<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;
use App\Models\DriverSubscription;
use App\Notifications\CommonNotification;
use Illuminate\Support\Facades\Log;

class NotifyDriversBeforeSubscriptionEnds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:drivers-subscription-end';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify drivers 2 days before their subscription ends';

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
        try {
            Log::info('Running NotifyDriversSubscriptionEnd at ' . now());
            $now = Carbon::now();
            $targetDate = $now->copy()->addDays(2)->startOfDay();
            $type = 'driver_subscription_end';
            Stripe::setApiKey(getStripeSecretKey());

            //  1. Cancel expired free plans
            $expiredFreePlans = DriverSubscription::where('status', 'active')
            ->whereDate('end_date', '<=', $now)
            ->whereHas('subscription', function ($q) {
                $q->where('price', 0);
            })
            ->get();

            Log::info('expiredFreePlans');

            foreach ($expiredFreePlans as $expiredSub) {
                $expiredSub->status = 'cancelled';
                $expiredSub->save();
            }
            // Notify 2 days before ending
            $subscriptions = DriverSubscription::whereDate('end_date', $targetDate)
                ->where('status', 'active')
                ->with('driver','subscription') // make sure relationship exists
                ->get();
            foreach ($subscriptions as $subscription) {
                $interval = $subscription->subscription->interval ?? 'monthly'; // default fallback
                $price = $subscription->subscription->price ?? 0;

                $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);

                if ($price == 0) {
                    $subject = 'Your Free Plan is Ending Soon';
                    $message = "Your free subscription plan will end in 2 days. Please upgrade to continue uninterrupted service.";
                } else {
                    if ($stripeSubscription->cancel_at_period_end) {
                        // Auto-renew is off
                        $subject = 'Your Subscription Will End Soon';
                        $message = "Your {$interval} subscription will end in 2 days as auto-renew is turned off.";
                    } else {
                        // Auto-renew is ON
                        $subject = 'Your Subscription Will Renew Soon';
                        $message = "Your {$interval} subscription will automatically renew in 2 days.";
                    }
                }
                // Send notification
                $driver_notification_data = [
                    'id' => $subscription->id,
                    'type' => $type,
                    'data' => [
                        'rider_id' => $subscription->driver_id ?? '',
                        'rider_name' => optional($subscription->driver)->display_name ?? '',
                    ],
                    'message' => $message,
                    'subject' => $subject,
                ];

                $subscription->driver->notify(new CommonNotification($type, $driver_notification_data));
            }
            Log::info('Completed NotifyDriversSubscriptionEnd at ' . now());

        }catch (\Throwable $e) {
            Log::error('Error in NotifyDriversSubscriptionEnd: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    
    }
}
