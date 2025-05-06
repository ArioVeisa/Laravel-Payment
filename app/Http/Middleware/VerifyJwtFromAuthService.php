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

        // Validasi token ke AuthService menggunakan environment variable
        $authServiceUrl = env('AUTH_SERVICE_URL', 'http://auth-service:9000');
        $response = Http::withToken($token)->get($authServiceUrl . '/api/users/me');

        if (!$response->ok()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Optional: inject user info ke request
        $request->merge(['user_data' => $response->json()]);

        return $next($request);
    }
}
