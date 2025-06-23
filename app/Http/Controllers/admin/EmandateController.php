<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;
use Illuminate\Support\Str;


class EmandateController extends Controller
{



    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'required|numeric|exists:customers,id',
            'amount' => 'required|numeric|min:1',
            'billDetails' => 'required|string|max:255',
            'collectionType' => 'required|in:installments,oneTime',
            'frequency' => 'required_if:collectionType,installments|in:daily,weekly,monthly,yearly|nullable',
            'installments' => 'required_if:collectionType,installments|integer|min:1|max:12|nullable',
            'startDate' => 'required|date',
            'paymentMethod' => 'required|in:upi,nach',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = DB::table('customers')
                ->select('id', 'name', 'email', 'phone', 'razorpay_customer_id')
                ->where('id', $request->userId)
                ->first();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found.'], 404);
            }

            $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

            // ✅ Create Razorpay Customer if not exists
            if (!$user->razorpay_customer_id) {
                $razorpayCustomer = $api->customer->create([
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact' => $user->phone,
                    'fail_existing' => 0
                ]);
                $razorpayCustomerId = $razorpayCustomer['id'];

                DB::table('customers')->where('id', $user->id)->update([
                    'razorpay_customer_id' => $razorpayCustomerId
                ]);
            } else {
                $razorpayCustomerId = $user->razorpay_customer_id;
            }

            $razorpayPlanId = null;
            $subscriptionId = null;
            $paymentLink = null;
            $subscriptionShortUrl = null;
            $upiQrImageUrl = null;

            // ✅ INSTALLMENTS (EMANDATE) FLOW
            if ($request->collectionType === 'installments') {
                $perInstallmentAmount = round($request->amount / $request->installments * 100); // in paise

                $plan = $api->plan->create([
                    'period' => $request->frequency,
                    'interval' => 1,
                    'item' => [
                        'name' => 'EMI Plan for ' . $user->name,
                        'amount' => $perInstallmentAmount,
                        'currency' => 'INR',
                        'description' => $request->billDetails,
                    ],
                    'notes' => [
                        'created_for' => 'eMandate EMI',
                        'user_id' => $user->id
                    ]
                ]);

                $razorpayPlanId = $plan['id'];

                $subscriptionParams = [
                    'plan_id' => $razorpayPlanId,
                    'customer_notify' => 1,
                    'total_count' => $request->installments,
                    'start_at' => strtotime($request->startDate),
                    'customer_id' => $razorpayCustomerId,
                    'auth_type' => $request->paymentMethod, // ✅ Always include auth_type
                    'notes' => [
                        'customer_name' => $user->name,
                        'purpose' => $request->billDetails
                    ]
                ];
                if ($request->collectionType === 'installments' && in_array($request->paymentMethod, ['nach', 'upi'])) {
    $subscriptionParams['auth_type'] = $request->paymentMethod;
}

                $subscription = $api->subscription->create($subscriptionParams);
                $subscriptionId = $subscription['id'];
                $subscriptionShortUrl = $subscription['short_url'] ?? null;

                // ✅ UPI QR image
                if ($request->paymentMethod === 'upi' && isset($subscription['upi_qr']['image_url'])) {
                    $upiQrImageUrl = $subscription['upi_qr']['image_url'];
                }
            }

            // ✅ ONETIME PAYMENT FLOW
            if ($request->collectionType === 'oneTime') {
                $paymentLink = $api->paymentLink->create([
                    'amount' => $request->amount * 100,
                    'currency' => 'INR',
                    'description' => $request->billDetails,
                    'customer' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'contact' => $user->phone
                    ],
                    'notify' => ['sms' => true, 'email' => true],
                    'reminder_enable' => true,
                    'callback_url' => env('APP_URL') . '/payment/callback',
                    'callback_method' => 'get'
                ]);
            }

            // ✅ Save all in DB
            $emandateId = DB::table('emandates')->insertGetId([
                'user_id' => $user->id,
                'amount' => $request->amount,
                'bill_details' => $request->billDetails,
                'collection_type' => $request->collectionType,
                'frequency' => $request->collectionType === 'installments' ? $request->frequency : null,
                'installments' => $request->collectionType === 'installments' ? $request->installments : null,
                'start_date' => $request->startDate,
                'payment_method' => $request->paymentMethod,
                'razorpay_plan_id' => $razorpayPlanId,
                'razorpay_subscription_id' => $subscriptionId,
                'razorpay_payment_link_id' => $paymentLink['id'] ?? null,
                'razorpay_payment_link_url' => $paymentLink['short_url'] ?? $subscriptionShortUrl,
                'upi_qr_image_url' => $upiQrImageUrl,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ✅ Final Response
            return response()->json([
                'success' => true,
                'message' => 'eMandate created successfully.',
                'emandate_id' => $emandateId,
                'plan_id' => $razorpayPlanId,
                'subscription_id' => $subscriptionId,
                'payment_link' => $paymentLink['short_url'] ?? $subscriptionShortUrl,
                'upi_qr_image' => $upiQrImageUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }




}
