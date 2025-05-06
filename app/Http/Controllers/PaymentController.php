<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Exception;

class PaymentController extends Controller
{
    private $orderServiceUrl;

    public function __construct()
    {
        $this->orderServiceUrl = env('ORDER_SERVICE_URL', 'http://OrderService:9000');
    }

    public function initiate(Request $request)
    {
        try {
            $request->validate(['order_id' => 'required|integer']);

            // Debug log untuk request
            Log::info('Initiating payment:', [
                'order_id' => $request->order_id,
                'user_data' => $request->get('user_data'),
                'headers' => $request->headers->all(),
                'token' => $request->bearerToken()
            ]);

            // Verifikasi ke OrderService
            try {
                $orderCheck = Http::withToken($request->bearerToken())
                    ->timeout(10)
                    ->get("{$this->orderServiceUrl}/api/orders/" . $request->order_id);

                // Debug log untuk response OrderService
                Log::info('OrderService response:', [
                    'status' => $orderCheck->status(),
                    'body' => $orderCheck->json(),
                    'url' => "{$this->orderServiceUrl}/api/orders/" . $request->order_id
                ]);

                if (!$orderCheck->ok()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order ID not valid in OrderService',
                        'error' => $orderCheck->json()
                    ], 404);
                }
            } catch (Exception $e) {
                Log::error('Error connecting to OrderService:', [
                    'error' => $e->getMessage(),
                    'url' => "{$this->orderServiceUrl}/api/orders/" . $request->order_id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to connect to OrderService',
                    'error' => $e->getMessage()
                ], 500);
            }

            // Ambil user ID dari token
            $user = $request->get('user_data');
            Log::info('User data from token:', ['user_data' => $user]);
            
            $userId = $user['id'] ?? $user['data']['id'] ?? null;

            if (!$userId) {
                Log::error('User ID not found in token data', [
                    'user_data' => $user
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'User not valid'
                ], 401);
            }

            // Buat Payment
            try {
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
            } catch (QueryException $e) {
                Log::error('Database error creating payment:', [
                    'error' => $e->getMessage(),
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment record',
                    'error' => $e->getMessage()
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('Unexpected error in payment initiation:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
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
