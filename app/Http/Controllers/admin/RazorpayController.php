<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache; // Caching ke liye import karein

class RazorpayController extends Controller
{
    private $keyId;
    private $keySecret;
    private $cacheDuration = 5; // Cache kitne minutes tak rahega

    public function __construct()
    {
        $this->keyId = config('services.razorpay.key');
        $this->keySecret = config('services.razorpay.secret');
    }

    private function areKeysConfigured()
    {
        if (!$this->keyId || !$this->keySecret) {
            Log::error('Razorpay API keys are not configured.');
            return false;
        }
        return true;
    }

    // ✅ --- CACHING APPLIED --- ✅
    public function getActiveMandatesCount()
    {
        $data = Cache::remember('razorpay_active_mandates_count', now()->addMinutes($this->cacheDuration), function () {
            if (!$this->areKeysConfigured()) return ['activeCount' => 0];
            try {
                $activeResponse = Http::withBasicAuth($this->keyId, $this->keySecret)->get('https://api.razorpay.com/v1/subscriptions', ['status' => 'active', 'count' => 1]);
                $authResponse = Http::withBasicAuth($this->keyId, $this->keySecret)->get('https://api.razorpay.com/v1/subscriptions', ['status' => 'authenticated', 'count' => 1]);
                $activeCount = $activeResponse->successful() ? $activeResponse->json()['count'] : 0;
                $authCount = $authResponse->successful() ? $authResponse->json()['count'] : 0;
                return ['activeCount' => $activeCount + $authCount];
            } catch (\Exception $e) {
                return ['activeCount' => 0];
            }
        });
        return response()->json($data);
    }

    // ✅ --- CACHING APPLIED --- ✅
    public function getPendingMandatesCount()
    {
        $data = Cache::remember('razorpay_pending_mandates_count', now()->addMinutes($this->cacheDuration), function () {
            if (!$this->areKeysConfigured()) return ['pendingCount' => 0];
            try {
                $response = Http::withBasicAuth($this->keyId, $this->keySecret)->get('https://api.razorpay.com/v1/subscriptions', ['status' => 'created', 'count' => 1]);
                return ['pendingCount' => $response->json()['count'] ?? 0];
            } catch (\Exception $e) {
                return ['pendingCount' => 0];
            }
        });
        return response()->json($data);
    }

    // ✅ --- CACHING APPLIED --- ✅
    public function getMonthlyCollection()
    {
        $cacheKey = 'razorpay_monthly_collection_' . now()->format('Y_m');
        $data = Cache::remember($cacheKey, now()->addMinutes($this->cacheDuration), function () {
            if (!$this->areKeysConfigured()) return ['monthly_collection' => 0];
            try {
                $startOfMonth = now()->startOfMonth()->timestamp;
                $endOfMonth = now()->endOfMonth()->timestamp;
                $totalPaidAmount = 0;
                $response = Http::withBasicAuth($this->keyId, $this->keySecret)->get('https://api.razorpay.com/v1/invoices', ['from' => $startOfMonth, 'to' => $endOfMonth, 'status' => 'paid', 'type' => 'subscription', 'count' => 100]);
                if ($response->successful()) {
                    foreach ($response->json()['items'] as $invoice) { $totalPaidAmount += $invoice['amount_paid']; }
                }
                return ['monthly_collection' => $totalPaidAmount];
            } catch (\Exception $e) {
                return ['monthly_collection' => 0];
            }
        });
        return response()->json($data);
    }

    // ✅ --- CACHING APPLIED --- ✅
    public function getSubscriptionDetails($subscriptionId)
    {
        $cacheKey = 'razorpay_subscription_details_' . $subscriptionId;
        $data = Cache::remember($cacheKey, now()->addMinutes($this->cacheDuration), function () use ($subscriptionId) {
            if (!$this->areKeysConfigured()) return ['error' => 'Keys not configured'];
            try {
                $subscriptionResponse = Http::withBasicAuth($this->keyId, $this->keySecret)->get("https://api.razorpay.com/v1/subscriptions/{$subscriptionId}?expand[]=customer");
                if (!$subscriptionResponse->successful()) { return ['error' => 'Failed to fetch subscription details']; }
                $subscription = $subscriptionResponse->json();
                if (!empty($subscription['plan_id'])) {
                    $planResponse = Http::withBasicAuth($this->keyId, $this->keySecret)->get("https://api.razorpay.com/v1/plans/{$subscription['plan_id']}");
                    if ($planResponse->successful()) { $subscription['plan'] = $planResponse->json(); }
                }
                $invoicesResponse = Http::withBasicAuth($this->keyId, $this->keySecret)->get("https://api.razorpay.com/v1/invoices", ['subscription_id' => $subscriptionId, 'count' => 100]);
                return ['subscription' => $subscription, 'invoices' => $invoicesResponse->successful() ? $invoicesResponse->json()['items'] : [] ];
            } catch (\Exception $e) {
                Log::error("Error in getSubscriptionDetails for {$subscriptionId}: " . $e->getMessage());
                return ['error' => 'Failed to fetch details due to a server error.'];
            }
        });
        return response()->json($data);
    }

