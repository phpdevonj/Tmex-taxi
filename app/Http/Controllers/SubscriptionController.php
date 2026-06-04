<?php

namespace App\Http\Controllers;

use App\DataTables\SubscriptionDataTable;
use App\Http\Requests\SubscriptionRequest;
use App\Models\Subscription;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;
use App\Models\DriverSubscription;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(SubscriptionDataTable $dataTable)
    {
       
        $pageTitle = __('message.list_form_title',['form' => __('message.subscription')] );
        $auth_user = authSession();
        $assets = ['datatable'];
        $button ='<a href="'.route('subscription.create').'" class="float-right btn btn-md border-radius-10 btn-outline-dark"><i class="fa fa-plus-circle"></i> '.__('message.add_form_title',['form' => __('message.subscription')]).'</a>';
        return $dataTable->render('global.datatable', compact('pageTitle','button','auth_user'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $pageTitle = __('message.add_form_title',[ 'form' => __('message.subscription')]);
        $currencies = config('stripe_currencies');

        return view('subscription.form', compact('pageTitle','currencies'));
    }

    /**
     * Retrieve Stripe secret and publishable keys based on the current mode.
     *
     * @return array
     */
    private function getStripeKeys()
    {
        $gateway = PaymentGateway::where('type', 'stripe')->first();
        if ($gateway) {
            $test = is_array($gateway->test_value) ? $gateway->test_value : json_decode($gateway->test_value, true);
            $live = is_array($gateway->live_value) ? $gateway->live_value : json_decode($gateway->live_value, true);
            if ($gateway->is_test == 1) {
                $secretKey = $test['secret_key'] ?? null;
                $publishableKey = $test['publishable_key'] ?? null;
            } else {
                $secretKey = $live['secret_key'] ?? null;
                $publishableKey = $live['publishable_key'] ?? null;
            }
            return [
                'secret_key' => $secretKey,
                'publishable_key' => $publishableKey,
            ];
        }
        return [
            'secret_key' => null,
            'publishable_key' => null,
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SubscriptionRequest $request)
    {
        $stripeKeys = $this->getStripeKeys();
        $secretKey = $stripeKeys['secret_key'];
        Stripe::setApiKey($secretKey);

        
        // Create product
        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);
        
        // Create price
        if ($request->interval === 'free' || (float) $request->price == 0) {
            // Create a price without recurring (for one-time or free plans)
            $price = Price::create([
                'unit_amount' => 0,
                'currency' => $request->currency,
                'product' => $product->id,
            ]);
        }else {
            $price = Price::create([
                'unit_amount' => $request->price * 100,
                'currency' => $request->currency,
                'recurring' => ['interval' => $request->interval],
                'product' => $product->id,
            ]);
        }
        // Merge the Stripe product & price IDs with the request data
        $data = $request->all();
        $data['stripe_product_id'] = $product->id;
        $data['stripe_price_id'] = $price->id;

        // Create subscription or plan
        Subscription::create($data);
    


        $message = __('message.save_form',['form' => __('message.subscription')]);
        
        if(request()->is('api/*')){
            return json_message_response( $message );
        }

        return redirect()->route('subscription.index')->withSuccess($message);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.subscription')]);
        $data = Subscription::findOrFail($id);
        $currencies = config('stripe_currencies');

        return view('subscription.form', compact('data', 'pageTitle','currencies', 'id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(SubscriptionRequest $request, $id)
    {
        $subscription = Subscription::findOrFail($id);

        $subscription->fill($request->all())->update();
        $stripeKeys = $this->getStripeKeys();
        $secretKey = $stripeKeys['secret_key'];
        Stripe::setApiKey($secretKey);

        $price = Price::retrieve($subscription->stripe_price_id);
        $productId = $price->product;


        $product = \Stripe\Product::update(
           $productId,
            ['name' => $request->name,'description' => $request->description,]
        );
        $message = __('message.update_form',['form' => __('message.subscription')]);

        if(request()->is('api/*')){
            return json_message_response( $message );
        }

        if(auth()->check()){
            return redirect()->route('subscription.index')->withSuccess($message);
        }
        return redirect()->back()->withSuccess($message);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if(env('APP_DEMO')){
            $message = __('message.demo_permission_denied');
            if(request()->ajax()) {
                return response()->json(['status' => true, 'message' => $message ]);
            }
            return redirect()->route('subscription.index')->withErrors($message);
        }
        $subscription = Subscription::find($id);
        $status = 'errors';
        if (!$subscription) {
            $message = __('message.not_found_entry', ['name' => __('message.subscription')]);
            return $this->responseBack($message, $status);
        }
          // Step 1: Check if any driver has this subscription in your DB
        $localActiveCount = DriverSubscription::where('subscription_id', $id)
        ->whereIn('status', ['active'])
        ->count();

        if ($localActiveCount > 0) {
            $message = __('Some users still have active subscriptions using this plan.');
            return $this->responseBack($message, $status);
        }


        // Step 2: Proceed with Stripe clean-up
        $stripeKeys = $this->getStripeKeys();
        Stripe::setApiKey($stripeKeys['secret_key']);

        try {
            // Step 1: Check if any active subscriptions are using this price
            $priceId = $subscription->stripe_price_id;
            $price = Price::retrieve($priceId);
             // Optionally disable the Stripe product if no other prices exist
            $productId = $price->product;
            $otherPrices = Price::all(['product' => $productId]);
            if (count($otherPrices->data) <= 1) {
                Product::update($productId, [
                    'active' => false,
                ]);
            }
            // Step 3: Delete local subscription record
            $subscription->delete();

            // if ($price->type === 'recurring') {
            //     $usedSubscriptions = \Stripe\Subscription::all([
            //         'limit' => 1,
            //         'price' => $priceId,
            //         'status' => 'active',
            //     ]);
            //     dd($usedSubscriptions->data);
                
            //     Log::error('Stripe usedSubscriptions check', [
            //         'priceId' => $priceId,
            //         'response' => $usedSubscriptions->data
            //     ]);
                
        
            //     if (count($usedSubscriptions->data) > 0) {
            //         $message = __('Active subscriptions exist using this price.', ['name' => __('message.subscription')]);
            //         return $this->responseBack($message, $status);
            //     }
            // }
    
    
            // Step 2: Optionally delete the Stripe Product (if no other prices attached)
            // $productId = $price->product;
            // $otherPrices = Price::all(['product' => $productId]);
    
            // if (count($otherPrices->data) <= 1) {
            //     \Stripe\Product::update($productId, [
            //         'active' => false,
            //     ]);
            // }
    
            // Step 4: Delete local DB record
            // $subscription->delete();
            $message = __('message.delete_form', ['form' => __('message.subscription')]);
            return $this->responseBack($message, 'success');
    
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $this->responseBack($message, 'errors');
        }
    }

    private function responseBack($message, $status = 'errors') {
        if(request()->is('api/*')){
            return json_message_response($message);
        }

        if(request()->ajax()) {
            return response()->json(['status' => $status === 'success', 'message' => $message ]);
        }
        return redirect()->back()->with($status, $message);
    }
}
