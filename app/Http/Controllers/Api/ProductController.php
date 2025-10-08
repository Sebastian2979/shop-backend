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
        return Product::paginate(10);
    }

    // Einzelnes Produkt anzeigen
    public function show($id)
    {
        $product = Product::find($id);

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
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120', // 5 MB
        ]);

        // Bild speichern (falls vorhanden)
        $imagePath = null;
        if ($request->hasFile('image')) {
            // Speichert in storage/app/public/products
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = Product::create([
            'name'        => $data['name'],
            'price'       => $data['price'],
            'description' => $data['description'] ?? null,
            'image'  => $imagePath, // nur Pfad/URL in DB!
        ]);

        // Optional: gleich eine öffentlich zugängliche URL mitsenden
        $product->image_url = $imagePath ? asset('storage/' . $imagePath) : null;

        return response()->json($product, 201);
    }


    // Produkt aktualisieren
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'price'       => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
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

        // Optional: URL mitsenden
        $product->image_url = $product->image ? asset('storage/' . $product->image) : null;

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
}
