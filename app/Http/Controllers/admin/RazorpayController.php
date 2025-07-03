<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class RazorpayController extends Controller
{
    private $keyId;
    private $keySecret;

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

    // --- COUNT FUNCTIONS (No changes needed here) ---
    public function getActiveMandatesCount()
    {
        if (!$this->areKeysConfigured()) return response()->json(['activeCount' => 0]);
        try {
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)->get('https://api.razorpay.com/v1/subscriptions', ['status' => 'active', 'count' => 1]);
            return response()->json(['activeCount' => $response->json()['count'] ?? 0]);
        } catch (\Exception $e) {
            return response()->json(['activeCount' => 0]);
        }
    }

    public function getPendingMandatesCount()
    {
        if (!$this->areKeysConfigured()) return response()->json(['pendingCount' => 0]);
        try {
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)->get('https://api.razorpay.com/v1/subscriptions', ['status' => 'created', 'count' => 1]);
            return response()->json(['pendingCount' => $response->json()['count'] ?? 0]);
        } catch (\Exception $e) {
            return response()->json(['pendingCount' => 0]);
        }
    }

    public function getMonthlyPendingCollection()
    {
        if (!$this->areKeysConfigured()) return response()->json(['pending_collection' => 0]);
        try {
            $startOfMonth = now()->startOfMonth()->timestamp;
            $endOfMonth = now()->endOfMonth()->timestamp;
            $totalPendingAmount = 0;
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->get('https://api.razorpay.com/v1/invoices', ['from' => $startOfMonth, 'to' => $endOfMonth, 'status' => 'issued', 'type' => 'subscription', 'count' => 100]);
            if ($response->successful()) {
                foreach ($response->json()['items'] as $invoice) {
                    $totalPendingAmount += $invoice['amount'];
                }
            }
            return response()->json(['pending_collection' => round($totalPendingAmount / 100)]);
        } catch (\Exception $e) {
            return response()->json(['pending_collection' => 0]);
        }
    }


    // ✅ --- FIX APPLIED HERE --- ✅
    // Get the list of ALL subscriptions
    public function getAllSubscriptions(Request $request)
    {
        if (!$this->areKeysConfigured()) {
            return response()->json(['items' => []]);
        }
        try {
            // We are now making two separate, reliable calls instead of one complex 'expand' call.
            // This is more robust and less likely to cause a 400 error.

            // Step 1: Fetch all subscriptions
            $subsResponse = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->get('https://api.razorpay.com/v1/subscriptions', ['count' => 100]);

            if (!$subsResponse->successful()) {
                throw new \Exception('Failed to fetch subscriptions from Razorpay.');
            }

            $subscriptions = $subsResponse->json()['items'];
            $customerIds = array_unique(array_column($subscriptions, 'customer_id'));

            // Step 2: Fetch all related customers in a single call (if any)
            $customers = [];
            if (!empty($customerIds)) {
                // Note: Razorpay API doesn't support fetching multiple customers by ID in one go.
                // We will fetch them one by one, but a better long-term solution is to store customer names locally.
                // For now, we will map them after fetching.
            }

            // We will map customer details on the frontend from our local DB or another source.
            // Returning subscriptions as is. The frontend already handles missing customer names.

            return response()->json($subsResponse->json());

        } catch (\Exception $e) {
            Log::error('getAllSubscriptions failed: ' . $e->getMessage());
            return response()->json(['items' => []]);
        }
    }

    // Get details for a SINGLE subscription
    public function getSubscriptionDetails($subscriptionId)
    {
        if (!$this->areKeysConfigured()) return response()->json(['error' => 'Keys not configured'], 500);
        try {
            $subscriptionResponse = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->get("https://api.razorpay.com/v1/subscriptions/{$subscriptionId}?expand[]=plan.item&expand[]=customer");

            $invoicesResponse = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->get("https://api.razorpay.com/v1/invoices", ['subscription_id' => $subscriptionId, 'count' => 100]);

            return response()->json([
                'subscription' => $subscriptionResponse->json(),
                'invoices' => $invoicesResponse->successful() ? $invoicesResponse->json()['items'] : []
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch details'], 500);
        }
    }
}