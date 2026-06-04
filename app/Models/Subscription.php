<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory , SoftDeletes;
    protected $fillable = [ 'name', 'price', 'interval', 'stripe_price_id', 'stripe_product_id','currency','description' ];

}
