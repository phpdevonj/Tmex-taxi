<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RideRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $pdfUrl = null;
        if($this->status == 'completed' ){
            $pdfUrl = route('ride-invoice', ['id' => $this->id]);
        }
        $surge_price = getSurgePrice($this->datetime);

        $getBidAmount = $this->approvedBids()->first();

        // Admin commission is resolved the same way as the admin panel (see
        // RideRequestController@show / riderequest.show blade): once the ride is
        // settled, PaymentTrait has already stored the calculated commission on the
        // payment, so that value wins. Until then it is derived from the service,
        // honouring its commission_type (fixed amount vs percentage of the ride
        // amount, excluding extra charges). Note the payments column defaults to 0,
        // so an unsettled payment row is treated as "not calculated yet" and falls
        // through to the service. Driver earning is the ride total minus the company
        // fee (admin commission). expenses_charge is currently not tracked, so it is
        // treated as 0.
        $payment_commission = optional($this->payment)->admin_commission;
        $service_commission = (float) (optional($this->service)->admin_commission ?? 0);
        $commission_type = optional($this->service)->commission_type;
        $ride_request_amount = (float) $this->total_amount - (float) $this->extra_charges_amount;

        if ($payment_commission !== null && (float) $payment_commission > 0) {
            $admin_commission = (float) $payment_commission;
            $commission_source = 'payment';
        } elseif ($commission_type == 'percentage') {
            $admin_commission = $service_commission ? ($ride_request_amount / 100) * $service_commission : 0;
            $commission_source = 'service:percentage';
        } else {
            $admin_commission = $service_commission;
            $commission_source = 'service:' . ($commission_type ?: 'fixed(default)');
        }

        $admin_commission = (float) $admin_commission;
        $driver_earning = (float) $this->total_amount - $admin_commission;

        if (config('app.log_admin_commission')) {
            \Log::channel('admin_commission')->debug('RideRequestResource admin_commission resolved', [
                'ride_request_id'      => $this->id,
                'status'               => $this->status,
                'source'               => $commission_source,
                'service_id'           => $this->service_id,
                'service_loaded'       => $this->service !== null,
                'commission_type'      => $commission_type,
                'service_commission'   => $service_commission,
                'payment_id'           => optional($this->payment)->id,
                'payment_commission'   => $payment_commission,
                'total_amount'         => $this->total_amount,
                'extra_charges_amount' => $this->extra_charges_amount,
                'ride_request_amount'  => $ride_request_amount,
                'admin_commission'     => $admin_commission,
                'driver_earning'       => $driver_earning,
            ]);
        }

        $driver_ratings = optional($this->driver)->driverRating ?? collect();
        $rider_ratings = optional($this->rider)->riderRating ?? collect();

        $place_details = og_get_distance_matrix(
            $this->start_latitude, 
            $this->start_longitude, 
            $this->end_latitude, 
            $this->end_longitude
        );

        $dropoff_distance_in_meters = distance_value_from_distance_matrix($place_details);
        $dropoff_distance_in_km = round($dropoff_distance_in_meters/1000,2);
        $dropoff_distance_in_miles = round(km_to_mile($dropoff_distance_in_meters/1000), 2);

        return [
            'id'                => $this->id,
            'rider_id'          => $this->rider_id,
            'service_id'        => $this->service_id,
            'datetime'          => $this->datetime,
            'is_schedule'       => $this->is_schedule,
            'ride_attempt'      => $this->ride_attempt,
            'otp'               => $this->otp,
            'total_amount'      => (float) number_format($this->total_amount, 2,'.',''),
            'admin_commission'  => $admin_commission,
            'driver_earning'    => $driver_earning,
            'subtotal'          => (!empty($getBidAmount) && $this->ride_has_bid == 1) ? $getBidAmount->bid_amount : $this->subtotal,
            'extra_charges_amount'  => $this->extra_charges_amount,
            'driver_id'         => $this->driver_id,
            'driver_name'       => optional($this->driver)->display_name,
            'rider_name'        => optional($this->rider)->display_name,
            'driver_email'       => optional($this->driver)->email,
            'rider_email'        => optional($this->rider)->email,
            'driver_contact_number' => optional($this->driver)->contact_number,
            'rider_contact_number'  => optional($this->rider)->contact_number,
            'driver_profile_image' => getSingleMedia(optional($this->driver), 'profile_image',null),
            'rider_profile_image' => getSingleMedia(optional($this->rider), 'profile_image',null),
            'start_latitude'    => $this->start_latitude,
            'start_longitude'   => $this->start_longitude,
            'start_address'     => $this->start_address,
            'end_latitude'      => $this->end_latitude,
            'end_longitude'     => $this->end_longitude,
            'end_address'       => $this->end_address,
            'distance_unit'     => $this->distance_unit,
            'dropoff_distance_in_miles'     => $dropoff_distance_in_miles ?? 0.00,
            'dropoff_distance_in_km'     => $dropoff_distance_in_km ?? 0.00,
            'start_time'        => $this->rideRequestStartTime() ?? null,
            'end_time'          => $this->rideRequestCompletedTime() ?? null,
            'riderequest_in_driver_id' => $this->riderequest_in_driver_id,
            'distance'          => $this->distance,
            'duration'          => $this->duration,
            'seat_count'        => $this->seat_count,
            'reason'            => $this->reason,
            'status'            => $this->status,
            'tips'              => $this->tips,
            'base_fare'         => $this->base_fare,
            'minimum_fare'      => $this->minimum_fare,
            'per_distance'      => $this->per_distance,
            'per_distance_charge' => $this->per_distance_charge,
            'per_minute_drive'  => $this->per_minute_drive,
            'per_minute_drive_charge' => $this->per_minute_drive_charge,
            'per_minute_waiting'=> $this->per_minute_waiting,
            'waiting_time'      => $this->waiting_time,
            'waiting_time_limit'    => $this->waiting_time_limit,
            'per_minute_waiting_charge'  => $this->per_minute_waiting_charge,
            'cancelation_charges'   => $this->cancelation_charges,
            'cancel_by'         => $this->cancel_by,
            'payment_id'        => optional($this->payment)->id,
            'payment_type'      => $this->payment_type,
            'payment_status'    => optional($this->payment)->payment_status ?? 'pending',
            'extra_charges'     => $this->extra_charges,
            'surge_price_type'  => $this->surge_type ?? '',
            'surge_price_value' => (float) number_format( (float) $this->surge_value, 2,'.','') ?? 0,
            'fixed_charge'      => (float) number_format( (float) $this->surge_amount, 2,'.','') ?? 0,
            'coupon_discount'   => $this->coupon_discount,
            'coupon_code'       => $this->coupon_code,
            'coupon_data'       => $this->coupon_data,
            'is_rider_rated'    => $this->is_rider_rated,
            'is_driver_rated'   => $this->is_driver_rated,
            'max_time_for_find_driver_for_ride_request' => $this->max_time_for_find_driver_for_ride_request,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'region_id'         => optional($this->service)->region_id,
            'is_ride_for_other' => $this->is_ride_for_other,
            'other_rider_data'  => $this->other_rider_data ?? null,
            'drop_location'     => $this->drop_location,
            'multi_drop_location'     => json_decode($this->multi_drop_location),
            'invoice_url' => $pdfUrl,
            'invoice_name' => 'Ride_' . $this->id,
            'driver_rating' => $driver_ratings->count() > 0 ? (float) number_format(max($driver_ratings->avg('rating'), 0), 2) : 0,
            'rider_rating'  => $rider_ratings->count() > 0 ? (float) number_format(max($rider_ratings->avg('rating'), 0), 2) : 0,
            'service_cancellation_fee' => (float) number_format( (float) optional($this->service)->cancellation_fee, 2,'.',''),
            'held_payment_intent_id'     => $this->held_payment_intent_id,
            'held_payment_amount'        => $this->held_payment_amount,
            'captured_payment_intent_id' => $this->captured_payment_intent_id,
            
        ];
    }
}