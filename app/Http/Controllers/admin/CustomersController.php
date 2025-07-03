<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;

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
            'pan_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'adhar_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'photo_file' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        $panPath = null;
        $adharPath = null;
        $photoPath = null;

        if ($request->file('pan_file')) {
            $panFile = $request->file('pan_file');
            $filename = uniqid() . '_' . $panFile->getClientOriginalName();
            $panFile->move(public_path('uploads/pan'), $filename);
            $panPath = 'uploads/pan/' . $filename;
        }

        if ($request->file('adhar_file')) {
            $adharFile = $request->file('adhar_file');
            $filename = uniqid() . '_' . $adharFile->getClientOriginalName();
            $adharFile->move(public_path('uploads/adhar'), $filename);
            $adharPath = 'uploads/adhar/' . $filename;
        }

        if ($request->file('photo_file')) {
            $photoFile = $request->file('photo_file');
            $filename = uniqid() . '_' . $photoFile->getClientOriginalName();
            $photoFile->move(public_path('uploads/photo'), $filename);
            $photoPath = 'uploads/photo/' . $filename;
        }


        $id = DB::table('customers')->insertGetId([
            'name'        => $request->input('name'),
            'email'       => $request->input('email'),
            'phone'       => $request->input('phone'),
            'password'    => $request->filled('password') ? Hash::make($request->input('password')) : null,
            'pan_file'    => $panPath,
            'adhar_file'  => $adharPath,
            'photo_file'  => $photoPath,
            'created_at'  => now(),
            'updated_at'  => now(),
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
            'pan_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'adhar_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'photo_file' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        $customer = DB::table('customers')->where('id', $id)->first();
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $updateData = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'updated_at' => now(),
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->input('password'));
        }

        // File update logic
        foreach (['pan_file', 'adhar_file', 'photo_file'] as $fileField) {
            if ($request->file($fileField)) {
                // Delete old file if it exists
                if ($customer->$fileField && File::exists(public_path($customer->$fileField))) {
                    File::delete(public_path($customer->$fileField));
                }

                $folder = str_replace('_file', '', $fileField);
                $filename = uniqid() . '_' . $request->file($fileField)->getClientOriginalName();
                $relativePath = "uploads/$folder/$filename";

                // Move file
                $request->file($fileField)->move(public_path("uploads/$folder"), $filename);

                // Store only the relative path
                $updateData[$fileField] = $relativePath;
            }
        }


        DB::table('customers')->where('id', $id)->update($updateData);

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
