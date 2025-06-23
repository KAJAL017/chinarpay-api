<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\RazorpayService;

class UserEmiController extends Controller
{
    public function getUserEmi(Request $request, RazorpayService $razorpayService)
    {
        $razorpayCustomerId = $request->header('razorpay_customer_id');

        if (!$razorpayCustomerId) {
            return response()->json(['message' => 'Razorpay customer ID missing.'], 400);
        }

        $subscription = $razorpayService->getActiveSubscription($razorpayCustomerId);
        if (!$subscription) {
            return response()->json(['message' => 'No active subscription found.'], 404);
        }

        $invoice = $razorpayService->getUpcomingInvoice($subscription['id']);
        if (!$invoice) {
            return response()->json(['message' => 'No upcoming EMI found.'], 404);
        }

        return response()->json([
            'emi_amount'   => $invoice['amount'],
            'due_date'     => $invoice['date'],
            'invoice_id'   => $invoice['invoice_id'],
            'product_name' => $invoice['product'] ?? 'Product',
        ]);
    }
}
