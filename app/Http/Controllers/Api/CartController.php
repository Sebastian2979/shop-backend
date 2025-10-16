<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Product;
use Laravel\Sanctum\PersonalAccessToken;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    // hole aktiven Warenkorb für User oder Session
    public function show(Request $request)
    {
        $cart = $this->resolveCart($request);

        return response()->json([
            'cart' => [
                'id' => $cart->id,
                'items' => $cart->items()->with(['product'])->get(),
            ],
        ]);
    }

    public function clearCart(Request $request)
    {

        $user = $request->user(); // kann null sein (Gast)
        // wenn keine Sanctum Route, User manuell via Token ermitteln
        if (!$user && ($raw = $request->bearerToken())) {
            if ($pat = PersonalAccessToken::findToken($raw)) {
                $user = $pat->tokenable;
            }
        }

        // Cart finden/erstellen
        $cart = null;
        if ($user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        } else {
            // bei dem guest_token handelt es sich um den cart_token vom Frontend
            $token = $request->payload['payload'] ?? $request->header('X-Cart-Token');
            if (empty($token)) {
                return response()->json(['message' => 'Missing cart token'], 422);
            }
            $cart = Cart::firstOrCreate(['guest_token' => $token]);
        }

        $cart->items()->delete();

        return response()->json([
            'cart' => [
                'id' => $cart->id,
                'items' => [],
            ],
        ]);
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'product_id'        => ['required', 'integer', 'exists:products,id'],
            'quantity'          => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->resolveCart($request);
        $cart->addItem($data['product_id'], $data['quantity']);

        return $this->show($request);
    }

    public function updateItem(Request $request, int $cartItemId)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->resolveCart($request);
        $cart->updateItemQty($cartItemId, $data['quantity']);

        return $this->show($request);
    }

    public function removeItem(Request $request, int $cartItemId)
    {
        $cart = $this->resolveCart($request);
        $cart->removeItem($cartItemId);

        return $this->show($request);
    }

    private function resolveCart(Request $request): Cart
    {
        // 1) eingeloggter User → aktiver Warenkorb
        if ($user = $request->user()) {
            return Cart::firstOrCreate(
                ['user_id' => $user->id, 'is_active' => true],
                ['status' => 'active']
            )->load('items');
        }

        // 2) Gast via session_token (z.B. aus Header oder Cookie)
        $token = $request->header('X-Session-Token') ?? $request->cookie('cart_token');

        if ($token) {
            $cart = Cart::firstOrCreate(
                ['session_token' => $token, 'is_active' => true],
                ['status' => 'active']
            );
            return $cart->load('items');
        }

        // 3) Falls gar nichts vorhanden, neuen Gast-Cart erstellen + Token setzen
        $cart = Cart::create([
            'session_token' => \Illuminate\Support\Str::uuid(),
            'is_active' => true,
            'status' => 'active',
        ]);

        // In der echten App: Token im Cookie/Response setzen
        return $cart->load('items');
    }

    public function sync(Request $request)
    {
        $payload = $request->validate([
            'items' => 'array',
            'items.*.product_id' => 'integer|exists:products,id',
            'items.*.quantity'   => 'integer|min:0',
        ]);

        $user = $request->user(); // kann null sein (Gast)
        // weil keine Sanctum Route, User manuell via Token ermitteln
        if (!$user && ($raw = $request->bearerToken())) {
            if ($pat = PersonalAccessToken::findToken($raw)) {
                $user = $pat->tokenable;
            }
        }
        // wenn Gast dann Token aus Header, um Warenkorb zu identifizieren
        $token = $request->header('X-Cart-Token');


        if (!$user && empty($token)) {
            // Sicherheit: ohne Ident steht kein Cart zur Verfügung
            return response()->json(['message' => 'Missing cart token'], 422);
        }

        // Cart finden/erstellen
        $cart = null;
        if ($user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
            $oldCart = Cart::where('guest_token', $token)->first();
            $oldCartItems = CartItem::where('cart_id', $oldCart?->id)->get();

            if ($oldCartItems) {
                // Gast-Warenkorb mit User-Warenkorb mergen
                foreach ($oldCartItems as $item) {
                    $cart->addItem($item->product_id, $item->quantity);
                }
                if ($oldCart) {
                    $oldCart->delete(); // alten Gast-Warenkorb entfernen
                }
            }
        } else {
            // bei dem guest_token handelt es sich um den cart_token vom Frontend
            $cart = Cart::firstOrCreate(['guest_token' => $token]);
        }

        $wanted = collect($payload['items'])->keyBy('product_id');

        $products = Product::whereIn('id', $wanted->keys())->get()->keyBy('id');

        // Alte Items entfernen, die nicht mehr im Payload sind
        CartItem::where('cart_id', $cart->id)
            ->whereNotIn('product_id', $wanted->keys())
            ->delete();

        // 2) Upsert/Löschlogik nach gewünschter Menge
        foreach ($wanted as $pid => $row) {
            $qty = (int) ($row['quantity'] ?? 0);

            // Falls Produkt unerwartet nicht geladen wurde, skippe defensiv
            if (!$products->has($pid)) {
                continue;
            }

            if ($qty <= 0) {
                // Menge 0/negativ -> Item entfernen (nicht speichern!)
                CartItem::where('cart_id', $cart->id)
                    ->where('product_id', $pid)
                    ->delete();
            } else {
                // Menge > 0 -> setzen/aktualisieren
                CartItem::updateOrCreate(
                    ['cart_id' => $cart->id, 'product_id' => $pid],
                    ['quantity' => $qty]
                );
            }
        }

        // 3) Safety: alles mit <= 0 aus der DB tilgen (falls irgendwo übersehen)
        CartItem::where('cart_id', $cart->id)
            ->where('quantity', '<=', 0)
            ->delete();

        $items = CartItem::with('product')->where('cart_id', $cart->id)->get();

        return response()->json([
            'cart_id' => $cart->id,
            'guest_token' => $cart->guest_token,
            'items' => $items->map(fn($i) => [
                'product_id' => $i->product_id,
                'name' => $i->product?->name,
                'quantity' => $i->quantity
            ])
        ]);
    }

    public function syncCartOnLogin(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $cart = Cart::where('user_id', $user->id)->first();

        return response()->json([
            'items' => $cart ? $cart->items()->with('product')->get() : []
        ]);
    }

    public function checkout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $cart = Cart::where('user_id', $user->id)->first();
        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $stripe = new StripeClient(config('services.stripe.secret'));

        // Baue line_items aus deinem Warenkorb
        $lineItems = [];
        foreach ($cart->items as $it) {
            // Verwende den beim Hinzufügen „eingefrorenen“ Preis wenn vorhanden,
            // sonst den aktuellen Produktpreis. IMMER integer Cents an Stripe!
            $unitCents = $it->unit_price_cents
                ?? (int) round(($it->product->price ?? 0) * 100);

            $lineItems[] = [
                'quantity'   => (int) $it->quantity,
                'price_data' => [
                    'currency'     => 'eur',
                    'unit_amount'  => $unitCents, // integer (z. B. 899 für 8,99 €)
                    'product_data' => [
                        'name'     => $it->product->name,
                        'metadata' => [
                            'product_id' => (string) $it->product_id,
                        ],
                    ],
                ],
            ];
        }

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'customer_email' => $user->email,
            'success_url' => rtrim(config('app.frontend_url') ?? env('FRONTEND_URL'), '/')
                . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => rtrim(config('app.frontend_url') ?? env('FRONTEND_URL'), '/')
                . '/cart',
            'metadata' => [
                'cart_id' => (string) $cart->id,
                'user_id' => (string) $user->id,
            ],
        ]);

        // Für Single-Page-Apps am bequemsten:
        return response()->json([
            'id'  => $session->id,
            'url' => $session->url, // alternativ: im Frontend redirectToCheckout(sessionId)
        ]);
    }

    // Frontend ruft dies nach Redirect auf, um die Order anzuzeigen / Fallback
    public function success(Request $request): JsonResponse
    {
        // 0) User ermitteln (Sanctum-Route oder manueller Bearer-Token-Fallback)
        $user = $request->user();
        if (!$user && ($raw = $request->bearerToken())) {
            if ($pat = PersonalAccessToken::findToken($raw)) {
                $user = $pat->tokenable;
            }
        }

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 1) Session laden
        $sessionId = (string) $request->input('session_id');
        if (!$sessionId) {
            return response()->json(['message' => 'Missing session_id'], 422);
        }

        $stripe  = new StripeClient(config('services.stripe.secret'));
        $session = $stripe->checkout->sessions->retrieve($sessionId, [
            'expand' => ['payment_intent'],
        ]);

        // 2) bezahlt?
        $paid = ($session->payment_status === 'paid')
            || (($session->payment_intent->status ?? null) === 'succeeded');
        if (!$paid) {
            return response()->json(['message' => 'Payment not completed'], 402);
        }

        // 3) gehört zum User?
        $metaUserId = (int) ($session->metadata->user_id ?? 0);
        if ($metaUserId !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 4) Intent-ID sicher ermitteln
        $intentId = is_string($session->payment_intent)
            ? $session->payment_intent
            : ($session->payment_intent->id ?? null);
        if (!$intentId) {
            return response()->json(['message' => 'Missing payment_intent'], 422);
        }

        // 5) Idempotenz: existiert schon eine Order zu dieser Session/Intent?
        $existing = Order::with('items')
            ->where('stripe_payment_intent', $intentId)
            ->orWhere('stripe_session_id', $sessionId)
            ->first();

        if ($existing) {
            return response()->json($existing);
        }

        // 6) Cart laden
        $cartId = (int) ($session->metadata->cart_id ?? 0);
        $cart   = Cart::with('items.product')
            ->where('id', $cartId)
            ->where('user_id', $user->id)
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart not found or empty'], 404);
        }

        // 7) Order anlegen (transaktional) – Unique-Fehler abfangen (Variante B)
        try {
            $order = DB::transaction(function () use ($cart, $user, $session, $intentId) {
                // 7a) Order-Header erstellen
                $order = Order::create([
                    'user_id'               => $user->id,
                    'cart_id'               => $cart->id,
                    'email'                 => $session->customer_details->email ?? $user->email,
                    'total_cents'           => 0,           // setzen wir nach den Positionen
                    'currency'              => 'eur',
                    'status'                => 'paid',
                    'stripe_session_id'     => $session->id,
                    'stripe_payment_intent' => $intentId,   // UNIQUE
                ]);

                // 7b) Positionen übertragen
                $total = 0;
                foreach ($cart->items as $it) {
                    if (!$it->product) continue;

                    $unit = $it->unit_price_cents
                        ?? (int) round(((float) ($it->product->price ?? 0)) * 100);
                    $qty  = max(1, (int) $it->quantity);
                    $line = $unit * $qty;
                    $total += $line;

                    OrderItem::create([
                        'order_id'         => $order->id,
                        'product_id'       => $it->product_id,
                        'name'             => $it->product->name,
                        'unit_price_cents' => $unit,
                        'quantity'         => $qty,
                        'total_cents'      => $line,
                    ]);
                }

                // 7c) Summe aktualisieren
                $order->update(['total_cents' => $total]);

                // 7d) Cart leeren/archivieren
                $cart->items()->delete();
                if (Schema::hasColumn('carts', 'is_active')) {
                    $cart->is_active = false;
                    $cart->save();
                }

                return $order->load('items');
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Jemand war schneller → bestehende Order zurückgeben
            $order = Order::with('items')
                ->where('stripe_payment_intent', $intentId)
                ->firstOrFail();
        }

        return response()->json($order);
    }
}
