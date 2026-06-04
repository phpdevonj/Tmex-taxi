<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
class PayoutSuccessNotification extends Notification
{
    use Queueable;
    public string $payoutId;
    public float  $amount;
    public string $currency;
    public string $payoutDate;
    /**
     * @param string $payoutId   Stripe payout ID  (e.g. po_xxx)
     * @param float  $amount     Payout amount in the account currency
     * @param string $currency   Currency code (e.g. USD, MXN)
     * @param string $payoutDate Formatted date-time string
     */
    public function __construct(string $payoutId, float $amount, string $currency, string $payoutDate)
    {
        $this->payoutId   = $payoutId;
        $this->amount     = $amount;
        $this->currency   = strtoupper($currency);
        $this->payoutDate = $payoutDate;
    }
    /**
     * Delivery channels: mail only (FCM is sent manually in via()).
     */
    public function via($notifiable): array
    {
        // Send FCM push to the driver when an FCM token is present
        if (!empty($notifiable->fcm_token)) {
            $this->sendFcm($notifiable);
        }
        return ['mail'];
    }
    /**
     * Send Firebase Cloud Messaging push notification to the driver.
     */
    protected function sendFcm($notifiable): void
    {
        try {
            $factory   = (new Factory)->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')));
            $messaging = $factory->createMessaging();
            $title = 'Payout Successful 🎉';
            $body  = 'Your payout of ' . number_format($this->amount, 2) . ' ' . $this->currency
                   . ' has been successfully processed.';
            $payload = [
                'message' => [
                    'token'   => $notifiable->fcm_token,
                    'android' => ['priority' => 'HIGH'],
                    'apns'    => [
                        'payload' => [
                            'aps' => [
                                'category'       => 'GENERAL',
                                'mutable-content' => 1,
                                'alert'          => ['title' => $title, 'body' => $body],
                                'sound'          => 'default',
                            ],
                        ],
                    ],
                    'data' => [
                        'title'       => $title,
                        'body'        => $body,
                        'type'        => 'payout_success',
                        'payout_id'   => $this->payoutId,
                        'amount'      => (string) number_format($this->amount, 2),
                        'currency'    => $this->currency,
                        'payout_date' => $this->payoutDate,
                    ],
                ],
            ];
            $messaging->send($payload['message']);
            Log::info('PayoutSuccessNotification: FCM push sent to driver', [
                'driver_id'  => $notifiable->id,
                'payout_id'  => $this->payoutId,
            ]);
        } catch (\Throwable $e) {
            Log::error('PayoutSuccessNotification: FCM push failed for driver ' . $notifiable->id
                . ': ' . $e->getMessage());
        }
    }
    /**
     * Build the mail notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Payout Has Been Processed Successfully')
            ->view('emails.payout_success', [
                'driver'     => $notifiable,
                'payoutId'   => $this->payoutId,
                'amount'     => number_format($this->amount, 2),
                'currency'   => $this->currency,
                'payoutDate' => $this->payoutDate,
            ]);
    }
    /**
     * Array representation (used by the database channel if added later).
     */
    public function toArray($notifiable): array
    {
        return [
            'type'        => 'payout_success',
            'payout_id'   => $this->payoutId,
            'amount'      => $this->amount,
            'currency'    => $this->currency,
            'payout_date' => $this->payoutDate,
        ];
    }
}