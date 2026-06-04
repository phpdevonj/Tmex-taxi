<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Service;
use App\Models\Document;
use App\Models\DriverDocument;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Account;
use GuzzleHttp\Client;
use Exception;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DriverAuthController extends Controller
{
    /**
     * Show driver registration form (Blade page).
     */
    public function showRegistrationForm()
    {
        $services = Service::where('status', 1)->get(['id', 'name']);
        $documents = Document::where('status', 1)->get(['id', 'name']);
        $assets = ['phone'];
        return view('frontend-website.driver.register', compact('services', 'documents','assets'));
    }

    public function register(Request $request)
    {
        // Validation rules in separate array for better readability
        $validationRules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',            
            'password' => 'required|string|min:8|confirmed',
            'email' => 'required|string|email|max:255|unique:users',
            'contact_number' => 'required|max:20|unique:users,contact_number',
            'service_id' => 'required|exists:services,id',
            'car_model'  => 'required|string|max:255',
            'car_color'  => 'required|string|max:255', 
            'car_plate'  => 'required|string|max:255|unique:user_details,car_plate_number',
            'car_year'  => 'required|digits:4|integer|min:1900|max:' . date('Y'),
            'documents'  => 'required|array',
            'documents.*.document_id' => 'required|exists:documents,id',
            'documents.*.file' => 'required|file|image|mimes:jpeg,png,jpg,gif,svg|max:20480',
        ];

        $validationMessages = [
            'documents.*.file.required' => 'Please upload all required documents.',
            'documents.*.file.image'    => 'Each document must be an image file.',
            'documents.*.file.max' => 'The document must not be larger than 20MB.',
        ];

        $request->validate($validationRules, $validationMessages);
        DB::beginTransaction();
        $uid = null; // track firebase UID for cleanup if needed
        try {

            $plainPassword = $request->password;

            // Create Firebase user
            $auth = app('firebase.auth');
            $firebaseUser = $auth->createUser([
                'email' => $request->email,
                'password' => $plainPassword,
            ]); 

            $uid = $firebaseUser->uid;

            // Store data in Firestore
            $firestore = app('firebase.firestore');
            $collection = $firestore->database()->collection('users');
            $document = $collection->document($uid)->set([
                "contact_number" => $request->contact_number,
                "created_at" => Carbon::now()->format('Y-m-d H:i:s.u'),
                "display_name" => $request->first_name . ' ' . $request->last_name,
                "email" => $request->email,
                "first_name" => $request->first_name,
                "last_name" => $request->last_name,
                "player_id" => null,
                "uid" => $uid,
                "updated_at" => Carbon::now()->format('Y-m-d H:i:s.u'),
                "user_type" => 'driver',
                "username" => $request->username ?? stristr($request->email, "@", true) . rand(100,1000),
            ]);

            // Create user
            $user = User::create([
                'status' => 'pending',
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'username' => $request->username ?? stristr($request->email, "@", true) . rand(100,1000),
                'password' => Hash::make($request->password),
                'contact_number' => $request->contact_number,
                'gender' => $request->gender ?? 'male',
                'service_id' => $request->service_id,
                'display_name' => $request->first_name . ' ' . $request->last_name,
                'user_type' => 'driver',
                'uid' => $uid
            ]);

            $user->assignRole('driver');

            // Upload profile image if provided
            if ($request->hasFile('profile_image')) {
                uploadMediaFile($user, $request->file('profile_image'), 'profile_image');
            }

            // Save Driver details
            $user->userDetail()->create([
                "car_model" => $request->car_model,
                "car_color" => $request->car_color,
                "car_plate_number" => $request->car_plate,
                "car_production_year" => $request->car_year,
            ]);

            // Create wallet
            $user->userWallet()->create(['total_amount' => 0]);

            // Save Driver documents
            if ($request->has('documents')) {
                foreach ($request->documents as $doc) {
                    $driverDocument = DriverDocument::create([
                        'driver_id' => $user->id,
                        'document_id' => $doc['document_id'],
                        'expire_date' => $request->expire_date ? date('Y-m-d', strtotime($request->expire_date)) : null,
                        'is_verified' => $request->is_verified ?? 0
                    ]);

                    if (isset($doc['file']) && $doc['file'] instanceof \Illuminate\Http\UploadedFile) {
                        uploadMediaFile($driverDocument, $doc['file'], 'driver_document');
                    }
                }
            }
             // Step 3: Stripe integration
            
            $secretKey = getStripeSecretKey();
            Stripe::setApiKey($secretKey);
            // Create Stripe Customer
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
            }else {
                // For US and others: full service agreement is default
                $commonData['capabilities'] = [
                    'transfers' => ['requested' => true],
                ];
                // Do NOT include service_agreement (Stripe defaults to full)
            }
            
            $account = Account::create($commonData);
            $user->update([
                'stripe_customer_id' => $customer->id,
                'stripe_account_id'  => $account->id,
            ]);
            DB::commit();

            return redirect()
                ->route('driver.register.form')
                ->with('success', 'Registration successful. Your documents are under review. You will be able to log in through the mobile app once approved.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Driver registration failed: ' . $e->getMessage());

            try {
                // ✅ Cleanup Firebase if created
                if ($uid) {
                    $auth = app('firebase.auth');
                    $auth->deleteUser($uid); // delete from Firebase Auth
    
                    $firestore = app('firebase.firestore');
                    $firestore->database()->collection('users')->document($uid)->delete(); // delete from Firestore
                }
            } catch (\Exception $cleanupError) {
                Log::error('Firebase cleanup failed: ' . $cleanupError->getMessage());
            }
            
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Registration failed. Please try again.');
        }    
    }
}
