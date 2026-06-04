<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\Stripe;
use Stripe\Transfer;
use Stripe\Payout;
use Stripe\Account;
use Carbon\Carbon;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHistory;
use App\Models\WithdrawRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Notifications\PayoutSuccessNotification;

class ProcessDriverPayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payouts:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process driver payouts based on payout frequency';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('Starting driver payout processing...');

        // Check if payout is enabled in settings
        $payoutSetting = Setting::where('key', 'payout')
            ->where('type', 'ride')
            ->first();

        if (!$payoutSetting || $payoutSetting->value != '1') {
            Log::info('Driver payout processing skipped - payout disabled in settings');
            return Command::SUCCESS;
        }
        Log::info('Payout is enabled. Starting payout processing...');

        // Check if Stripe API key is available
        
            $stripeSecretKey = getStripeSecretKey();
            if (!$stripeSecretKey) {
                Log::error('Driver payout processing failed - Stripe secret key not found');
                return Command::FAILURE;
            }

            Stripe::setApiKey($stripeSecretKey);
            $today = Carbon::now()->format('Y-m-d');

            // Get all drivers with wallets that have total_amount > 0
            $drivers = User::where('user_type', 'driver')
                ->whereHas('userWallet', function ($query) {
                    $query->where('total_amount', '>', 0);
                })
                ->with('userWallet')
                ->get();

            Log::info("Driver payout processing - Found {$drivers->count()} drivers with wallet balance > 0");

            $processedCount = 0;
            $errorCount = 0;

            foreach ($drivers as $driver) {
                $shouldPayout = false;

                // Check payout frequency based on driver's payout_schedule
                $payoutSchedule = $driver->payout_schedule ?? 'weekly';
                
                switch ($payoutSchedule) {
                    case 'daily':
                        $shouldPayout = true;
                        break;

                    case 'weekly':
                        // Pay every Monday
                        if (Carbon::now()->isMonday()) {
                            $shouldPayout = true;
                        }
                        break;

                    case 'monthly':
                        // Pay on the 1st of each month
                        if (Carbon::now()->day == 1) {
                            $shouldPayout = true;
                        }
                        break;
                }

                if ($shouldPayout) {
                    $amountToPay = $driver->userWallet->total_amount;
                    $amountToPay = (float) number_format( (float) $amountToPay, 2,'.','');

                    if ($amountToPay > 0 && $driver->stripe_account_id) {
                        if (!$this->isStripeAccountReady($driver->stripe_account_id)) {
                            Log::warning("Driver payout skipped - Stripe account not fully set up", [
                                'driver_id' => $driver->id,
                                'driver_name' => $driver->display_name
                            ]);
                            continue; // skip this driver
                        }

                        try {
                            // Get currency from Stripe account
                            $stripeCurrency = $this->getStripeAccountCurrency($driver->stripe_account_id);
                            
                            // Step 1: Transfer money from platform account to driver's Stripe account
                            $transfer = Transfer::create([
                                'amount' => $amountToPay * 100, // convert to cents
                                'currency' => $stripeCurrency,
                                'destination' => $driver->stripe_account_id,
                                'description' => "Transfer to driver account for payout - {$today}",
                                'metadata' => [
                                    'driver_id' => $driver->id,
                                    'driver_name' => $driver->display_name,
                                    'transfer_date' => $today
                                ]
                            ]);

                            Log::info("Transfer created successfully", [
                                'driver_id' => $driver->id,
                                'driver_name' => $driver->display_name,
                                'transfer_id' => $transfer->id,
                                'amount' => $amountToPay,
                                'currency' => $stripeCurrency
                            ]);

                            // Step 2: Create payout from driver's Stripe account to their bank account
                            $payout = Payout::create([
                                'amount' => $amountToPay * 100, // convert to cents
                                'currency' => $stripeCurrency,
                                'description' => "Payout to bank account for {$today}",
                                'metadata' => [
                                    'driver_id' => $driver->id,
                                    'driver_name' => $driver->display_name,
                                    'payout_date' => $today,
                                    'transfer_id' => $transfer->id
                                ]
                            ], [
                                'stripe_account' => $driver->stripe_account_id
                            ]);

                            // Update wallet, create wallet history, and create withdrawal request
                            DB::beginTransaction();
                            try {
                                // Update wallet balance
                                $driver->userWallet->total_amount = 0;
                                $driver->userWallet->save();

                                // Create wallet history record
                                WalletHistory::create([
                                    'user_id' => $driver->id,
                                    'type' => 'debit',
                                    'transaction_type' => 'payout',
                                    'amount' => $amountToPay,
                                    'balance' => 0,
                                    'currency' => $stripeCurrency,
                                    'datetime' => now(),
                                    'description' => "Payout to bank account - Transfer ID: {$transfer->id}, Payout ID: {$payout->id}",
                                    'data' => [
                                        'stripe_transfer_id' => $transfer->id,
                                        'stripe_payout_id' => $payout->id,
                                        'transfer_status' => 'created',
                                        'payout_status' => $payout->status,
                                        'payout_date' => $today,
                                        'payout_schedule' => $payoutSchedule
                                    ]
                                ]);

                                // Create withdrawal request with approved status
                                WithdrawRequest::create([
                                    'user_id' => $driver->id,
                                    'amount' => $amountToPay,
                                    'currency' => $stripeCurrency,
                                    'status' => 1, // 1 = approved
                                ]);

                                // ── Payout notifications (push + email) ──────────────────────
                                // Deduplication: check that we haven't already notified this
                                // driver for this exact payout ID in a previous run.
                                $alreadyNotified = WalletHistory::where('user_id', $driver->id)
                                    ->where('transaction_type', 'payout')
                                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.stripe_payout_id')) = ?", [$payout->id])
                                    ->where('data', 'LIKE', '%"notified":true%')
                                    ->exists();
                                if (!$alreadyNotified) {
                                    try {
                                        $payoutDate = Carbon::now()->format('d M Y, h:i A');
                                        $driver->notify(new PayoutSuccessNotification(
                                            $payout->id,
                                            $amountToPay,
                                            strtoupper($stripeCurrency),
                                            $payoutDate
                                        ));
                                        // Mark the WalletHistory record as notified so we
                                        // never send duplicates if the command runs again.
                                        WalletHistory::where('user_id', $driver->id)
                                            ->where('transaction_type', 'payout')
                                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.stripe_payout_id')) = ?", [$payout->id])
                                            ->update(['data' => DB::raw(
                                                "JSON_SET(data, '\$.notified', CAST('true' AS JSON))"
                                            )]);
                                        Log::info("Payout notification sent to driver", [
                                            'driver_id' => $driver->id,
                                            'payout_id' => $payout->id,
                                        ]);
                                    } catch (\Exception $notifyEx) {
                                        // Notification failure must not affect the payout result
                                        Log::warning("Payout notification failed for driver {$driver->id}: " . $notifyEx->getMessage());
                                    }
                                } else {
                                    Log::info("Payout notification already sent, skipping duplicate", [
                                        'driver_id' => $driver->id,
                                        'payout_id' => $payout->id,
                                    ]);
                                }

                                DB::commit();

                                Log::info("Driver payout successful", [
                                    'driver_id' => $driver->id,
                                    'driver_name' => $driver->display_name,
                                    'amount' => $amountToPay,
                                    'currency' => $stripeCurrency,
                                    'transfer_id' => $transfer->id,
                                    'payout_id' => $payout->id,
                                    'transfer_status' => 'created',
                                    'payout_status' => $payout->status
                                ]);
                                $processedCount++;
                            } catch (\Exception $e) {
                                DB::rollBack();
                                throw $e;
                            }
                        } catch (\Exception $e) {
                            Log::error("Driver payout failed", [
                                'driver_id' => $driver->id,
                                'driver_name' => $driver->display_name,
                                'error' => $e->getMessage()
                            ]);
                            $errorCount++;
                        }
                    } else {
                        if (!$driver->stripe_account_id) {
                            Log::warning("Driver payout skipped - no Stripe account", [
                                'driver_id' => $driver->id,
                                'driver_name' => $driver->display_name
                            ]);
                        }
                    }
                } else {
                    Log::info("Driver #{$driver->id} ({$driver->display_name}) - Payout not due today (schedule: {$payoutSchedule})");
                }
            }

            Log::info("Driver payout processing completed", [
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
                'total_drivers' => $drivers->count()
            ]);

            return Command::SUCCESS;
        
    }

    /**
     * Get currency from Stripe account
     *
     * @param string $stripeAccountId
     * @return string
     */
    private function getStripeAccountCurrency($stripeAccountId)
    {
        try {
            $account = Account::retrieve($stripeAccountId);
            return $account->default_currency ?? 'MXN';
        } catch (\Exception $e) {
            Log::warning("Failed to get Stripe account currency for account {$stripeAccountId}: " . $e->getMessage());
            return 'MXN'; // fallback to MXN
        }
    }

    private function isStripeAccountReady($stripeAccountId) {
        try {
            $account = Account::retrieve($stripeAccountId);

            // If payouts are disabled, skip
            if (!empty($account->requirements->disabled_reason)) {
                return false;
            }

            // If something is still due, skip
            if (!empty($account->requirements->currently_due)) {
                return false;
            }

            // If no external account (bank/card) added, skip
            if (empty($account->external_accounts->data)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            \Log::warning("Stripe account check failed: {$stripeAccountId} - " . $e->getMessage());
            return false;
        }
    }

}
