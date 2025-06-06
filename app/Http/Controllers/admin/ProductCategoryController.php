<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ProductCategoryController extends Controller


{
    public function index()
    {
        $categories = DB::table('productcategories')->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required']);

        $id = DB::table('productcategories')->insertGetId([
            'name' => $request->input('name'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $category = DB::table('productcategories')->where('id', $id)->first();
        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required']);

        DB::table('productcategories')
            ->where('id', $id)
            ->update([
                'name' => $request->input('name'),
                'updated_at' => now(),
            ]);

        $updatedCategory = DB::table('productcategories')->where('id', $id)->first();
        return response()->json($updatedCategory);
    }

    public function destroy($id)
    {
        $exists = DB::table('productcategories')->where('id', $id)->exists();

        if (!$exists) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        DB::table('productcategories')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
public function deleteMultiple(Request $request)
{
    $ids = $request->input('ids');

    if (!is_array($ids) || empty($ids)) {
        return response()->json(['error' => 'Invalid ID list'], 400);
    }

    $records = DB::table('productcategories')->whereIn('id', $ids)->delete();

    return response()->json([
        'records_found' => $records,
        'ids_received' => $ids,
    ]);
}



}
