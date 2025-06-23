<?
namespace App\Services;

use Razorpay\Api\Api;

class RazorpayService
{
    protected $api;

    public function __construct()
    {
        $this->api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));
    }

    // Get active subscription for the user
    public function getActiveSubscription($razorpayCustomerId)
    {
        $subs = $this->api->subscription->all(['customer_id' => $razorpayCustomerId]);

        foreach ($subs['items'] as $sub) {
            if ($sub['status'] === 'active') {
                return $sub;
            }
        }

        return null;
    }

    // Get upcoming invoice from Razorpay
    public function getUpcomingInvoice($subscriptionId)
    {
        $invoices = $this->api->invoice->all(['subscription_id' => $subscriptionId]);

        foreach ($invoices['items'] as $invoice) {
            if ($invoice['status'] === 'issued') {
                return [
                    'amount'     => $invoice['amount_due'] / 100,
                    'date'       => date('d-M-Y', $invoice['date']),
                    'invoice_id' => $invoice['id'],
                    'product'    => $invoice['description'],
                ];
            }
        }

        return null;
    }
}
