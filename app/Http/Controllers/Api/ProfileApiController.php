<?php

namespace App\Http\Controllers\Api;

use App\Support\Upload;
use App\Support\UploadException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service profile management for every signed-in role.
 *
 *   POST /api/profile          { name, phone? }        -> updated user
 *   POST /api/profile/photo    multipart photo         -> updated user
 *   POST /api/profile/password { current_password, password, password_confirmation }
 *
 * Users can only ever change their own account; there is no id in any route.
 */
class ProfileApiController extends ApiController
{
    /** Update the signed-in user's display name (and optional phone). */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user = $this->user();
        $user->name = trim($data['name']);
        $user->phone = isset($data['phone'])
            ? (trim((string) $data['phone']) ?: null)
            : $user->phone;
        $user->save();

        return $this->ok($user->fresh('team')?->apiPayload() ?? $user->apiPayload());
    }

    /** Replace the signed-in user's profile photo. */
    public function photo(Request $request): JsonResponse
    {
        if (! $request->hasFile('photo') || ! $request->file('photo')?->isValid()) {
            return $this->validationError([
                'photo' => ['Choose a photo to upload.'],
            ]);
        }

        $user = $this->user();

        try {
            $path = Upload::image($request->file('photo'), 'avatars', $user->photo);
        } catch (UploadException $e) {
            return $this->validationError(['photo' => [$e->getMessage()]]);
        }

        if ($path === null) {
            return $this->validationError([
                'photo' => ['The photo could not be stored. Please try another image.'],
            ]);
        }

        $user->photo = $path;
        $user->save();

        return $this->ok($user->fresh('team')?->apiPayload() ?? $user->apiPayload());
    }

    /** Change the signed-in user's password after confirming the old one. */
    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $this->user();

        if (! Hash::check((string) $data['current_password'], (string) $user->getRawOriginal('password'))) {
            return $this->validationError([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->password = (string) $data['password'];
        $user->save();

        return $this->message('Password updated.');
    }
}
