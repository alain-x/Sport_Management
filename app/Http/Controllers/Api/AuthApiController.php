<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Token-based authentication for the mobile app.
 *
 * POST /api/login   -> { token, user }
 * POST /api/logout  -> revokes the current token
 * GET  /api/me      -> the signed-in user
 */
class AuthApiController extends ApiController
{
    /** Issue a personal access token for valid credentials. */
    public function login(Request $request): JsonResponse
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $device = trim((string) $request->input('device_name', 'mobile')) ?: 'mobile';

        $errors = [];
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        }
        if ($password === '') {
            $errors['password'][] = 'Password is required.';
        }
        if ($errors !== []) {
            return $this->validationError($errors);
        }

        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        // Guard against a stored value that is not a real hash (see the web
        // login): hashing a plaintext row would throw and surface as a 500.
        if ($user === null || ! $this->isUsableHash((string) $user->getRawOriginal('password'))) {
            return $this->validationError(
                ['email' => ['These credentials do not match our records.']],
                'These credentials do not match our records.'
            );
        }

        if (! Hash::check($password, (string) $user->getRawOriginal('password'))) {
            return $this->validationError(
                ['email' => ['These credentials do not match our records.']],
                'These credentials do not match our records.'
            );
        }

        // One token per device; replace any earlier one with the same name.
        $user->tokens()->where('name', $device)->delete();
        $token = $user->createToken($device)->plainTextToken;

        return response()->json([
            'message' => 'Signed in successfully.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    /** The signed-in user for the current token. */
    public function me(Request $request): JsonResponse
    {
        return $this->ok($this->userPayload($this->user()));
    }

    /** Revoke the token used for the current request. */
    public function logout(Request $request): JsonResponse
    {
        $token = $this->user()?->currentAccessToken();
        if ($token !== null && method_exists($token, 'delete')) {
            $token->delete();
        }

        return $this->message('Signed out successfully.');
    }

    /** User shape returned to the client (never includes the password). */
    private function userPayload(User $user): array
    {
        return $user->apiPayload();
    }

    /** True when the value looks like a hash the configured hasher can verify. */
    private function isUsableHash(string $hash): bool
    {
        return (bool) preg_match('/^\$(2[aby]|argon2i|argon2id)\$/', $hash);
    }
}
