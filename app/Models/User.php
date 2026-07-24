<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, InteractsWithMedia, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'stripe_customer_id','stripe_account_id','first_name', 'last_name', 'email', 'password', 'username','country_code', 'contact_number', 'gender', 'email_verified_at', 'address', 'user_type', 'player_id', 'fcm_token', 'fleet_id', 'latitude', 'longitude', 'last_notification_seen', 'status', 'is_online', 'is_available', 'uid', 'login_type', 'display_name', 'timezone', 'service_id', 'is_verified_driver', 'last_location_update_at', 'otp_verify_at','last_actived_at','app_version', 'referral_code', 'referred_by', 'device_id', 'device_type'
    ];
    protected $dates = ['deleted_at'];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'ssn',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_available'  => 'integer',
        'service_id'        => 'integer',
        'fleet_id'          => 'integer',
        'is_verified_driver'=> 'integer',
        'is_online'         => 'integer',
        'last_location_update_at'   => 'datetime',
        'otp_verify_at'     => 'datetime',
        'ssn'               => 'encrypted',
    ];

    /**
     * Masked SSN for safe display to driver/admin, e.g. "*--1234".
     */
    public function getMaskedSsnAttribute() {
        return $this->ssn_last_four ? '*--' . $this->ssn_last_four : null;
    }

    public function userDetail() {
        return $this->hasOne(UserDetail::class, 'user_id', 'id');
    }

    public function userBankAccount() {
        return $this->hasOne(UserBankAccount::class, 'user_id', 'id');
    }

    public function fleet() {
        return $this->belongsTo(User::class, 'fleet_id', 'id');
    }

    public function userWallet() {
        return $this->hasOne(Wallet::class, 'user_id', 'id');
    }

    public function userPoint(){
        return $this->hasOne(Point::class, 'user_id', 'id');
    }

    public function userAddresses()
    {
        return $this->hasMany(UserAddress::class, 'user_id', 'id');
    }

    public function scopeAdmin($query) {
        return $query->where('user_type', 'admin')->first();
    }

    public function scopeGetUser($query, $user_type=null)
    {
        $auth_user = auth()->user();

        if( $auth_user->hasAnyRole(getActiveAdminsRoles()) ) {
            $query->where('user_type', $user_type)->where('status','active');
            return $query;
        }
        if( $auth_user->hasRole('fleet') ) {
            return $query->where('user_type', 'driver')->where('fleet_id', $auth_user->id);
        }
    }

    public function riderRideRequestDetail() {
        return $this->hasMany(RideRequest::class, 'rider_id', 'id');
    }

    public function driverRideRequestDetail() {
        return $this->hasMany(RideRequest::class, 'driver_id', 'id');
    }

    public function driverDocument(){
        return $this->hasMany(DriverDocument::class, 'driver_id', 'id');
    }

    public function service() {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function riderRating(){
        return $this->hasMany(RideRequestRating::class, 'rider_id', 'id');
    }

    public function driverRating(){
        return $this->hasMany(RideRequestRating::class, 'driver_id', 'id');
    }

    public function routeNotificationForOneSignal()
    {
        return $this->player_id;
    }

    public function routeNotificationForFcm($notification)
    {
        return $this->fcm_token;
    }

    public function userWithdraw(){
        return $this->hasMany(WithdrawRequest::class, 'user_id', 'id');
    }

    public function bids()
    {
        return $this->hasMany(RideRequestBid::class, 'driver_id');
    }
    protected static function boot(){
        parent::boot();
        static::deleted(function ($row) {
            $row->userDetail()->delete();
            $row->userWithdraw()->delete();
            $row->userWallet()->delete();
            switch ($row->user_type) {
                case 'rider':
                    $row->riderRideRequestDetail()->delete();
                    break;
                case 'driver':
                    $row->userBankAccount()->delete();
                    $row->driverDocument()->delete();
                    $row->driverRideRequestDetail()->delete();
                    break;
                default:
                    # code...
                    break;
            }
        });
    }

    public function getPayment(){
        
        return $this->hasManyThrough( 
            Payment::class,
            RideRequest::class,
            'driver_id',
            'ride_request_id',
            'id',
            'id'
        )->where('payment_status','paid');
    }

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }
    
    public function getReferralCount($status = 'complete')
    {
        return $this->referrals()
            ->when($status, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->count();
    }

    public function driverSubscriptions() {
        return $this->hasMany(DriverSubscription::class, 'driver_id');
    }
    public static function scopeDriverBaseQuery($query)
    {
        $query = $query->where('user_type', 'driver');
        return $query;
    }
    
    public function hasExpiredDocuments()
    {
        return $this->driverDocument()
            ->where('is_verified', 3)
            ->exists();
    }

    public function scopeWithExpiredDocument($query)
    {
        return $query->whereHas('driverDocument', function ($q) {
            $q->where('is_verified', 3);
        });
    }
}
