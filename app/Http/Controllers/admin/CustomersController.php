<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomersController extends Controller
{
    public function index()
    {
        $customers = DB::table('customers')->get();
        return response()->json($customers);
    }
    public function count()
    {
        $customers = DB::table('customers')->count();
        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
        ]);

        $id = DB::table('customers')->insertGetId([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customer = DB::table('customers')->where('id', $id)->first();
        return response()->json($customer, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
        ]);

        $updateData = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->input('password')),
            'updated_at' => now(),
        ];

        DB::table('customers')
            ->where('id', $id)
            ->update($updateData);

        $updatedCustomer = DB::table('customers')->where('id', $id)->first();
        return response()->json($updatedCustomer);
    }

    public function destroy($id)
    {
        $exists = DB::table('customers')->where('id', $id)->exists();

        if (!$exists) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        DB::table('customers')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function deleteMultiple(Request $request)
    {
        $ids = $request->input('ids');

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['error' => 'Invalid ID list'], 400);
        }

        $records = DB::table('customers')->whereIn('id', $ids)->delete();

        return response()->json([
            'records_deleted' => $records,
            'ids_received' => $ids,
        ]);
    }
    public function users()
    {
        $users = DB::table('users')
            ->select('id', 'name', 'email')
            ->get();

        return response()->json($users);
    }
}
