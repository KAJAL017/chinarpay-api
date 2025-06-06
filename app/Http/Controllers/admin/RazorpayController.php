<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class RazorpayController extends Controller
{
    public function getPlans()
    {
        $keyId = env('RAZORPAY_KEY');
        $keySecret = env('RAZORPAY_SECRET');

        $response = Http::withBasicAuth($keyId, $keySecret)
                        ->get('https://api.razorpay.com/v1/plans');

        if ($response->successful()) {
            return response()->json($response->json());
        } else {
            return response()->json([
                'error' => 'Failed to fetch plans from Razorpay',
                'details' => $response->body(),
            ], $response->status());
        }
    }
}
