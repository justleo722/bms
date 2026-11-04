<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Settings\BarSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Update the administrator information shown in the profile modal.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'Please sign in to manage the administrator profile.',
            ], 401);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'business_name' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'business_name' => 'business name',
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->business_name = $data['business_name'] ?? null;

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        // Keep the bar profile and printed receipt in step with the admin profile.
        if (filled($user->business_name)) {
            BarSettings::save([
                'name' => $user->business_name,
                'phone' => $user->phone,
                'receipt_header' => $user->business_name,
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Profile updated successfully.',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'business_name' => $user->business_name,
                'initials' => $user->initials(),
            ],
        ]);
    }

    /**
     * Issue a new 4 digit sign-in code for the administrator.
     */
    public function regeneratePin(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'Please sign in to manage the administrator profile.',
            ], 401);
        }

        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::withTrashed()->where('pin', $pin)->where('id', '!=', $user->id)->exists());

        $user->pin = $pin;
        $user->save();

        return response()->json([
            'ok' => true,
            'message' => 'A new sign-in code has been generated.',
            'pin' => $pin,
        ]);
    }

    /**
     * Change the password of the administrator.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'Please sign in to manage the administrator profile.',
            ], 401);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = $data['password'];
        $user->save();

        return response()->json([
            'ok' => true,
            'message' => 'Password changed successfully.',
        ]);
    }
}
