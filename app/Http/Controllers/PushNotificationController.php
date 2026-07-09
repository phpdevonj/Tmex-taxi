<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PushNotification;
use App\DataTables\PushNotificationDataTable;
use App\Models\User;
use App\Models\Service;
use App\Models\Region;
use App\Notifications\CommonNotification;
use App\Notifications\RideNotification;
use App\Models\Notification;

class PushNotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(PushNotificationDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title',['form' => __('message.pushnotification')] );
        $auth_user = authSession();
        $assets = ['datatable'];
        $button = $auth_user->can('pushnotification add') ? '<a href="'.route('pushnotification.create').'" class="float-right btn btn-md border-radius-10 btn-outline-dark"><i class="fa fa-plus-circle"></i> '.__('message.add_form_title',['form' => __('message.pushnotification')]).'</a>' : '';
        return $dataTable->render('global.datatable', compact('pageTitle','button','auth_user'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $pageTitle = __('message.add_form_title',[ 'form' => __('message.pushnotification')]);
        $relation = [
            'rider' => User::where('user_type','rider')->where('status','active')->get()->pluck('display_name', 'id'),
            'driver' => User::where('user_type','driver')->where('status','active')->get()->pluck('display_name', 'id'),
            'service' => Service::where('status',1)->pluck('name', 'id'),
            'region' => Region::where('status',1)->pluck('name', 'id'),
        ];

        return view('push_notification.form', compact('pageTitle')+$relation);
    }

    /**
     * Return matching riders/drivers for a chosen Service or Region as JSON.
     * Used by the push notification form to pre-select recipients.
     *
     * Service  -> drivers only (matched by their service_id).
     * Region   -> both riders and drivers, matched by their stored
     *             latitude/longitude falling inside the region polygon.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers(Request $request)
    {
        $riders = collect();
        $drivers = collect();

        if ($request->filled('service_id')) {
            $drivers = User::where('user_type', 'driver')
                ->where('status', 'active')
                ->where('service_id', $request->service_id)
                ->get(['id', 'display_name']);
        } elseif ($request->filled('region_id')) {
            $regionId = $request->region_id;

            // Restrict to users whose coordinates fall inside the region polygon.
            // The stored polygon uses (longitude latitude) axis order (Grimzy spatial),
            // so the tested point must be built in the same order.
            $inRegion = function ($query) use ($regionId) {
                $query->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->where('latitude', '!=', '')
                    ->where('longitude', '!=', '')
                    ->whereRaw(
                        "ST_Contains((SELECT coordinates FROM regions WHERE id = ?), ST_GeomFromText(CONCAT('POINT(', longitude, ' ', latitude, ')')))",
                        [$regionId]
                    );
            };

            $riders = User::where('user_type', 'rider')
                ->where('status', 'active')
                ->where($inRegion)
                ->get(['id', 'display_name']);

            $drivers = User::where('user_type', 'driver')
                ->where('status', 'active')
                ->where($inRegion)
                ->get(['id', 'display_name']);
        }

        return response()->json([
            'riders'  => $riders->map(fn ($user) => ['id' => $user->id, 'text' => $user->display_name])->values(),
            'drivers' => $drivers->map(fn ($user) => ['id' => $user->id, 'text' => $user->display_name])->values(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $pushnotification = PushNotification::create($request->all());

        uploadMediaFile($pushnotification, $request->notification_image, 'notification_image');
        
        $notification_data = [
            'id' => $pushnotification->id,
            'push_notification_id' => $pushnotification->id,
            'type' => 'push_notification',
            'subject' => $pushnotification->title,
            'message' => $pushnotification->message,
        ];
        if( getMediaFileExit($pushnotification, 'notification_image') ) {
            $notification_data['image'] = getSingleMedia($pushnotification, 'notification_image');
        } else {
            $notification_data['image'] = null;
        }

        if ($request->has('driver')) {
            $pushnotification->update(['for_driver' => 1]);

            User::whereIn('id', $request->driver)->chunk(20, function ($driverdata) use ($notification_data) {
                foreach ($driverdata as $user) {
                    $user->notify(new CommonNotification($notification_data['type'], $notification_data));
                    $user->notify(new RideNotification($notification_data));
                }
            });
        }

        if ($request->has('rider')) {
            $pushnotification->update(['for_rider' => 1]);

            User::whereIn('id', $request->rider)->chunk(20, function ($riderdata) use ($notification_data) {
                foreach ($riderdata as $user) {
                    $user->notify(new CommonNotification($notification_data['type'], $notification_data));
                    $user->notify(new RideNotification($notification_data));
                }
            });
        }
        
        return redirect()->route('pushnotification.index')->withSuccess(__('message.save_form', ['form' => __('message.pushnotification')]));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $pageTitle = __('message.update_form_title',[ 'form' => __('message.pushnotification')]);
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
            return redirect()->route('pushnotification.index')->withErrors($message);
        }
        $pushnotification = PushNotification::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.pushnotification')]);

        if($pushnotification != '') {
            // $search = "push_notification_id".'":'.$id;
            // Notification::where('data','like',"%{$search}%")->delete();
            Notification::whereJsonContains('data->push_notification_id',$id);
            $pushnotification->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.pushnotification')]);
        }

        if(request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message ]);
        }

        return redirect()->back()->with($status, $message);
    }
}
