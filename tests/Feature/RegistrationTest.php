<?php

use App\Mail\InitialRegistrationCodesMail;
use App\Mail\NewRegistrationNotificationMail;
use App\Mail\RegistrationWelcomeMail;
use App\Models\RegistrationEntryCode;
use App\Models\User;
use App\Settings\BarSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('creates and emails six initial single-use entry codes', function () {
    $this->get('/register')->assertOk();

    expect(RegistrationEntryCode::whereNull('consumed_at')->count())->toBe(6);

    Mail::assertSent(InitialRegistrationCodesMail::class, function (InitialRegistrationCodesMail $mail) {
        return count($mail->codes) === 6
            && $mail->hasTo('wilsonwincheslaus722@gmail.com');
    });
});

it('consumes an entry code, replenishes the pool, and emails both parties', function () {
    $this->get('/register')->assertOk();
    $entryCode = RegistrationEntryCode::whereNull('consumed_at')->firstOrFail();
    $submittedCode = $entryCode->code_encrypted;

    $response = $this->postJson('/register', [
        'entry_code' => strtolower($submittedCode),
        'name' => 'Wilson Wincheslaus',
        'business_name' => 'Sunset Bar',
        'phone' => '+255 712 345 678',
        'email' => 'wilson@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertOk()
        ->assertJsonPath('ok', true);

    $user = User::where('email', 'wilson@example.com')->firstOrFail();
    $pin = $response->json('pin');

    expect(RegistrationEntryCode::whereNull('consumed_at')->count())->toBe(6)
        ->and($entryCode->fresh()->consumed_at)->not->toBeNull()
        ->and($user->pin)->toBe($pin)
        ->and(BarSettings::get('name'))->toBe('Sunset Bar')
        ->and(BarSettings::get('phone'))->toBe('+255 712 345 678')
        ->and(BarSettings::get('receipt_header'))->toBe('Sunset Bar');

    Mail::assertSent(NewRegistrationNotificationMail::class, function (NewRegistrationNotificationMail $mail) use ($user, $submittedCode) {
        return $mail->user->is($user)
            && $mail->usedEntryCode === $submittedCode
            && $mail->replacementEntryCode !== ''
            && $mail->hasTo('wilsonwincheslaus722@gmail.com');
    });
    Mail::assertSent(RegistrationWelcomeMail::class, function (RegistrationWelcomeMail $mail) use ($user, $pin) {
        return $mail->user->is($user)
            && $mail->signInCode === $pin
            && $mail->hasTo($user->email);
    });
});

it('rejects a consumed entry code without creating another account', function () {
    $this->get('/register')->assertOk();
    $entryCode = RegistrationEntryCode::whereNull('consumed_at')->firstOrFail();
    $data = [
        'entry_code' => $entryCode->code_encrypted,
        'name' => 'Wilson Wincheslaus',
        'business_name' => 'Sunset Bar',
        'phone' => '+255 712 345 678',
        'email' => 'wilson@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ];

    $this->postJson('/register', $data)->assertOk();
    auth()->logout();
    $data['email'] = 'another@example.com';

    $this->postJson('/register', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('entry_code');

    expect(User::count())->toBe(1)
        ->and(RegistrationEntryCode::whereNull('consumed_at')->count())->toBe(6);
});
