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
            'amount' => 'required|numeric|min:1',
            'mobileNo' => 'required|string|max:15',
            'customerName' => 'required|string|max:255',
            'email' => 'required|email|max:255',
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

        $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

        try {
            // Create Razorpay Plan only if it's EMI (installments)
            $razorpayPlanId = null;
            $subscriptionId = null;

            if ($request->collectionType === 'installments') {
                // Calculate per installment amount
                $perInstallmentAmount = round($request->amount / $request->installments * 100);

                // Create Plan
                $plan = $api->plan->create([
                    'period' => $request->frequency,
                    'interval' => 1,
                    'item' => [
                        'name' => 'EMI Plan for ' . $request->customerName,
                        'amount' => $perInstallmentAmount,
                        'currency' => 'INR',
                        'description' => $request->billDetails
                    ],
                    'notes' => [
                        'created_for' => 'Emandate EMI'
                    ]
                ]);
                $razorpayPlanId = $plan['id'];

                // Create Subscription
                $subscription = $api->subscription->create([
                    'plan_id' => $razorpayPlanId,
                    'total_count' => $request->installments,
                    'customer_notify' => 1,
                    'notes' => [
                        'customer_name' => $request->customerName,
                        'purpose' => $request->billDetails
                    ]
                ]);
                $subscriptionId = $subscription['id'];
            }

            // For one-time payment, create payment link
            $paymentLink = null;
            if ($request->collectionType === 'oneTime') {
                $paymentLink = $api->paymentLink->create([
                    'amount' => $request->amount * 100,
                    'currency' => 'INR',
                    'description' => $request->billDetails,
                    'customer' => [
                        'name' => $request->customerName,
                        'email' => $request->email,
                        'contact' => $request->mobileNo
                    ],
                    'notify' => [
                        'sms' => true,
                        'email' => true
                    ],
                    'reminder_enable' => true,
                    'callback_url' => env('APP_URL').'/payment/callback',
                    'callback_method' => 'get'
                ]);
            }

            // Store in DB
            $emandateId = DB::table('emandates')->insertGetId([
                'amount' => $request->amount,
                'mobile_no' => $request->mobileNo,
                'customer_name' => $request->customerName,
                'email' => $request->email,
                'bill_details' => $request->billDetails,
                'collection_type' => $request->collectionType,
                'frequency' => $request->collectionType === 'installments' ? $request->frequency : null,
                'installments' => $request->collectionType === 'installments' ? $request->installments : null,
                'start_date' => $request->startDate,
                'payment_method' => $request->paymentMethod,
                'razorpay_plan_id' => $razorpayPlanId,
                'razorpay_subscription_id' => $subscriptionId,
                'razorpay_payment_link_id' => $paymentLink ? $paymentLink['id'] : null,
                'razorpay_payment_link_url' => $paymentLink ? $paymentLink['short_url'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'eMandate created successfully.',
                'emandate_id' => $emandateId,
                'plan_id' => $razorpayPlanId,
                'subscription_id' => $subscriptionId,
                'payment_link' => $paymentLink ? $paymentLink['short_url'] : ($subscriptionId ? 'https://dashboard.razorpay.com/app/subscriptions/'.$subscriptionId : null)
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
