<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    // Alle Produkte
    public function index()
    {
        return Product::with('category')->paginate(20);
    }

    // Einzelnes Produkt anzeigen
    public function show($id)
    {
        $product = Product::with('category')->find($id);

        if (! $product) {
            return response()->json(['message' => 'Produkt nicht gefunden'], 404);
        }

        return $product;
    }
    // Produkt erstellen
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'category'    => 'required',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
        ]);

        $imagePath = $request->file('image')?->store('products', 'public'); // "products/abc.jpg"

        $product = Product::create([
            'name'        => $data['name'],
            'price'       => $data['price'],
            'category_id' => $data['category'],
            'description' => $data['description'] ?? null,
            'image'  => $imagePath,
        ]);

        return response()->json([
            'id'         => $product->id,
            'name'       => $product->name,
            'price'      => $product->price,
            'description' => $product->description,
            'image'  => Storage::disk('public')->url($imagePath),
        ], 201);
    }


    // Produkt aktualisieren
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'price'       => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'category_id'    => 'required',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
        ]);

        // Bild speichern (falls vorhanden)
        if ($request->hasFile('image')) {
            // Altes Bild löschen, wenn vorhanden
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }

            $imagePath = $request->file('image')->store('products', 'public');
            $data['image'] = $imagePath;
        }

        $product->update($data);

        return response()->json($product);
    }



    // Produkt löschen
    public function destroy($id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json(['message' => 'Produkt nicht gefunden'], 404);
        }

        // Bild löschen, falls vorhanden
        if ($product->image) {
            $imagePath = public_path('storage/' . $product->image);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $product->delete();

        return response()->json(['message' => 'Produkt erfolgreich gelöscht'], 200);
    }

    public function byCategory($categoryId)
    {
        $categoryId = intval($categoryId);
        $products = Product::with('category')->where('category_id', $categoryId)->get();
        return response()->json($products);
    }
}
