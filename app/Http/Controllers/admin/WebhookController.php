<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $event = $request->input('event');

        if ($event === 'subscription.activated') {
            $subscriptionId = $request->input('payload.subscription.entity.id');

            DB::table('emandates')
              ->where('razorpay_subscription_id', $subscriptionId)
              ->update(['is_authorized' => true]);
        }

        return response()->json(['status' => 'ok']);
    }
}