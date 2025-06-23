<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class UserAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $customer = DB::table('customers')
            ->where('email', $credentials['email'])
            ->first();

        if (!$customer || !Hash::check($credentials['password'], $customer->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        Session::put('customer_id',    $customer->id);
        Session::put('customer_name',  $customer->name);
        Session::put('customer_email', $customer->email);

        return response()->json([
            'message'  => 'Login successful.',
            'customer' => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'razorpay_customer_id' => $customer->id
            ],
        ]);
    }
}
