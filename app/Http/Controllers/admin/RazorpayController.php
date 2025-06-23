<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class RazorpayController extends Controller
{
    public function getSubscriptions()
    {
        $keyId = env('RAZORPAY_KEY');
        $keySecret = env('RAZORPAY_SECRET');

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->get('https://api.razorpay.com/v1/subscriptions');

        if ($response->successful()) {
            return response()->json($response->json());
        } else {
            return response()->json([
                'error' => 'Failed to fetch subscriptions from Razorpay',
                'details' => $response->body(),
            ], $response->status());
        }
    }

    public function getMonthlyCollection()
    {
        $keyId = env('RAZORPAY_KEY');
        $keySecret = env('RAZORPAY_SECRET');

        $startOfMonth = strtotime(date('Y-m-01 00:00:00'));
        $endOfMonth = strtotime(date('Y-m-t 23:59:59'));

        $totalAmount = 0;
        $count = 100;
        $skip = 0;

        do {
            $response = Http::withBasicAuth($keyId, $keySecret)
                ->get('https://api.razorpay.com/v1/payments', [
                    'from' => $startOfMonth,
                    'to' => $endOfMonth,
                    'count' => $count,
                    'skip' => $skip,
                ]);

            if (!$response->successful()) {
                return response()->json(['error' => 'API call failed'], $response->status());
            }

            $payments = $response->json()['items'];

            foreach ($payments as $payment) {
                if ($payment['status'] === 'captured') {
                    $totalAmount += $payment['amount'];
                }
            }

            $skip += $count;

        } while (count($payments) === $count);

        return response()->json([
            'total_collection' => round($totalAmount / 100),
        ]);
    }
    public function getPlansActive()
    {
        $keyId = env('RAZORPAY_KEY');
        $keySecret = env('RAZORPAY_SECRET');

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->get('https://api.razorpay.com/v1/subscriptions?status=active');

        if (!$response->successful()) {
            return response()->json([
                'error' => 'Failed to fetch subscriptions from Razorpay',
            ], $response->status());
        }

        $subscriptions = $response->json()['items'] ?? [];
        $activeCount = count($subscriptions);

        return response()->json([
            'activeCount' => $activeCount,
            'subscriptions' => $subscriptions,
        ]);
    }
    public function getPlansPending()
    {
        $keyId = env('RAZORPAY_KEY');
        $keySecret = env('RAZORPAY_SECRET');

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->get('https://api.razorpay.com/v1/subscriptions?status=created');

        if (!$response->successful()) {
            return response()->json([
                'error' => 'Failed to fetch subscriptions from Razorpay',
            ], $response->status());
        }

        $subscriptions = $response->json()['items'] ?? [];
        $pendingCount = count($subscriptions);

        return response()->json([
            'pendingCount' => $pendingCount,
            'subscriptions' => $subscriptions,
        ]);
    }


}
