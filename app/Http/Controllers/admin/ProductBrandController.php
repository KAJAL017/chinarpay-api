<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


class ProductBrandController extends Controller
{
  public function index()
    {
        $brands = DB::table('brands')->get();
        return response()->json($brands);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required']);

        $id = DB::table('brands')->insertGetId([
            'name' => $request->input('name'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $brand = DB::table('brands')->where('id', $id)->first();
        return response()->json($brand, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required']);

        DB::table('brands')
            ->where('id', $id)
            ->update([
                'name' => $request->input('name'),
                'updated_at' => now(),
            ]);

        $updatedbrand = DB::table('brands')->where('id', $id)->first();
        return response()->json($updatedbrand);
    }

    public function destroy($id)
    {
        $exists = DB::table('brands')->where('id', $id)->exists();

        if (!$exists) {
            return response()->json(['message' => 'brand not found'], 404);
        }

        DB::table('brands')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
public function deleteMultiple(Request $request)
{
    $ids = $request->input('ids');

    if (!is_array($ids) || empty($ids)) {
        return response()->json(['error' => 'Invalid ID list'], 400);
    }

    $records = DB::table('brands')->whereIn('id', $ids)->delete();

    return response()->json([
        'records_found' => $records,
        'ids_received' => $ids,
    ]);
}

}
