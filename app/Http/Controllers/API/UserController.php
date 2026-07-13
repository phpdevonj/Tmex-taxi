<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\DriverDocument;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\DriverResource;
use Illuminate\Support\Facades\Password;
use App\Models\AppSetting;
use App\Models\Setting;
use App\Models\DriverSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\DriverRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\DriverStepOneRequest;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Account;
use GuzzleHttp\Client;
use Exception;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function register(UserRequest $request)
    {
        $input = $request->all();
        // Validate device ID if provided
        // Check referral code if provided
        $referrer = null;
        if (isset($input['referral_code'])) {
            $referrer = validateReferralCode($input['referral_code']);
            if (!$referrer) {
                return json_message_response(__('message.invalid_referral_code'), 422);
            }

            // Check device ID if referral code exists
            $validReferral = true;
            $unique_device_id_allow = SettingData('referral', 'unique_device_id') ?? null;
            if (isset($input['device_id']) && $unique_device_id_allow == 1) {
                if (!validateDeviceId($input['device_id'])) {
                    $validReferral = false;
                }
            }
        }
        
        $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'rider';
        if(isset($input['login_type'])=='mobile'){
            $input['password'] = Hash::make($input['contact_number']); // Use contact number as password for mobile login
        }else{
            $input['password'] = Hash::make($input['password']);
        }
        $input['login_type'] = 'mobile';
        $input['referral_code'] = generateUniqueReferralCode();
        $input['referred_by'] = $referrer?->id;
        $input['device_id'] = $request->device_id ?? null;
        $input['device_type'] = $request->device_type ?? null;

        if( in_array($input['user_type'],['driver']))
        {
            $input['status'] = isset($input['status']) ? $input['status']: 'pending';
        }

        $input['display_name'] = $input['first_name']." ".$input['last_name'];
        $input['last_actived_at'] = now();
        $input['contact_number'] = trim($input['country_code']) . trim($input['contact_number']);

        // Create a customer in Stripe using the helper function
        $stripeCustomer = createStripeCustomer($input['email'], $input['display_name'], $input['contact_number'], 'stripe');
        if(isset($stripeCustomer['id'])) {
            $input['stripe_customer_id'] = $stripeCustomer['id'];
        } else {
            return json_message_response('Failed to create Stripe customer.',400);
        }

        $user = User::create($input);
        $user->assignRole($input['user_type']);

        if( $request->has('user_detail') && $request->user_detail != null ) {
            $user->userDetail()->create($request->user_detail);
        }

        // Create referral record 
        if ($referrer) {
            createReferral($user->referred_by, $user->id, $validReferral);
           
            $referral_conditions = [
                SettingData('referral', 'referral_reward_condition') ?? null,
                SettingData('driver_referral', 'driver_referral_reward_condition') ?? null
            ];
            if (in_array('on_registration', $referral_conditions, true)) {
                processReferral($user->referred_by, $user->id,$user->user_type);
            }
        }

        $message = __('message.save_form',['form' => __('message.'.$input['user_type']) ]);
        $user->api_token = $user->createToken('auth_token')->plainTextToken;
        $user->profile_image = getSingleMedia($user, 'profile_image', null);
        $response = [
            'message' => $message,
            'data' => $user
        ];
        return json_custom_response($response);
    }

    public function driverRegister(DriverRequest $request)
    {
        DB::beginTransaction();
        try {
            $input = $request->all();
            $password = $input['password'];

            // Handle referral code
            $referrer = null;
            $validReferral = true;
            if (isset($input['referral_code'])) {
                $referrer = validateReferralCode($input['referral_code']);
                if (!$referrer) {
                    return json_message_response(__('message.invalid_referral_code'), 422);
                }
        
                // Validate device ID if required
                $unique_device_id_allow = SettingData('referral', 'unique_device_id') ?? null;
                if (isset($input['device_id']) && $unique_device_id_allow == 1) {
                    if (!validateDeviceId($input['device_id'])) {
                        $validReferral = false;
                    }
                }
            }

            // Prepare user data
            $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'driver';
            $input['password'] = Hash::make($password);
            $input['status'] = $input['status'] ??  'pending';
            $input['display_name'] = $input['first_name']." ".$input['last_name'];
            $input['is_available'] = 1;
            $input['last_actived_at'] = now();
            $input['contact_number'] = trim($input['country_code']) . trim($input['contact_number']);
            $input['referral_code'] = generateUniqueReferralCode();
            $input['referred_by'] = $referrer?->id;
            $input['device_id'] = $request->device_id ?? null;
            $input['device_type'] = $request->device_type ?? null;
            $input['payout_schedule'] = $input['payout_schedule'] ?? 'weekly'; // default
                 
            $user = User::create($input);
            $user->assignRole($input['user_type']);
            if (!empty($request->profile_image)) {
                $base64Str = $request->profile_image;
            
                // Check if it has a base64 prefix
                if (preg_match('/^data:(.*?);base64,(.*)$/', $base64Str, $matches)) {
                    $mimeType = $matches[1];
                    $base64Data = base64_decode($matches[2]);
                } else {
                    // Handle raw base64 without prefix (assume JPEG)
                    $mimeType = 'image/jpeg';
                    $base64Data = base64_decode($base64Str);
                }
            
                // Get file extension and temp path
                $extension = explode('/', $mimeType)[1] ?? 'jpg';
                $fileName = 'profile_image_' . Str::random(10) . '.' . $extension;
                $tempFilePath = storage_path('app/' . $fileName);
            
                // Save to temporary file
                File::put($tempFilePath, $base64Data);
            
                // Replace profile image
                $user->clearMediaCollection('profile_image');
                $user->addMedia($tempFilePath)
                     ->usingFileName($fileName)
                     ->toMediaCollection('profile_image');
            
                // Delete temp file
                File::delete($tempFilePath);
            }
            if( $request->has('user_detail') && $request->user_detail != null ) {
                $user->userDetail()->create($request->user_detail);
            }
            
            if( $request->has('user_bank_account') && $request->user_bank_account != null ) {
                $user->userBankAccount()->create($request->user_bank_account);
            }
            $user->userWallet()->create(['total_amount' => 0 ]);

            // Stripe setup
            $secretKey = getStripeSecretKey();
            Stripe::setApiKey($secretKey);

            $customer = Customer::create([
                'email' => $user->email,
                'name' => $user->display_name,
                'phone' => $user->contact_number,
            ]);

            // Get country code from phone
            $phoneUtil = PhoneNumberUtil::getInstance();
            $number = $phoneUtil->parse($user->contact_number, null);
            $countryCode = strtoupper($phoneUtil->getRegionCodeForNumber($number) ?: 'MX');
            $notallowedCountries = ['IN']; // Update based on Stripe's allowed list
            if (in_array($countryCode, $notallowedCountries)) {
                return response()->json(['status' => false, 'message' => 'Connected accounts in ' . $countryCode . ' cannot be created by this platform.'], 403);
            }
            // 
            $commonData = [
                'type' => 'custom',
                'country' => $countryCode,
                'email' => $user->email,
                'business_type' => 'individual',
                'tos_acceptance' => [
                    'date' => time(),
                    'ip' => $_SERVER['REMOTE_ADDR'],
                ],
                'settings' => [
                    'payouts' => [
                        'schedule' => [] // We'll set it below based on your logic
                    ],
                ],
            ];

            
            if ($countryCode === 'CA') {
                // Option 1: If only transferring funds (no card payments)
                $commonData['capabilities'] = [
                    'transfers' => ['requested' => true],
                ];
                $commonData['tos_acceptance']['service_agreement'] = 'recipient'; // Required in CA
            } elseif ($countryCode === 'US') {
                // US – must request both card_payments and transfers
                $commonData['capabilities'] = [
                    'card_payments' => ['requested' => true],
                    'transfers'     => ['requested' => true],
                ];
            }elseif ($countryCode === 'MX') {
                // Mexico – must request both card_payments and transfers
                $commonData['capabilities'] = [
                    'transfers'     => ['requested' => true],
                ];
                // Add service agreement for Mexico
                $commonData['tos_acceptance']['service_agreement'] = 'recipient'; // Required for transfers in MX
            }else {
                // For US and others: full service agreement is default
                $commonData['capabilities'] = [
                    'transfers' => ['requested' => true],
                ];
                // Do NOT include service_agreement (Stripe defaults to full)
            }
                // Adjust based on driver preference
                // switch (strtolower($user->payout_schedule)) {
                //     case 'daily':
                //         $payoutSchedule = [
                //             'interval' => 'daily'
                //         ];
                //         break;

                //     case 'weekly':
                //         $payoutSchedule = [
                //             'interval' => 'weekly',
                //             'weekly_anchor' => 'monday' // Or let driver choose
                //         ];
                //         break;

                //     case 'monthly':
                //         $payoutSchedule = [
                //             'interval' => 'monthly',
                //             'monthly_anchor' => 1 // Day of month (1 = 1st day)
                //         ];
                //         break;
                //     default:
                //         $payoutSchedule = [
                //             'interval' => 'weekly',
                //             'weekly_anchor' => 'monday'
                //         ];
                // }
                // $commonData['settings']['payouts']['schedule'] = $payoutSchedule;

                
            
                // Create the connected account
                $account = Account::create($commonData);
                // Save stripe_customer_id
                // Save Stripe IDs
                $user->update([
                    'stripe_customer_id' => $customer->id,
                    'stripe_account_id' => $account->id,
                ]);


                $accountLink = \Stripe\AccountLink::create([
                    'account' => $account->id,
                    'refresh_url' => route('stripe.reauth', ['account_id' => $account->id]),
                    'return_url' => route('stripe.return'),
                    'type' => 'account_onboarding',
                ]);
           
            // Handle referral creation and reward
            if ($referrer) {
                createReferral($user->referred_by, $user->id, $validReferral);
            
                $referral_conditions = [
                    SettingData('referral', 'referral_reward_condition') ?? null,
                    SettingData('driver_referral', 'driver_referral_reward_condition') ?? null
                ];
                if (in_array('on_registration', $referral_conditions, true)) {
                    processReferral($user->referred_by, $user->id,$user->user_type);
                }
            }
            DB::commit();
            $message = __('message.save_form',['form' => __('message.driver') ]);
            $user->api_token = $user->createToken('auth_token')->plainTextToken;
            $user->is_verified_driver = (int) $user->is_verified_driver;// DriverDocument::verifyDriverDocument($user->id);
            $user->profile_image = getSingleMedia($user, 'profile_image', null);
            $user->stripe_url = $accountLink->url;
            $user->account_id = $account->id;
            $response = [
                'message' => $message,
                'data' => $user
            ];
            return json_custom_response($response);
        } catch (\Exception $e) {
            DB::rollBack();
            return json_custom_response([
                'status' => false,
                'message' => 'Registration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function login(LoginRequest $request)
    {      
        Log::channel('custom_api')->info('[LOGIN] API called', ['request' => $request->all(),'line' => __LINE__]); 
        
        try {
            if(Auth::attempt(['email' => request('email'), 'password' => request('password'), 'user_type' => request('user_type')])){
                Log::channel('custom_api')->info('[LOGIN] Authentication successful', ['email' => $request->email,'line' => __LINE__]);
                
                $user = Auth::user();
    
                if( $user->status == 'banned' ) {
                    $message = __('message.account_banned');
                    Log::channel('custom_api')->warning('[LOGIN] Account is banned', ['email' => $request->email,'line' => __LINE__]);
                    return json_message_response($message,400);
                }
    
                if(request('player_id') != null){
                    $user->player_id = request('player_id');
                }
    
                if(request('fcm_token') != null){
                    $user->fcm_token = request('fcm_token');
                }
                $user->last_actived_at = now();
                $user->save();
                $user->tokens()->delete();
                $success = $user;
                $success['api_token'] = $user->createToken('auth_token')->plainTextToken;
                $success['profile_image'] = getSingleMedia($user,'profile_image',null);
                $is_verified_driver = false;
                if($user->user_type == 'driver') {
                    $is_verified_driver = $user->is_verified_driver; // DriverDocument::verifyDriverDocument($user->id);
                }
                $success['is_verified_driver'] = (int) $is_verified_driver;
                unset($success['media']);
                Log::channel('custom_api')->info('[LOGIN] Login successful, response returned', ['email' => $request->email,'line' => __LINE__]);
                return json_custom_response([ 'data' => $success ], 200 );
            }
            else{
                Log::channel('custom_api')->warning('[LOGIN] Authentication failed', ['email' => $request->email,'line' => __LINE__]);
                $message = __('auth.failed');
                
                return json_message_response($message,400);
            }
        } catch (\Exception $e) {
            Log::channel('custom_api')->error('[LOGIN] Exception occurred', ['email'   => $request->email ?? null,'error'   => $e->getMessage(),'line' => __LINE__]);
            $message = __('auth.failed');
            return json_message_response($message,400);
        }
    }

    public function userList(Request $request)
    {
        $user_type = isset($request['user_type']) ? $request['user_type'] : 'rider';
        
        $user_list = User::query();
        
        $user_list->when(request('user_type'), function ($q) use($user_type) {
            return $q->where('user_type', $user_type);
        });

        $user_list->when(request('fleet_id'), function ($q) {
            return $q->where('fleet_id', request('fleet_id'));
        });

        if( $request->has('is_online') && isset($request->is_online) )
        {
            $user_list = $user_list->where('is_online',request('is_online'));
        }
        
        if( $request->has('status') && isset($request->status) )
        {
            $user_list = $user_list->where('status',request('status'));
        }

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page))
        {
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $user_list->count();
            }
        }
        
        $user_list = $user_list->paginate($per_page);

        if( $user_type == 'driver' ) {
            $items = DriverResource::collection($user_list);
        } else {
            $items = UserResource::collection($user_list);
        }

        $response = [
            'pagination' => json_pagination_response($items),
            'data' => $items,
        ];
        
        return json_custom_response($response);
    }

    public function userDetail(Request $request)
    {
        $id = $request->id;
        // $user = auth()->user();
        // dd($user);
        $user = User::where('id',$id)->first();
        if(empty($user))
        {
            $message = __('message.user_not_found');
            return json_message_response($message,400);   
        }

        $response = [
            'data' => null,
        ];
        if( $user->user_type == 'driver') {
            $user_detail = new DriverResource($user);
            $settings = Setting::select('value')->where('key','subscription')->where('type','ride')->first();
            // Check for active subscription
            $activeSubscription = $user->driverSubscriptions()->where('status', 'active')->latest()->first();
            $stripeDetails = $this->getStripeAccountDetails($user->stripe_account_id,$user);

            $response = [
                'data' => $user_detail,
                'required_document' => driver_required_document($user),
                'subscription' =>  $activeSubscription ? 1 : 0,
                'is_expired' => $activeSubscription ? false : true ,// true means subscription is expired
                'stripe_status'  => $stripeDetails['status'],
                'stripe_message' =>$stripeDetails['message'] ?? NULL,
                'stripe_url'     =>  $stripeDetails['accountUrl'] ?? NULL,
            ];
        } else {
            $user_detail = new UserResource($user);
            $response = [
                'data' => $user_detail
            ];
        }

        return json_custom_response($response);

    }

    public function getStripeAccountDetails($stripeAccountId,$user){
        $response = [
            'currencySymbol' => '',
            'account' => null,
            'accountUrl' => null,
            'status' => 'not_connected',
        ];
      
        $secretKey = getStripeSecretKey();
        Stripe::setApiKey($secretKey);
        try {
            // Stripe se account details lo
            if (empty($stripeAccountId)) {
              

                $phoneUtil = PhoneNumberUtil::getInstance();
                $number = $phoneUtil->parse($user->contact_number, null);
                $countryCode = strtoupper($phoneUtil->getRegionCodeForNumber($number) ?: 'MX');
                $notallowedCountries = ['IN']; // Update based on Stripe's allowed list
                if (in_array($countryCode, $notallowedCountries)) {
                    return [
                        'status'       => 'not_connected',
                        'message'      => 'Connected accounts in ' . $countryCode . ' cannot be created by this platform.',
                        'accountUrl'   => null,
                        'currency'     => null,
                        'currencySymbol' => null,
                        'account'      => null,
                    ];
                }

                
    
                //  Create a new connected account
                $accountData = [
                    'type' => 'custom',
                    'country' => $countryCode,
                    'email' => $user->email,
                    'business_type' => 'individual',
                    'tos_acceptance' => [
                        'date' => time(),
                        'ip' => $_SERVER['REMOTE_ADDR'],
                    ],
                    'metadata' => [
                        'user_id' => $user->id,
                        'platform' => config('app.name')
                    ],
                    'settings' => [
                        'payouts' => [
                            'schedule' => [] // We'll set it below based on your logic
                        ],
                    ],
                ];
                
                
                // Canada-specific requirements
                
                if ($countryCode === 'CA') {
                    // Option 1: If only transferring funds (no card payments)
                    $accountData['capabilities'] = [
                        'transfers' => ['requested' => true],
                    ];
                    $accountData['tos_acceptance']['service_agreement'] = 'recipient'; // Required in CA
                } elseif ($countryCode === 'US') {
                    // US – must request both card_payments and transfers
                    $accountData['capabilities'] = [
                        'card_payments' => ['requested' => true],
                        'transfers'     => ['requested' => true],
                    ];
                }else {
                    // For US and others: full service agreement is default
                    $accountData['capabilities'] = [
                        'transfers' => ['requested' => true],
                    ];
                    // Do NOT include service_agreement (Stripe defaults to full)
                }

                // US-specific requirements
               
                $account = \Stripe\Account::create($accountData);
                $stripeAccountId = $account->id;
                $user->update([
                       'stripe_account_id' => $stripeAccountId,
                ]);
            } else {
                $account = Account::retrieve($stripeAccountId);
            }
            $response['account'] = $account;
            $currency = $account->default_currency ?? 'MXN';
            // Create a NumberFormatter instance with the 'currency' style
            $fmt = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY);
            $fmt->setTextAttribute(\NumberFormatter::CURRENCY_CODE, strtoupper($currency));
            // Get the symbol for the given currency code
            $response['currency']  = strtoupper($account->default_currency ?? 'MXN');

            $response['currencySymbol'] = $fmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
                // Check if onboarding is complete
            if (!empty($account->requirements->disabled_reason)) {
                $accountLink = \Stripe\AccountLink::create([
                    'account' => $stripeAccountId,
                    'refresh_url' => route('stripe.reauth',['account_id' => $account->id]),
                    'return_url' => route('stripe.return'),
                    'type' => 'account_onboarding',
                ]);
                $response['status'] = 'pending';
                $response['accountUrl'] = $accountLink->url;
            }else {
                $response['status'] = 'verified'; // <-- Set only when verified
            }
        } catch (\Exception $e) {
            return [
                'status'       => 'error',
                'message'      => $e->getMessage(),
                'accountUrl'   => null,
                'currency'     => null,
                'currencySymbol' => null,
                'account'      => null,
            ];
        }
            
        return $response;
        
    }

    public function updateSignupMethod(Request $request){
        $user = auth()->user(); 
        $validator = Validator::make($request->all(), [
            'navigation_method' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }
        $user->signup_method = $request->navigation_method;
        $user->save();
        return response()->json([
            'status' => true,
            'message' => 'Signup method updated successfully.',
            'data' => [
                'navigation_method' => $user->signup_method,
            ],
        ]);
    }

    public function updatePayoutSetting(Request $request){
        $user = auth()->user(); 
        $validator = Validator::make($request->all(), [
            'payout_schedule' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }
        $user->payout_schedule = strtolower($request->payout_schedule);
        $user->save();
        return response()->json([
            'status' => true,
            'message' => 'Updated successfully.',
            'data' => [
                'payout_schedule' => $user->payout_schedule,
            ],
        ]);
    }

    public function changePassword(Request $request){
        $user = User::where('id',Auth::user()->id)->first();

        if($user == "") {
            $message = __('message.user_not_found');
            return json_message_response($message,400);   
        }
           
        $hashedPassword = $user->password;

        $match = Hash::check($request->old_password, $hashedPassword);

        $same_exits = Hash::check($request->new_password, $hashedPassword);
        if ($match)
        {
            if($same_exits){
                $message = __('message.old_new_pass_same');
                return json_message_response($message,400);
            }

			$user->fill([
                'password' => Hash::make($request->new_password)
            ])->save();
            
            $message = __('message.password_change');
            return json_message_response($message,200);
        }
        else
        {
            $message = __('message.valid_password');
            return json_message_response($message,400);
        }
    }

    public function updateProfile(UserRequest $request)
    {   
        $user = Auth::user();
        if($request->has('id') && !empty($request->id)){
            $user = User::where('id',$request->id)->first();
        }
        if($user == null){
            return json_message_response(__('message.no_record_found'),400);
        }

        // Check if email, name, or phone number has changed
        $emailChanged = $user->email !== $request->email;
        $nameChanged = $user->display_name !== $request->first_name . ' ' . $request->last_name;
        $phoneChanged = $user->contact_number !== $request->contact_number;

        if ($emailChanged || $nameChanged || $phoneChanged) {
            // Update Stripe customer
            if ($user->stripe_customer_id) {
                $stripeResponse = updateStripeCustomer($user->stripe_customer_id, $request->email, $request->first_name . ' ' . $request->last_name, $request->contact_number);
                if (isset($stripeResponse['error'])) {
                    return json_message_response('Failed to update Stripe customer.',400);
                }
            }
        }

        $user->fill($request->all())->update();

        if($request->hasFile('profile_image')) {
            $user->clearMediaCollection('profile_image');
            $user->addMediaFromRequest('profile_image')->toMediaCollection('profile_image');
        }

        $user_data = User::find($user->id);
        
        if($user_data->userDetail != null && $request->has('user_detail') ) {
            $user_data->userDetail->fill($request->user_detail)->update();
        } else if( $request->has('user_detail') && $request->user_detail != null ) {
            $user_data->userDetail()->create($request->user_detail);
        }
        
        if($user_data->userBankAccount != null && $request->has('user_bank_account')) {
            $user_data->userBankAccount->fill($request->user_bank_account)->update();
        } else if( $request->has('user_bank_account') && $request->user_bank_account != null ) {
            $user_data->userBankAccount()->create($request->user_bank_account);
        }

        // Update or create addresses
        if ($request->has('user_address')){
            $user_addresses = json_decode($request->user_address, true);
            if (is_array($user_addresses)) {
                foreach ($user_addresses as $addressData) {
                    if (isset($addressData['id'])) {
                        // Update existing address
                        $user->userAddresses()->where('id', $addressData['id'])->update($addressData);
                    } else {
                        // Create new address
                        $user->userAddresses()->create($addressData);
                    }
                }
            }
        }
        
        $message = __('message.updated');
        // $user_data['profile_image'] = getSingleMedia($user_data,'profile_image',null);
        unset($user_data['media']);

        if( $user_data->user_type == 'driver') {
            $user_resource = new DriverResource($user_data);
        } else {
            $user_resource = new UserResource($user_data);
        }

        $response = [
            'data' => $user_resource,
            'message' => $message
        ];
        return json_custom_response( $response );
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        if($request->is('api*')){
            $clear = request('clear');
            if( $clear != null ) {
                $user->$clear = null;
            }
            $user->save();
            return json_message_response('Logout successfully');
        }
    }

    public function forgetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $response = Password::sendResetLink(
            $request->only('email')
        );

        return $response == Password::RESET_LINK_SENT
            ? response()->json(['message' => __($response), 'status' => true], 200)
            : response()->json(['message' => __($response), 'status' => false], 400);
    }
    
    public function socialLogin(Request $request)
    {
        $input = $request->all();
        Log::channel('custom_api')->info('[SOCIAL_LOGIN] API called', ['request' => $input,'line' => __LINE__]);

        try {
            if($input['login_type'] === 'mobile'){
                $user_data = User::where('username', $input['username'])->where('login_type','mobile')->first();
            } else {
                $user_data = User::where('email',$input['email'])->first();
            }
            
            if( $user_data != null ) {
                if( !in_array($user_data->user_type, ['admin',request('user_type')] )) {
                    $message = __('auth.failed');
                    Log::channel('custom_api')->info('[SOCIAL_LOGIN] Auth failed due to user type mismatch', ['email' => $user_data->email,'line' => __LINE__]);
                    return json_message_response($message,400);
                }
    
                if( $user_data->status == 'banned' ) {
                    $message = __('message.account_banned');
                    Log::channel('custom_api')->info('[SOCIAL_LOGIN] Account is banned', ['email' => $user_data->email,'line' => __LINE__]);
                    return json_message_response($message,400);
                }
            
                if( !isset($user_data->login_type) || $user_data->login_type  == '' )
                {
                    if($request->login_type === 'google')
                    {
                        $message = __('validation.unique',['attribute' => 'email' ]);
                    } else {
                        $message = __('validation.unique',['attribute' => 'username' ]);
                    }
                    Log::channel('custom_api')->info('[SOCIAL_LOGIN] Email/Username validation error', [
                        'email' => $user_data->email,
                        'message' => $message,
                        'line' => __LINE__
                    ]);
                    return json_message_response($message,400);
                }
                $message = __('message.login_success');
            } else {
    
                if($request->login_type === 'google')
                {
                    $key = 'email';
                    $value = $request->email;
                } else {
                    $key = 'username';
                    $value = $request->username;
                }
    
                if($request->login_type === 'mobile' && $user_data == null ){
                    $otp_response = [
                        'status' => true,
                        'is_user_exist' => false
                    ];
                    Log::channel('custom_api')->info('[SOCIAL_LOGIN] Mobile login, user not found', ['username' => $input['username'], 'otp_response' => $otp_response,'line' => __LINE__]);
                    return json_custom_response($otp_response);
                }
                
                $validator = Validator::make($input,[
                    'email' => 'required|email|unique:users,email',
                    'username'  => 'required|unique:users,username',
                    'contact_number' => 'max:20|unique:users,contact_number',
                ]);
    
                if ( $validator->fails() ) {
                    $data = [
                        'status' => false,
                        'message' => $validator->errors()->first(),
                        'all_message' =>  $validator->errors()
                    ];
                    Log::channel('custom_api')->info('[SOCIAL_LOGIN] Validation error during registration', ['input' => $input, 'errors' => $data,'line' => __LINE__]);
                    return json_custom_response($data, 422);
                }
    
                $password = !empty($input['accessToken']) ? $input['accessToken'] : $input['email'];
    
                $input['display_name'] = $input['first_name']." ".$input['last_name'];
                $input['password'] = Hash::make($password);
                $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'rider';
                $input['referral_code'] = generateUniqueReferralCode();

                $find_user = User::where('email',$input['email'])->first();
                if(empty($find_user)){
                    // Create a customer in Stripe using the helper function
                    $stripeCustomer = createStripeCustomer($input['email'], $input['display_name'], '', 'stripe');
                    if(isset($stripeCustomer['id'])) {
                        $input['stripe_customer_id'] = $stripeCustomer['id'];
                    } else {
                        return json_message_response('Failed to create Stripe customer.',400);
                    }
                }
                
                $user = User::create($input);
                if($user->userWallet == null) {
                    $user->userWallet()->create(['total_amount' => 0 ]);
                }
                $user->assignRole($input['user_type']);
    
                $user_data = User::where('id',$user->id)->first();
                $message = __('message.save_form',['form' => $input['user_type'] ]);
            }

            $user_data->tokens()->delete();

            if($user_data->referral_code == null){
                $referral_code = generateUniqueReferralCode();
                $user_data->referral_code = $referral_code;
                $user_data->save();
            }
    
            $user_data['api_token'] = $user_data->createToken('auth_token')->plainTextToken;
            $user_data['profile_image'] = getSingleMedia($user_data, 'profile_image', null);
    
            $is_verified_driver = false;
            if($user_data->user_type == 'driver') {
                $is_verified_driver = $user_data->is_verified_driver; // DriverDocument::verifyDriverDocument($user_data->id);
            }
            $user_data['is_verified_driver'] = (int) $is_verified_driver;
            $response = [
                'status' => true,
                'message' => $message,
                'data' => $user_data
            ];

            Log::channel('custom_api')->info('[SOCIAL_LOGIN] Login successful, response returned', [
                'email' => $user_data->email ?? null,
                'response' => $response,
                'line' => __LINE__
            ]);
            return json_custom_response($response);
        } catch (\Exception $e) {
            Log::channel('custom_api')->error('[SOCIAL_LOGIN] Exception occurred', [
                'error' => $e->getMessage(),
                'line' => __LINE__
            ]);
            $message = __('auth.failed');
            return json_message_response($message,400);
        }
    }

    public function newsocialLogin(Request $request) {
        $input = $request->all();
        $validator = Validator::make($request->all(), [
            'token' => 'required_if:login_type,apple,google',
            'email' => 'required|email',
            'user_type'  => 'required|in:driver,rider',
            'login_type' => 'required|in:google,mobile,apple',
            'unique_id' => 'required_if:login_type,apple',

        ]);
    
        if ($validator->fails()) {
            $data = [
                'status' => false,
                'message' => $validator->errors()->first(),
                'all_message' =>  $validator->errors()
            ];
            return json_custom_response($data, 422);
        }
        $client = new Client();
        $token = $request['token'];
        $email = $request['email'];
        $unique_id = $request['unique_id'];
        // $firebase_token = $request->firebase_token ?? null;

        try{
            if ($request->login_type === 'google') {
                $res = $client->request('GET', 'https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . $request->token);
                $data = json_decode($res->getBody(), true);
    
                // Extra security check
                if (!isset($data['email']) || strtolower($data['email']) !== strtolower($request->email)) {
                    return json_message_response('Invalid Google token.', 403);
                }
            } 
            elseif ($request->login_type == 'apple') {
                  $parts = explode('.', $token);
                  if (count($parts) !== 3) {
                    throw new Exception('Invalid token format');
                  }
                  $payload = $parts[1];
                  // Adjust padding for base64 decoding
                  $payload = str_replace(['-', '_'], ['+', '/'], $payload);
                  $padding = strlen($payload) % 4;
                  if ($padding > 0) {
                    $payload .= str_repeat('=', 4 - $padding);
                  }
      
                  $decodedPayload = base64_decode($payload);
                  $userData = json_decode($decodedPayload, true);
      
                  if (!is_array($userData)) {
                    throw new Exception('Invalid payload JSON');
                  }
                  $data = [
                    'sub' => $userData['sub'] ?? null,
                    'email' => $userData['email'] ?? null,
                  ];
            }
            
            $request_data = [
                'token' => $token,
                'email' => $email,
                'unique_id' => $unique_id,
                'verified' => $request['verified']??'default',
                'name'    => $request['name']??null,
                'address'   => $request['address']??null,
                'firebase_token' => $firebase_token,
                'user_type' => $request->user_type
            ];
            
            $user = User::where('email', $data['email'])->where('user_type',$request_data['user_type'])->first();
            
            if ($user && $user->status == 'banned' ) {
                $message = __('message.account_banned');
                Log::channel('custom_api')->info('[SOCIAL_LOGIN] Account is banned', ['email' => $user->email,'line' => __LINE__]);
                return json_message_response($message,400);
            }
            
            if (!$user) {
                $name_parts = explode(' ', $request_data['name'], 2);
                $firstName = $name_parts[0] ?? '';
                $lastName = $name_parts[1] ?? '';
                $user = new User();
                $user->email = $data['email'];
                $user->temp_token = $request_data['unique_id'];
                $user->first_name = $firstName;
                $user->last_name = $lastName;
                $user->user_type = $request_data['user_type'];
                $user->display_name = trim($firstName.' '.$lastName);
                // $user->fcm_token = $request_data['firebase_token'];
                $user->save();
                $user->assignRole($request_data['user_type']);
                $user->userWallet()->create(['total_amount' => 0]);
            } else {
                // $user->fcm_token = $request->firebase_token;
                $user->last_actived_at = now();
                $user->save();
            }
          
            // Generate API token
            $user->api_token = $user->createToken('auth_token')->plainTextToken;
            $user->profile_image = getSingleMedia($user, 'profile_image', null);

            return json_custom_response(['data' => $user], 200);
        } catch (\Exception $e) {
            Log::channel('custom_api')->error('[SOCIAL_LOGIN] Exception occurred', [
                'error' => $e->getMessage(),
                'line' => __LINE__
            ]);
            $message = __('auth.failed');
            return json_message_response($message,400);
        }
    }

    public function updateUserStatus(Request $request)
    {
        $user_id = $request->id ?? auth()->user()->id;
        
        $user = User::where('id',$user_id)->first();

        if($user == "") {
            $message = __('message.user_not_found');
            return json_message_response($message,400);
        }
        if($request->has('status')) {
            $user->status = $request->status;
        }
        if($request->has('is_online')) {
            if ($request->is_online == 1) {
                if ($user->status == 'banned') {
                    $message = __('message.account_banned');
                    return json_message_response($message,400);
                }
                if ($user->is_verified_driver != 1 || $user->status != 'active') {
                    $hasExpiredDoc = $user->hasExpiredDocuments();
                    $response = [
                        'data' => [
                            'status' => false,
                            'step' => 'documents'
                        ],
                        'message' => $hasExpiredDoc ? __('message.doc_expired') : __('message.driver_doc_pending'),
                    ];
                    return json_custom_response($response,400);
                }
            }
            $user->is_online = $request->is_online;
        }
        // if($request->has('is_available')) {
        //     $user->is_available = $request->is_available;
        // }
        if($request->has('latitude')) {
            $user->latitude = $request->latitude;
        }
        if($request->has('longitude')) {
            $user->longitude = $request->longitude;
        }
        if($request->has('latitude') && $request->has('longitude') ) {
            $user->last_location_update_at = date('Y-m-d H:i:s');
        }
        if($request->has('player_id')) {
            $user->player_id = $request->player_id;
        }
        if($request->has('app_version')) {
            $user->app_version = $request->app_version;
        }

        if($request->has('otp_verify_at')) {
            $user->otp_verify_at = $request->otp_verify_at;
        }

        if($request->has('fcm_token')) {
            $user->fcm_token = $request->fcm_token;
        }
        
        if($request->is_online == 1) {
            $user->is_available = 1;
        }
        $user->save();
        /*
        if( $user->user_type == 'driver') {
            $user_resource = new DriverResource($user);
        } else {
            $user_resource = new UserResource($user);
        }*/
        $user_resource = null;
        $message = __('message.update_form',['form' => __('message.status') ]);
        $response = [
            'data' => $user_resource,
            'message' => $message
        ];
        return json_custom_response($response);
    }

    public function updateAppSetting(Request $request)
    {
        $data = $request->all();
        AppSetting::updateOrCreate(['id' => $request->id],$data);
        $message = __('message.save_form',['form' => __('message.app_setting') ]);
        $response = [
            'data' => AppSetting::first(),
            'message' => $message
        ];
        return json_custom_response($response);
    }

    public function getAppSetting(Request $request)
    {
        if($request->has('id') && isset($request->id)){
            $data = AppSetting::where('id',$request->id)->first();
        } else {
            $data = AppSetting::first();
        }

        return json_custom_response($data);
    }

    public function deleteUserAccount(Request $request)
    {
        $id = auth()->id();
        $user = User::where('id', $id)->first();
        $message = __('message.not_found_entry',['name' => __('message.account') ]);

        if( $user != '' ) {
            try {
                $auth = app('firebase.auth');
                $firebaseUser = $auth->getUserByEmail($user->email);

                // Delete Firebase user
                $auth->deleteUser($firebaseUser->uid);

            } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
                \Log::error("Firebase user not found for email: " . $user->email);
            } catch (\Exception $e) {
                \Log::error("Error deleting Firebase user: " . $e->getMessage());
            }
            // Delete Stripe customer if exists

            try {
                if (!empty($user->stripe_customer_id)) {
                    $secretKey = getStripeSecretKey();
                    Stripe::setApiKey($secretKey);
                    $stripeCustomer = Customer::retrieve($user->stripe_customer_id);
                    if ($stripeCustomer) {
                        $stripeCustomer->delete();
                    }
                }
            } catch (\Exception $e) {
                // optional: log or return error response
                return json_custom_response([
                    'status' => false,
                    'message' => 'Stripe customer creation failed: ' . $e->getMessage(),
                ], 500);
            }
            $domain = parse_url(config('app.url'), PHP_URL_HOST);
            $user->update([
                'email' => $user->id . '@' . $domain,
                'username' => 'user' . $user->id,
                'contact_number' => '900000000' . $user->id
            ]);
            $user->delete();
            $message = __('message.account_deleted');
        }
        
        return json_custom_response(['message'=> $message, 'status' => true]);
    }

    public function validateDriverStepOne(DriverStepOneRequest $request)
    {
        return json_custom_response(['status' => true]);
    }
}
