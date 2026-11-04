<?php

namespace App\Services;

use App\Mail\InitialRegistrationCodesMail;
use App\Models\RegistrationCodePool;
use App\Models\RegistrationEntryCode;
use App\Models\User;
use App\Settings\RegistrationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistrationEntryCodePool
{
    public const ACTIVE_CODE_COUNT = 6;

    /**
     * Create the initial six-code pool and notify the system owner once.
     *
     * @return string|null Mail delivery error, if any.
     */
    public function sendInitialCodesIfNeeded(): ?string
    {
        $pool = DB::transaction(function () {
            $pool = RegistrationCodePool::query()->lockForUpdate()->findOrFail(1);

            $activeCount = RegistrationEntryCode::whereNull('consumed_at')->count();
            while ($activeCount < self::ACTIVE_CODE_COUNT) {
                $this->createCode();
                $activeCount++;
            }

            return $pool;
        });

        if ($pool->initial_notified_at) {
            return null;
        }

        if (config('mail.default') === 'log') {
            Log::warning('Initial BMS entry codes are not being emailed because the mailer is set to log.');

            return 'The mailer is currently set to log emails. Configure SMTP to send the registration codes to the system owner.';
        }

        $codes = RegistrationEntryCode::whereNull('consumed_at')
            ->orderBy('id')
            ->get()
            ->map(fn (RegistrationEntryCode $code) => $code->code_encrypted)
            ->all();

        try {
            Mail::to($this->notificationEmail())->send(new InitialRegistrationCodesMail($codes));

            $pool->forceFill(['initial_notified_at' => now()])->save();

            return null;
        } catch (Throwable $exception) {
            Log::error('Could not email the initial BMS registration codes.', [
                'recipient' => $this->notificationEmail(),
                'exception' => $exception->getMessage(),
            ]);

            return 'The registration codes could not be emailed to the system owner. Please check the mail configuration and try again.';
        }
    }

    /**
     * Consume one code and create its replacement in the same transaction.
     *
     * @param  callable(string): User  $createUser
     * @return array{user: User, used_code: string, replacement_code: string}
     */
    public function consumeAndReplace(string $submittedCode, callable $createUser): array
    {
        return DB::transaction(function () use ($submittedCode, $createUser) {
            RegistrationCodePool::query()->lockForUpdate()->findOrFail(1);

            $normalizedCode = strtoupper(trim($submittedCode));
            $entryCode = RegistrationEntryCode::where('code_hash', hash('sha256', $normalizedCode))
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->first();

            if (! $entryCode || ! hash_equals($entryCode->code_hash, hash('sha256', $normalizedCode))) {
                throw ValidationException::withMessages([
                    'entry_code' => 'This entry code is not valid or has already been used.',
                ]);
            }

            $user = $createUser($normalizedCode);
            $replacementCode = $this->createCode();

            $entryCode->forceFill([
                'consumed_at' => now(),
                'consumed_by' => $user->id,
            ])->save();

            return [
                'user' => $user,
                'used_code' => $normalizedCode,
                'replacement_code' => $replacementCode,
            ];
        });
    }

    private function createCode(): string
    {
        do {
            $code = RegistrationCode::generate();
            $hash = hash('sha256', $code);
        } while (RegistrationEntryCode::where('code_hash', $hash)->exists());

        RegistrationEntryCode::create([
            'code_hash' => $hash,
            'code_encrypted' => $code,
        ]);

        return $code;
    }

    private function notificationEmail(): string
    {
        return (string) config('bms.registration_notification_email');
    }
}
