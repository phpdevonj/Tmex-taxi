<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\User;
use App\Models\DriverSubscription;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Subscription as StripeSubscription;
use Stripe\PaymentIntent;
use Carbon\Carbon;

class SubscriptionController extends Controller
{

    public function __construct()
    {
        Stripe::setApiKey(getStripeSecretKey());
    }

    public function getList(Request $request)
    {
        $user = auth()->user();
         // Check if user already used the free plan (any status)

         $hasUsedFreePlan = DriverSubscription::where('driver_id', $user->id)->where('status','active')
         ->whereHas('subscription', function ($query) {
             $query->where('interval', 'free');
         })
         ->exists();

         $items = collect();

         // Only include free plan if not already used
         if (!$hasUsedFreePlan) {
             $freeSubscription = Subscription::where('interval', 'free')->first();
             if ($freeSubscription) {
                 $items->push($freeSubscription);
             }
         }
        
        // Add all paid plans (monthly/yearly)

        $paidSubscriptions = Subscription::whereIn('interval', ['month', 'year'])->get();
       
        $items = $items->merge($paidSubscriptions);
        // Convert price to float
        $items = $items->map(function($item) {
            $item->price = (float) $item->price;
            return $item;
        });
        $response = [
            'data' => $items,
        ];
        
        return json_custom_response($response);
    }

    public function driverSubscriptionList(){
        $user = auth()->user();
        $subscription = DriverSubscription::with('subscription')->where('driver_id',$user->id)
        ->orderByRaw("FIELD(status, 'active') DESC")->latest()->first();
        if (!$subscription) {
            return json_custom_response(['data' => null]);
        }

        try {
            $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);
        } catch (\Exception $e) {
            $stripeSubscription = null;
        }
        $endDateTimestamp = null;
        $cancelAtPeriodEnd = null;

        if ($stripeSubscription && isset($stripeSubscription->items->data[0]->current_period_end)) {
            $endDateTimestamp = $stripeSubscription->items->data[0]->current_period_end;
            $cancelAtPeriodEnd = $stripeSubscription->cancel_at_period_end;
        }
        $currencySymbols = [
            'USD' => '$',
            'EUR' => '€',
            'INR' => '₹',
            'GBP' => '£',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'SGD' => 'S$',
            'MXN' => 'Mex$',
        ];
        $currencyCode = $subscription->subscription->currency;
        $list = [
            'id' => $subscription->id,
            'subscription_id' => $subscription->subscription_id,
            'stripe_subscription_id' => $subscription->stripe_subscription_id,
            'status' => ucfirst($subscription->status),
            'price'=> (float) $subscription->subscription->price,
            'currency'=> $currencySymbols[$currencyCode] ?? '$', // show symbol if available
            'interval'=>  $subscription->subscription->interval,
            'created_at' => $subscription->created_at,
            'updated_at' => $subscription->updated_at,
            'end_date' => $endDateTimestamp ? date('d F Y', $endDateTimestamp): null,
            'auto_renew_in' => $subscription->subscription ? getReadableInterval($subscription->subscription->interval) : null,
            'auto_renewal_off' => $cancelAtPeriodEnd === true ? true : false, //If auto-renew is off, you will get true:
        ];
       

