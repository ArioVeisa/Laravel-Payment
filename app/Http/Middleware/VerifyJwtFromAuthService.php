<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VerifyJwtFromAuthService
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token missing'], 401);
        }

        // Validasi token ke AuthService
        $response = Http::withToken($token)->get('http://127.0.0.1:8000/api/users/me');

        if (!$response->ok()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Optional: inject user info ke request
        $request->merge(['user_data' => $response->json()]);

        return $next($request);
    }
}
