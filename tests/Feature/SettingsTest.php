<?php

use App\Models\User;
use App\Settings\BarSettings;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Build a real (tiny) PNG upload.
 */
function logoUpload(string $name = 'logo.png'): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $path = tempnam(sys_get_temp_dir(), 'bmslogo').'.png';
    file_put_contents($path, $png);

    return new UploadedFile($path, $name, 'image/png', null, true);
}

it('renders the settings page with the full currency list', function () {
    $response = $this->get('/settings');

    $response->assertOk();
    $response->assertSee('Bar Information');
    $response->assertSee('Receipt Settings');

    // A representative sample of major currencies must be selectable.
    foreach (['TZS', 'USD', 'EUR', 'GBP', 'KES', 'JPY', 'ZAR', 'NGN', 'INR', 'AED'] as $code) {
        $response->assertSee('value="'.$code.'"', false);
    }
});

it('loads the signed-in user registration business and phone into bar settings', function () {
    $user = auth()->user();
    $user->update([
        'business_name' => 'Registration Business Name',
        'phone' => '+255 711 222 333',
        'business_address' => 'Registered Street, Moshi',
    ]);
    BarSettings::save([
        'name' => 'Old Saved Bar Name',
        'phone' => '+255 700 000 000',
        'address' => 'Old Saved Address',
        'receipt_header' => 'Old Receipt Header',
    ]);

    $this->get('/settings')
        ->assertOk()
        ->assertViewHas('settings.name', 'Registration Business Name')
        ->assertViewHas('settings.phone', '+255 711 222 333')
        ->assertViewHas('settings.address', 'Registered Street, Moshi')
        ->assertSee('Registration Business Name')
        ->assertSee('+255 711 222 333')
        ->assertSee('Registered Street, Moshi');

    expect(BarSettings::get('name'))->toBe('Registration Business Name')
        ->and(BarSettings::get('phone'))->toBe('+255 711 222 333')
        ->and(BarSettings::get('address'))->toBe('Registered Street, Moshi')
        ->and(BarSettings::get('receipt_header'))->toBe('Registration Business Name');
});

it('no longer renders the opening and closing time fields', function () {
    $response = $this->get('/settings');

    $response->assertOk();
    $response->assertDontSee('Opening Time');
    $response->assertDontSee('Closing Time');
});

it('saves the bar profile and receipt settings', function () {
    $response = $this->post('/settings', [
        'name' => 'Kilimanjaro Bar',
        'phone' => '+255 700 000 111',
        'address' => 'Samo, Arusha',
        'receipt_header' => 'KILIMANJARO BAR',
        'receipt_footer' => 'Karibu tena!',
        'currency' => 'KES',
    ]);

    $response->assertOk();
    $response->assertJson(['ok' => true]);

    $settings = BarSettings::all();

    expect($settings['name'])->toBe('Kilimanjaro Bar')
        ->and($settings['phone'])->toBe('+255 700 000 111')
        ->and($settings['address'])->toBe('Samo, Arusha')
        ->and($settings['receipt_header'])->toBe('Kilimanjaro Bar')
        ->and($settings['receipt_footer'])->toBe('Karibu tena!')
        ->and($settings['currency'])->toBe('KES');

    expect(auth()->user()->fresh()->business_name)->toBe('Kilimanjaro Bar')
        ->and(auth()->user()->fresh()->phone)->toBe('+255 700 000 111')
        ->and(auth()->user()->fresh()->business_address)->toBe('Samo, Arusha');

    expect(BarSettings::currency()['symbol'])->toBe('KSh');
});

it('always uses the bar name as the receipt header', function () {
    $this->postJson('/settings', [
        'name' => 'Registered Bar Name',
        'phone' => '+255 700 000 111',
        'address' => '',
        'receipt_header' => 'A Different Receipt Header',
        'receipt_footer' => 'Welcome back',
        'currency' => 'TZS',
    ])->assertOk();

    expect(BarSettings::get('receipt_header'))->toBe('Registered Bar Name');
});

it('rejects an unknown currency code', function () {
    $this->post('/settings', [
        'name' => 'Test Bar',
        'currency' => 'XXXX',
    ])->assertStatus(422)->assertJsonValidationErrors('currency');
});

it('requires a bar name', function () {
    $this->post('/settings', [
        'name' => '',
        'currency' => 'TZS',
    ])->assertStatus(422)->assertJsonValidationErrors('name');
});

it('uploads, serves and removes the bar logo', function () {
    $response = $this->post('/settings/logo', ['logo' => logoUpload()]);

    $response->assertOk();
    $response->assertJson(['ok' => true]);

    $logo = BarSettings::get('logo');
    expect($logo)->not->toBeNull();
    expect(BarSettings::logoPath())->toBeFile();

    // The logo is streamed back through the branding route.
    $this->get('/branding/logo')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    // Removing it clears the setting and the file on disk.
    $this->delete('/settings/logo')->assertOk()->assertJson(['ok' => true]);

    expect(BarSettings::get('logo'))->toBeNull();
    expect(BarSettings::logoPath())->toBeNull();
});

it('keeps the uploaded logo after the settings page is refreshed', function () {
    $this->post('/settings/logo', ['logo' => logoUpload('refresh-logo.png')])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $storedLogo = BarSettings::get('logo');
    expect($storedLogo)->not->toBeNull()
        ->and(BarSettings::logoUrl())->toContain('/branding/logo?v=');

    $this->get('/settings')
        ->assertOk()
        ->assertSee('/branding/logo', false);

    expect(BarSettings::get('logo'))->toBe($storedLogo)
        ->and(BarSettings::logoPath())->toBeFile();

    $this->get('/branding/logo')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('rejects a file that is not a real image', function () {
    $path = tempnam(sys_get_temp_dir(), 'bmsfake').'.png';
    file_put_contents($path, 'definitely not an image');

    $response = $this->post('/settings/logo', [
        'logo' => new UploadedFile($path, 'fake.png', 'image/png', null, true),
    ]);

    $response->assertStatus(422);
    expect(BarSettings::get('logo'))->toBeNull();

    @unlink($path);
});

it('exposes the currency catalogue to the application', function () {
    $codes = BarSettings::currencyCodes();

    expect(count($codes))->toBeGreaterThan(50);
    expect($codes)->toContain('TZS', 'USD', 'EUR', 'KES');
    expect(BarSettings::currency('EUR')['symbol'])->toBe('€');
    expect(BarSettings::currency('EUR')['decimals'])->toBe(2);
    expect(BarSettings::currency('JPY')['decimals'])->toBe(0);
});
