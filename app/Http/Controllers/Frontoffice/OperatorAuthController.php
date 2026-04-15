<?php

namespace App\Http\Controllers\Frontoffice;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OperatorAuthController extends Controller
{
    /**
     * Verify operator password and return a temporary session token
     */
    public function verifyPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        try {
            // Find user by password - check all operators
            $users = User::whereIn('role', ['operator', 'admin'])->get();
            $user = null;

            foreach ($users as $potentialUser) {
                // For operators: check hashed password field
                if ($potentialUser->role === 'operator' && Hash::check($validated['password'], $potentialUser->password)) {
                    $user = $potentialUser;
                    break;
                }
                // For admins: check authentication_pin (stored in plain text)
                if ($potentialUser->role === 'admin' && $potentialUser->authentication_pin === $validated['password']) {
                    $user = $potentialUser;
                    break;
                }
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password non corretta',
                ], 401);
            }

            // Generate a temporary token valid for current session
            $token = base64_encode($user->id . ':' . time() . ':' . bin2hex(random_bytes(16)));

            // Store in session for verification
            session(['operator_token_' . $token => [
                'user_id' => $user->id,
                'timestamp' => time(),
            ]]);

            return response()->json([
                'success' => true,
                'message' => 'Autenticazione riuscita',
                'data' => [
                    'token' => $token,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'role' => $user->role,
                    'permissions' => $user->role === 'admin'
                        ? array_keys(\App\Models\User::availablePermissions())
                        : ($user->permissions ?? []),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error verifying operator password: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore durante la verifica',
            ], 500);
        }
    }

    /**
     * Verify if a token is valid
     */
    public function verifyToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $tokenData = session('operator_token_' . $validated['token']);

        if (!$tokenData) {
            return response()->json([
                'success' => false,
                'message' => 'Token non valido o scaduto',
            ], 401);
        }

        // Check if token is older than 1 hour
        if (time() - $tokenData['timestamp'] > 3600) {
            session()->forget('operator_token_' . $validated['token']);
            return response()->json([
                'success' => false,
                'message' => 'Token scaduto',
            ], 401);
        }

        $user = User::find($tokenData['user_id']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Operatore non trovato',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
            ],
        ]);
    }

    /**
     * Generate an operator token for the currently authenticated backoffice admin
     */
    public function getAdminToken(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non autenticato'], 401);
        }

        $token = base64_encode($user->id . ':' . time() . ':' . bin2hex(random_bytes(16)));
        session(['operator_token_' . $token => [
            'user_id' => $user->id,
            'timestamp' => time(),
        ]]);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $user->role,
                'permissions' => $user->role === 'admin'
                    ? array_keys(\App\Models\User::availablePermissions())
                    : ($user->permissions ?? []),
            ],
        ]);
    }

    /**
     * Authenticate as admin and return a redirect URL for the backoffice
     */
    public function adminLoginRedirect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string',
            'order_id' => 'nullable|integer',
        ]);

        try {
            $users = User::where('role', 'admin')->get();
            $user = null;

            foreach ($users as $potentialUser) {
                // Check against authentication_pin (stored in plain text) for admins
                if ($potentialUser->authentication_pin === $validated['password']) {
                    $user = $potentialUser;
                    break;
                }
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password non corretta o utente non admin',
                ], 401);
            }

            Auth::login($user);

            $orderId = $validated['order_id'] ?? null;
            $url = $orderId
                ? route('restaurant.sales.show', $orderId)
                : route('restaurant.sales.index');

            return response()->json([
                'success' => true,
                'url' => $url,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in admin login redirect: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore durante l\'autenticazione',
            ], 500);
        }
    }

    /**
     * Verify admin PIN without logging in (used by frontoffice admin-gated features).
     */
    public function adminVerifyPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $user = User::where('role', 'admin')
            ->where('authentication_pin', $validated['password'])
            ->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Password non corretta o utente non admin',
            ], 401);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get all operators (users with operator or admin role)
     */
    public function getOperators(): JsonResponse
    {
        try {
            $operators = User::whereIn('role', ['operator', 'admin'])
                ->select('id', 'name', 'email', 'role', 'permissions')
                ->orderBy('name')
                ->get()
                ->map(function ($user) {
                    $user->permissions = $user->role === 'admin'
                        ? array_keys(\App\Models\User::availablePermissions())
                        : ($user->permissions ?? []);
                    return $user;
                });

            return response()->json([
                'success' => true,
                'data' => $operators,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching operators: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nel caricamento degli operatori',
            ], 500);
        }
    }
}
