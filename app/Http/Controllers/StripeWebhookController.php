<?php

namespace App\Http\Controllers;

    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Log;
    use Stripe\Stripe;
    use Stripe\Webhook;
    use Stripe\Subscription as StripeSubscription;
    use Stripe\Customer;
    use App\Models\User;
    use App\Models\Subscription;
    use App\Models\DriverSubscription;
    use Carbon\Carbon;

    class StripeWebhookController extends Controller
    {
        public function handle(Request $request)
        {
            $secretKey = getStripeSecretKey();
            Stripe::setApiKey($secretKey);
            $endpoint_secret = config('services.stripe.webhook_secret');
            $payload = $request->getContent();
            $sig_header = $request->header('Stripe-Signature');
            try {
                $event = Webhook::constructEvent(
                    $payload, $sig_header, $endpoint_secret
                );
            } catch (\UnexpectedValueException $e) {
                return response('Invalid payload', 400);
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                return response('Invalid signature', 400);
            }
            // Handle event types
            switch ($event->type) {
                case 'invoice.payment_succeeded':
                    $invoice = $event->data->object;
                    // $this->handleInvoicePaid($invoice);
                    break;

                case 'payment_intent.succeeded':
                    $intent = $event->data->object;
                    // $this->handlePaymentIntentSucceeded($intent);
                    break;
                
                case 'customer.subscription.updated':
                    $subscription = $event->data->object;
                    $stripeSubscriptionId = $subscription->id;
                    $status = $subscription->status;
                    $cancelAtPeriodEnd = $subscription->cancel_at_period_end;
                    $canceledAt = $subscription->canceled_at;

                    // Condition: Cancelled due to period end
                    if (
                        $status === 'canceled' &&
                        $cancelAtPeriodEnd === true &&
                        !empty($canceledAt)
                    ) {
                        DriverSubscription::where('stripe_subscription_id', $stripeSubscriptionId)
                            ->update([
                                'status' => 'cancelled',
                                'updated_at' => now()
                            ]);
                    }
                    // Subscription renewed (still active) → update start and end dates
                    if ($status === 'active') {
                        $currentPeriodStart = Carbon::createFromTimestamp($subscription->items->data[0]->current_period_start);
                        $currentPeriodEnd = Carbon::createFromTimestamp($subscription->items->data[0]->current_period_end);

                        DriverSubscription::where('stripe_subscription_id', $stripeSubscriptionId)
                            ->update([
                                'start_date' => $currentPeriodStart,
                                'end_date' => $currentPeriodEnd,
                                'updated_at' => now()
                            ]);
                    }
                    break;

                default:
                    Log::info('Unhandled Stripe event: ' . $event->type);
            }

            return response('Webhook received', 200);
        }

        protected function handleInvoicePaid($invoice)
        {
            $subscriptionId = $invoice->subscription;
            $paymentIntentId = $invoice->payment_intent;

            if (!$subscriptionId) {
                // PAYMENT received BUT NO subscription attached — possibly custom payment

                Log::warning("Payment succeeded without subscription", ['invoice_id' => $invoice->id]);
                try {
                    \Stripe\Refund::create([
                        'payment_intent' => $paymentIntentId,
                        'reason' => 'requested_by_customer',
                    ]);
                    Log::info("Refund issued for payment intent: {$paymentIntentId}");
                } catch (\Exception $e) {
                    Log::error("Refund failed for payment intent: {$paymentIntentId}. Error: " . $e->getMessage());
                }
    
                return response()->json(['status' => 'refund issued']);
            }
        }

        // protected function handlePaymentIntentSucceeded($intent)
        // {
        //     $customerId = $intent->customer;

        //     Log::info("PaymentIntent succeeded for customer {$customerId}");

        //     // Same flow as above if you need to match payment intent to user/subscription
        // }
    }
