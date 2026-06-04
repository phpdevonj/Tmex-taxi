<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverSubscription extends Model
{
    use HasFactory;
    protected $table = 'driver_subscriptions';

    protected $fillable = [
        'driver_id',
        'subscription_id',
        'stripe_subscription_id',
        'status',
    ];
    
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
