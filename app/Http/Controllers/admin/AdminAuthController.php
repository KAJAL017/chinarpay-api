<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class AdminAuthController extends Controller
{
    public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);


    $admin = DB::table('admins')
        ->where('email', $request->email)
        ->where('password', $request->password)
        ->first();

    if ($admin) {
        // Login successful
        return response()->json([
            'message' => 'Login successful.',
            // 'admin' => $admin // Optional: return admin info
        ], 200);
    }

    return response()->json([
        'message' => 'Invalid email or password.'
    ], 401);
}
}
