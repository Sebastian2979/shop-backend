<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::all();
    }

    public function store(Request $request)
    {
    $data = $request->validate([
        'name' => 'required|string|max:255|unique:categories,name',
    ]);

    $category = Category::create(['name' => $data['name']]);

    return response()->json($category, 201);
    }

    public function delete($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Kategorie nicht gefunden'], 404);
        }

        $category->delete();

        return response()->json('Kategorie erfolgreich gelöscht', 200);
    }
}