    // ✅ --- CACHING APPLIED --- ✅
    public function getAllSubscriptions(Request $request)
    {
        $cacheKey = 'razorpay_all_subscriptions_' . md5($request->fullUrl());
        $data = Cache::remember($cacheKey, now()->addMinutes($this->cacheDuration), function () use ($request) {
            if (!$this->areKeysConfigured()) return ['items' => []];
            try {
                $razorpayApiParams = ['count' => 100];
                if ($request->filled('plan_id')) { $razorpayApiParams['plan_id'] = $request->query('plan_id'); }

                $subsResponse = Http::withBasicAuth($this->keyId, $this->keySecret)->get('https://api.razorpay.com/v1/subscriptions', $razorpayApiParams);
                $plansResponse = Http::withBasicAuth($this->keyId, $this->keySecret)->get('https://api.razorpay.com/v1/plans', ['count' => 100]);
                $customersResponse = Http::withBasicAuth($this->keyId, $this->keySecret)->get('https://api.razorpay.com/v1/customers', ['count' => 100]);
                if (!$subsResponse->successful() || !$plansResponse->successful() || !$customersResponse->successful()) { throw new \Exception('Failed to fetch initial data from Razorpay.'); }

                $subscriptions = $subsResponse->json()['items'];
                $customerMap = collect($customersResponse->json()['items'])->keyBy('id');
                $planMap = collect($plansResponse->json()['items'])->keyBy('id');

                $enrichedSubscriptions = array_map(function ($subscription) use ($customerMap, $planMap) {
                    $subscription['customer'] = $customerMap->get($subscription['customer_id']);
                    $subscription['plan'] = $planMap->get($subscription['plan_id']);
                    return $subscription;
                }, $subscriptions);

                $filteredSubscriptions = collect($enrichedSubscriptions)
                    ->when($request->filled('status'), fn($c) => $c->where('status', $request->query('status')))
                    ->when($request->filled('subscription_id'), fn($c) => $c->where('id', $request->query('subscription_id')))
                    ->when($request->filled('plan_id'), fn($c) => $c->where('plan_id', $request->query('plan_id')))
                    ->when($request->filled('customer_email'), fn($c) => $c->where('customer.email', $request->query('customer_email')))
                    ->when($request->filled('search'), function ($c) use ($request) {
                        $searchTerm = strtolower($request->query('search'));
                        return $c->filter(fn($item) => Str::contains(strtolower($item['customer']['name'] ?? ''), $searchTerm) || Str::contains(strtolower($item['plan']['item']['name'] ?? ''), $searchTerm));
                    })
                    ->when($request->filled('completing_in'), function ($c) use ($request) {
                        $days = (int)$request->query('completing_in');
                        if ($days > 0) {
                            $from = now()->timestamp;
                            $to = now()->addDays($days)->endOfDay()->timestamp;
                            return $c->whereNotNull('end_at')->whereBetween('end_at', [$from, $to]);
                        }
                        return $c;
                    })
                    ->when($request->filled('from') && $request->filled('to'), function ($c) use ($request) {
                        try {
                            $from = Carbon::parse($request->query('from'))->startOfDay()->timestamp;
                            $to = Carbon::parse($request->query('to'))->endOfDay()->timestamp;
                            return $c->whereBetween('created_at', [$from, $to]);
                        } catch (\Exception $e) { return $c; }
                    });

                return ['items' => $filteredSubscriptions->values()->all()];

            } catch (\Exception $e) {
                Log::error('getAllSubscriptions failed: ' . $e->getMessage());
                return ['items' => []];
            }
        });
        return response()->json($data);
    }
}
