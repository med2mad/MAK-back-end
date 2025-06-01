<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: *');

use Illuminate\Support\Facades\Route;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request; 
use App\Http\Middleware\cors;

Route::get('/', function(Request $request){
  $data = Product::orderBy('order');
  return (["rows"=>$data->get()]);
});

Route::get('/cart', function(Request $request){
  $data = DB::select('SELECT id,nameEN,nameFR,nameCH,price,photo FROM products WHERE id IN (' . $request->query('ids') .')');
  return (["rows"=>$data]);
});

Route::post('/api/orders', function(Request $request){
    $validated = $request->validate([
        'name' => 'required|string',
        'phone' => 'required|string',
        'email' => 'nullable|email',
        'address' => 'nullable|string',
        'coupon_code' => 'nullable|string',
        'total' => 'required|numeric',
    ]);

    return DB::transaction(function () use ($request) {
        $order = Order::create([
            'name' => $request->input('name', 'No name provided'),
            'phone' => $request->input('phone', 'No phone provided'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'coupon_code' => $request->input('coupon_code'),
            'total' => $request->input('total', 0),
            'status' => 'pending'
        ]);

        // Attach products if they exist
        if ($request->has('products')) {
            foreach ($request->input('products') as $product) {
                $order->products()->attach($product['id'], [
                    'quantity' => $product['quantity'] ?? 1
                ]);
            }
        }

        return response()->json([
            'message' => 'Order created successfully (debug mode)',
            'order' => $order,
            'received_data' => $request->all() // Return received data for debugging
        ], 201);
    });
});

Route::get('/api/orders', function() {
    $orders = Order::with('products')
        ->orderBy('created_at', 'desc')
        ->get();
    
    return response()->json($orders);
});

Route::get('/api/orders/{id}', function($id) {
    $order = Order::with('products')->findOrFail($id);
    return response()->json($order);
});

Route::patch('/api/orders/{id}/status', function($id, Request $request) {
    $validated = $request->validate([
        'status' => 'required|in:pending,completed,cancelled'
    ]);

    $order = Order::findOrFail($id);
    $order->update(['status' => $validated['status']]);

    return response()->json([
        'message' => 'Status updated successfully',
        'order' => $order
    ]);
});

Route::delete('/api/orders/{id}', function($id) {
    $order = Order::findOrFail($id);
    $order->delete();
    
    return response()->json([
        'message' => 'Order deleted successfully'
    ]);
});