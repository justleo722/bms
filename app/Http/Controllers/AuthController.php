<?php

namespace App\Http\Controllers;

use App\Mail\NewRegistrationNotificationMail;
use App\Mail\RegistrationWelcomeMail;
use App\Models\User;
use App\Services\RegistrationEntryCodePool;
use App\Settings\BarSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    /**
     * How many failed sign-in attempts are allowed per minute and IP address.
     */
    private const MAX_LOGIN_ATTEMPTS = 10;

    /**
     * The sign-in screen (4 digit staff code).
     */
    public function showLogin(): View
    {
        return view('pages.login', [
            'hasUsers' => User::withTrashed()->count() > 0,
        ]);
    }

    /**
     * Sign a staff member in with their 4 digit code.
     */
    public function login(Request $request): RedirectResponse|JsonResponse
    {
        $credentials = $request->validate([
            'pin' => ['required', 'digits:4'],
        ], [], ['pin' => 'code']);

        $throttleKey = 'login|'.Str::lower($request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'pin' => 'Too many attempts. Please wait a minute and try again.',
            ]);
        }

        $user = User::where('pin', $credentials['pin'])
            ->where('status', 'active')
            ->first();

        if (! $user) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'pin' => 'Invalid code. Please try again.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $redirect = $user->landingPath();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Signed in successfully.',
                'redirect' => url($redirect),
            ]);
        }

        return redirect($redirect);
    }

    /**
     * The registration screen.
     */
    public function showRegister(RegistrationEntryCodePool $entryCodes): View
    {
        return view('pages.register', [
            'hasUsers' => User::withTrashed()->count() > 0,
            'initialCodeEmailError' => $entryCodes->sendInitialCodesIfNeeded(),
        ]);
    }

    /**
     * Create the administrator account.
     *
     * The entry code is a single-use credential from this installation's
     * active registration code pool.
     */
    public function register(Request $request, RegistrationEntryCodePool $entryCodes): JsonResponse
    {
        $data = $request->validate([
            'entry_code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:120'],
            'business_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'entry_code' => 'entry code',
            'business_name' => 'business name',
            'phone' => 'phone number',
        ]);

        $pin = $this->generateUniquePin();
        $registration = $entryCodes->consumeAndReplace(
            $data['entry_code'],
            function (string $_validatedCode) use ($data, $pin): User {
                $user = User::create([
                    'name' => $data['name'],
                    'business_name' => $data['business_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'role' => 'administrator',
                    'pin' => $pin,
                    'password' => $data['password'],
                ]);

                // A newly registered business starts with its own profile and no
                // inherited branding from a previous registration.
                BarSettings::deleteLogo();
                BarSettings::save([
                    'name' => $data['business_name'],
                    'phone' => $data['phone'],
                    'address' => '',
                    'receipt_header' => $data['business_name'],
                    'logo' => null,
                ]);

                return $user;
            }
        );
        $user = $registration['user'];

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $emailWarnings = $this->sendRegistrationEmails(
            $user,
            $registration['used_code'],
            $registration['replacement_code'],
            $pin,
        );

        return response()->json([
            'ok' => true,
            'message' => 'Your admin account has been created.',
            'pin' => $pin,
            'email_warnings' => $emailWarnings,
            'redirect' => route('dashboard'),
        ]);
    }

    /**
     * Sign the current administrator out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * A 4 digit code that is not already taken.
     */
    private function generateUniquePin(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::withTrashed()->where('pin', $pin)->exists());

        return $pin;
    }

    /**
     * Send the owner a replacement code and the new account its sign-in code.
     *
     * @return array<int, string>
     */
    private function sendRegistrationEmails(User $user, string $usedCode, string $replacementCode, string $pin): array
    {
        $warnings = [];
        $messages = [
            [
                'recipient' => (string) config('bms.registration_notification_email'),
                'mailable' => new NewRegistrationNotificationMail($user, $usedCode, $replacementCode),
                'warning' => 'The system owner notification could not be sent.',
            ],
            [
                'recipient' => $user->email,
                'mailable' => new RegistrationWelcomeMail($user, $pin),
                'warning' => 'Your sign-in code email could not be sent.',
            ],
        ];

        foreach ($messages as $message) {
            try {
                if (config('mail.default') === 'log') {
                    throw new \RuntimeException('The mailer is configured to log rather than deliver email.');
                }

                Mail::to($message['recipient'])->send($message['mailable']);
            } catch (Throwable $exception) {
                Log::error($message['warning'], [
                    'recipient' => $message['recipient'],
                    'user_id' => $user->id,
                    'exception' => $exception->getMessage(),
                ]);
                $warnings[] = $message['warning'].' Check the mail configuration.';
            }
        }

        return $warnings;
    }
}
