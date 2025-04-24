<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function initiate(Request $request)
    {
        $request->validate(['order_id' => 'required|integer']);

        // ✅ Verifikasi ke OrderService
        $orderCheck = Http::withToken($request->bearerToken())
            ->get("http://localhost:8002/api/orders/" . $request->order_id);

        if (!$orderCheck->ok()) {
            return response()->json([
                'success' => false,
                'message' => 'Order ID not valid in OrderService'
            ], 404);
        }

        // ✅ Ambil user ID dari token
        $user = $request->get('user_data');
        $userId = $user['id'] ?? $user['data']['id'] ?? null;

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User not valid'
            ], 401);
        }

        // ✅ Buat Payment
        $payment = Payment::create([
            'order_id' => $request->order_id,
            'user_id' => $userId,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment initiated',
            'data' => [
                'payment_id' => $payment->id,
                'redirect_url' => "http://dummy-gateway.test/pay/{$payment->id}",
                'status' => $payment->status
            ]
        ]);
    }

    public function simulate(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|integer',
            'status' => 'required|in:paid,failed'
        ]);

        $payment = Payment::findOrFail($request->payment_id);
        $payment->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated',
            'data' => $payment
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->get('user_data');
        $userId = $user['id'] ?? $user['data']['id'] ?? null;

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User not valid'
            ], 401);
        }

        $payments = Payment::where('user_id', $userId)->get();

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }
}