        return json_custom_response(['data' => $list]);
    }

    public function createPaymentIntent(Request $request) {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'subscription_id' => 'required',
        ]);

        if ($validator->fails()) {
            return json_custom_response([
                'status' => false,
                'message' => $validator->errors()->first(),
                'all_message' => $validator->errors()
            ], 422);
        }

        $subscription = Subscription::find($request->subscription_id);
        if (!$subscription) {
            return json_message_response(__('message.subscription_not_found'), 400);
        }
        try {

            if (!$user->stripe_customer_id) {
                $customer = Customer::create([
                    'email' => $user->email,
                    'name' => $user->display_name,
                    'phone' => $user->contact_number,
                ]);
                $user->stripe_customer_id = $customer->id;
                $user->save();
            }

            $intent = PaymentIntent::create([
                'amount' => intval($subscription->price * 100), // amount in cents
                'currency' => $subscription->currency,
                'customer' => $user->stripe_customer_id,
                'setup_future_usage' => 'off_session',
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'driver_id' => $user->id
                ],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'PaymentIntent created.',
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }


    public function createSetupIntent(Request $request)
    {
        $user = auth()->user();
    
        if (!$user->stripe_customer_id) {
            $customer = Customer::create([
                'email' => $user->email,
                'name'  => $user->display_name,
                'phone' => $user->contact_number,
            ]);
            $user->stripe_customer_id = $customer->id;
            $user->save();
        }
    
        $si = \Stripe\SetupIntent::create([
            'customer' => $user->stripe_customer_id,
            'payment_method_types' => ['card'],
            'usage' => 'off_session',
        ]);
    
        return response()->json([
            'status' => true,
            'message' => 'SetupIntent created.',
            'client_secret' => $si->client_secret,
            // optionally return these if you want saved-card UI on PaymentSheet
            // 'customerId' => $user->stripe_customer_id,
            // 'ephemeralKey' => \Stripe\EphemeralKey::create(
            //     ['customer' => $user->stripe_customer_id],
            //     ['stripe_version' => '2025-06-30']
            // )->secret,
        ]);
    }
    
   

    public function addSubscriptionByUser(Request $request) {
        $user = auth()->user();
        $validator = Validator::make($request->all(),[
            'subscription_id' => 'required',
        ]);

        if ( $validator->fails() ) {
            $data = [
                'status' => false,
                'message' => $validator->errors()->first(),
                'all_message' =>  $validator->errors()
            ];
            return json_custom_response($data, 422);
        }
        $subscription = Subscription::find($request->subscription_id);
        if (!$subscription) {
            return json_custom_response([
                'status' => false,
                'message' => __('message.subscription_not_found'),
            ], 200);
        }

        //  if user is already subscribed (optional)
        $existing = DriverSubscription::where('driver_id', $user->id)
        ->where('status', 'active')
        ->first();
        if ($existing) {
            return json_custom_response([
                'status' => false,
                'message' => __('message.already_subscribed_to_plan'),
            ], 200);
        }
        if ((float) $subscription->price == 0) {
            // Free Plan – no Stripe payment required
            DriverSubscription::create([
                'driver_id' => $user->id,
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => null,
                'start_date' => Carbon::now(),
                'end_date' => getIntervalCarbon($subscription->interval),  // you can define this helper
                'status' => 'active',
            ]);
    
            return response()->json([
                'status' => true,
                'message' => __('message.subscribed_to_free_plan'),
                'subscription_status' => 'active',
            ]);
        }
    
        // For paid plans, validate payment_method_id
        // $validator = Validator::make($request->all(),[
        //     'payment_intent_id' => 'required',
        // ]);

        if ( $validator->fails() ) {
            $data = [
                'status' => false,
                'message' => $validator->errors()->first(),
                'all_message' =>  $validator->errors()
            ];
            return json_custom_response($data, 422);
        }
        try {
            // Create a new Stripe Customer (if not already)
            if (!$user->stripe_customer_id) {
                $customer = Customer::create([
                    'email' => $user->email,
                    'name' => $user->display_name,
                    'phone' => $user->contact_number,
                ]);
                $user->stripe_customer_id = $customer->id;
                $user->save();
            }

            
            // $intent = PaymentIntent::retrieve($request->payment_intent_id);
           
            // if ($intent->status !== 'succeeded') {
            //     return response()->json([
            //         'status' => false,
            //         'message' => __('message.payment_not_successful')
            //     ], 200);
            // }
            // $paymentMethodId = $intent->payment_method;
            // $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            // $paymentMethod->attach(['customer' => $user->stripe_customer_id]);
             // Get default PM saved by the SetupIntent
            $customer = Customer::retrieve($user->stripe_customer_id);
            $paymentMethodId = $customer->invoice_settings->default_payment_method;
            if (!$paymentMethodId) {
                $pms = \Stripe\PaymentMethod::all([
                    'customer' => $user->stripe_customer_id,
                    'type' => 'card',
                ]);
                if (!count($pms->data)) {
                    return response()->json([
                        'status' => false,
                        'message' => 'No saved payment method. Please add a card.',
                    ], 400);
                }
                $paymentMethodId = $pms->data[0]->id;
                Customer::update($user->stripe_customer_id, [
                    'invoice_settings' => ['default_payment_method' => $paymentMethodId]
                ]);
            }
            // Set Default Payment Method
            // Customer::update($user->stripe_customer_id, [
            //     'invoice_settings' => [
            //         'default_payment_method' => $paymentMethodId
            //     ]
            // ]);
            // Create subscription
            $stripeSubscription = StripeSubscription::create([
                'customer' => $user->stripe_customer_id,
                'items' => [[
                    'price' => $subscription->stripe_price_id,
                ]],
                'default_payment_method' => $paymentMethodId,
                // 'payment_behavior' => 'default_incomplete',
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'driver_id' => $user->id,
                ],
            ]);
    
            // Save locally
            DriverSubscription::create([
                'driver_id' => $user->id,
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $stripeSubscription->id,
                'status' => $stripeSubscription->status, // 'active' or 'incomplete'
                'start_date' => Carbon::createFromTimestamp($stripeSubscription->items->data[0]->current_period_start),
                'end_date' => Carbon::createFromTimestamp($stripeSubscription->items->data[0]->current_period_end),
            ]);
    
            return response()->json([
                'status' => true,
                'message' => 'Subscription created successfully.',
                'subscription_status' => $stripeSubscription->status,
                'stripe_subscription_id' => $stripeSubscription->id,
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cancelSubscription(Request $request){
        $user = auth()->user();
        $validator = Validator::make($request->all(),[
            'subscription_id' => 'required',
        ]);

        if ( $validator->fails() ) {
            $data = [
                'status' => false,
                'message' => $validator->errors()->first(),
                'all_message' =>  $validator->errors()
            ];
            return json_custom_response($data, 422);
        }
        $subscription = DriverSubscription::where('subscription_id',$request->subscription_id)->where('driver_id',$user->id)->where('status','active')->first();
        if (!$subscription) {
            return json_custom_response([
                'status' => false,
                'message' => __('message.subscription_not_found'),
            ], 200);
        }
        try {
            // Retrieve and update the Stripe subscription
            $stripeSubscription = StripeSubscription::update(
                $subscription->stripe_subscription_id,
                ['cancel_at_period_end' => true]
            );
            return json_custom_response([
                'status' => true,
                'message' => __('message.auto_renew_off_notice'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update subscription: ' . $e->getMessage()
            ], 500);
        }

    }



    
}