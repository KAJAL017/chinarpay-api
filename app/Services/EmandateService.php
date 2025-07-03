<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;
use Exception;

class EmandateService
{
    protected $razorpay;

    public function __construct()
    {
        $this->razorpay = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));
    }

    /**
     * eMandate create karne ka poora process
     *
     * @param array $validatedData
     * @return string The authorization URL
     * @throws Exception
     */
    public function createMandate(array $validatedData): string
    {
        // DB Transaction: Ya to sab kuch hoga, ya kuch nahi.
        return DB::transaction(function () use ($validatedData) {

            $customer = DB::table('customers')->where('id', $validatedData['userId'])->first();

            if (!$customer) {
                // Agar customer nahi milta hai to error throw karein
                throw new Exception("Customer with ID {$validatedData['userId']} not found.");
            }

            $razorpayCustomerId = $this->getOrCreateRazorpayCustomer($customer);

            $authorizationUrl = null;
            $razorpayData = [];

            if ($validatedData['collectionType'] === 'installments') {
                $response = $this->createSubscription($validatedData, $razorpayCustomerId);
                $authorizationUrl = $response['short_url'];
                $razorpayData['razorpay_plan_id'] = $response['plan_id_local'];
                $razorpayData['razorpay_subscription_id'] = $response['id'];
            } else { // oneTime
                $response = $this->createPaymentLink($validatedData, $customer);
                $authorizationUrl = $response['short_url'];
                $razorpayData['razorpay_payment_link_id'] = $response['id'];
            }

            // ✅ --- YAHAN TYPO THEEK KAR DIYA GAYA HAI --- ✅
            $insertData = $validatedData;
            // 'userId' ki value ko 'user_id' key mein daalein
            $insertData['user_id'] = $insertData['userId'];
            // Ab purani 'userId' key ko hata dein
            unset($insertData['userId']);

            // Ab saare data ko merge karke insert karein
            DB::table('emandates')->insert(array_merge(
                $insertData,
                [
                    'razorpay_customer_id' => $razorpayCustomerId,
                    'razorpay_payment_link_url' => $authorizationUrl,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $razorpayData
            ));


            // Ab hum object ki jagah seedha URL return karenge
            return $authorizationUrl;
        });
    }

    /**
     * @param object $customer
     * @return string
     * @throws Exception
     */
    private function getOrCreateRazorpayCustomer(object $customer): string
    {
        if ($customer->razorpay_customer_id) {
            try {
                $this->razorpay->customer->fetch($customer->razorpay_customer_id);
                return $customer->razorpay_customer_id;
            } catch (Exception $e) {
                // ID invalid hai, naya banayenge
            }
        }

        $razorpayCustomer = $this->razorpay->customer->create([
            'name' => $customer->name,
            'email' => $customer->email,
            'contact' => $customer->phone,
            'fail_existing' => 0,
        ]);

        // DB::table() se update karein
        DB::table('customers')
            ->where('id', $customer->id)
            ->update(['razorpay_customer_id' => $razorpayCustomer['id']]);

        return $razorpayCustomer['id'];
    }

    private function createSubscription(array $data, string $razorpayCustomerId): array
    {
        $perInstallmentAmount = round($data['amount'] / $data['installments'], 2) * 100;

        $plan = $this->razorpay->plan->create([
            'period' => $data['frequency'],
            'interval' => 1,
            'item' => [
                'name' => 'Installment: ' . $data['billDetails'],
                'amount' => $perInstallmentAmount,
                'currency' => 'INR',
                'description' => 'Recurring payment for ' . $data['billDetails'],
            ],
        ]);

        $subscription = $this->razorpay->subscription->create([
            'plan_id' => $plan['id'],
            'customer_id' => $razorpayCustomerId,
            'customer_notify' => 1,
            'total_count' => (int)$data['installments'],
            'start_at' => strtotime($data['startDate']),
        ]);

        $subscriptionData = $subscription->toArray();
        $subscriptionData['plan_id_local'] = $plan['id'];

        return $subscriptionData;
    }

    /**
     * @param array $data
     * @param object $customer
     * @return array
     */
    private function createPaymentLink(array $data, object $customer): array
    {
        $link = $this->razorpay->paymentLink->create([
            'amount' => $data['amount'] * 100,
            'currency' => 'INR',
            'description' => $data['billDetails'],
            'customer' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'contact' => $customer->phone,
            ],
            'notify' => ['sms' => true, 'email' => true],
        ]);

        return $link->toArray();
    }
}
