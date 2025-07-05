<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Razorpay\Api\Api;


class UserController extends Controller
{
    private $keyId;
    private $keySecret;
    private $cacheDuration = 5; // Minutes

    public function __construct()
    {
        $this->keyId = config('services.razorpay.key');
        $this->keySecret = config('services.razorpay.secret');
    }


    public function createSubscriptionPayment(Request $request, $subscription_id)
    {
        try {
            $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

            // 1. Fetch all invoices for the subscription
            $invoices = $api->invoice->all([
                'subscription_id' => $subscription_id
            ]);

            // 2. Find the latest unpaid (issued) invoice
            $latestInvoice = null;
            foreach ($invoices->items as $invoice) {
                if ($invoice->status == 'issued') {
                    $latestInvoice = $invoice;
                    break;
                }
            }

            if (!$latestInvoice) {
                return response()->json(['error' => 'No pending invoice found for this subscription.'], 404);
            }

            // 3. Create a Razorpay Order for this specific invoice
            // This links the payment to the invoice and subscription
            $order = $api->order->create([
                'amount'   => $latestInvoice->amount,
                'currency' => $latestInvoice->currency,
                'receipt'  => $latestInvoice->receipt,
                'payment'  => [
                    'capture' => 'automatic',
                    'capture_options' => [
                        'refund_speed' => 'normal'
                    ]
                ]
            ]);

            // Return the order_id. When this order is paid, Razorpay will
            // automatically mark the corresponding invoice as 'paid'.
            return response()->json([
                'order_id' => $order->id,
                'amount'   => $order->amount,
                'currency' => $order->currency
            ]);

        } catch (\Exception $e) {
            Log::error("Subscription Payment Initiation Failed for sub ID {$subscription_id}: " . $e->getMessage());
            return response()->json(['error' => 'Could not initiate subscription payment.'], 500);
        }
    }

    // Keep this function as well, to provide the key_id to the frontend securely
    public function getRazorpayKey()
    {
        return response()->json(['key_id' => env('RAZORPAY_KEY')]);
    }




    /**
     * Yeh function user ki saari subscriptions laayega
     */
    public function getUserSubscriptions(Request $request, $customerId)
    {
        if (!$customerId) {
            return response()->json(['error' => 'Customer ID not provided.'], 400);
        }

        $cacheKey = 'user_subscriptions_' . $customerId;

        // Caching se data laane ki koshish karein
        $subscriptionsData = Cache::remember($cacheKey, now()->addMinutes($this->cacheDuration), function () use ($customerId) {
            // Step 1: Razorpay se saari subscriptions fetch karein
            $subsResponse = Http::withBasicAuth($this->keyId, $this->keySecret)->get('https://api.razorpay.com/v1/subscriptions', ['count' => 100]);
            if (!$subsResponse->successful()) {
                throw new \Exception('Could not fetch subscriptions from Razorpay.');
            }
            $allSubscriptions = $subsResponse->json()['items'];

            // Step 2: Sirf logged-in user ki subscriptions filter karein
            $customerSubscriptions = collect($allSubscriptions)->where('customer_id', $customerId)->values();

            // Step 3: Har subscription ke saath uska plan details attach karein
            $planIds = $customerSubscriptions->pluck('plan_id')->unique()->filter();
            if ($planIds->isEmpty()) {
                return $customerSubscriptions; // Agar plan ID nahi hai to aise hi bhej dein
            }

            // Saare plans ek hi baar mein fetch karein
            $plansResponse = Http::withBasicAuth($this->keyId, $this->keySecret)->get('https://api.razorpay.com/v1/plans', ['count' => 100]);
            if (!$plansResponse->successful()) {
                throw new \Exception('Could not fetch plans.');
            }
            $planMap = collect($plansResponse->json()['items'])->keyBy('id');

            // Subscriptions mein plan details daalein
            return $customerSubscriptions->map(function ($sub) use ($planMap) {
                if (isset($sub['plan_id']) && $planMap->has($sub['plan_id'])) {
                    $sub['plan'] = $planMap->get($sub['plan_id']);
                }
                return $sub;
            });
        });

        return response()->json($subscriptionsData);
    }

    /**
     * Yeh function "Pay" button ke liye Razorpay Order create karega
     */

    public function getSingleSubscriptionDetails(Request $request, $subscriptionId)
    {
        if (!$subscriptionId) {
            return response()->json(['error' => 'Subscription ID not provided.'], 400);
        }

        try {
            // Subscription aur customer details fetch karein
            $subscriptionResponse = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->get("https://api.razorpay.com/v1/subscriptions/{$subscriptionId}?expand[]=customer");

            if (!$subscriptionResponse->successful()) {
                throw new \Exception('Could not fetch subscription details from Razorpay.');
            }
            $subscription = $subscriptionResponse->json();

            // Plan details fetch karein taaki amount aur naam mil sake
            if (!empty($subscription['plan_id'])) {
                $planResponse = Http::withBasicAuth($this->keyId, $this->keySecret)
                    ->get("https://api.razorpay.com/v1/plans/{$subscription['plan_id']}");
                if ($planResponse->successful()) {
                    $subscription['plan'] = $planResponse->json();
                }
            }

            // Us subscription se jude saare invoices fetch karein
            $invoicesResponse = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->get("https://api.razorpay.com/v1/invoices", ['subscription_id' => $subscriptionId, 'count' => 100]);

            return response()->json([
                'subscription' => $subscription,
                'invoices' => $invoicesResponse->successful() ? $invoicesResponse->json()['items'] : []
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get single subscription details for ID {$subscriptionId}: " . $e->getMessage());
            return response()->json(['error' => 'Could not fetch subscription details.'], 500);
        }
    }
}
