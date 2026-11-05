<?php

namespace App\Settings;

use Illuminate\Support\Facades\File;

/**
 * Bar / business profile settings.
 *
 * Settings are persisted as a small JSON document in storage/app/settings so
 * they can be read by every module (POS, receipts, dashboard). The signed-in
 * user's business profile (name, phone and address) is mirrored to the users
 * table by SettingsController so it survives a cleared settings file.
 */
class BarSettings
{
    public const DEFAULTS = [
        'name' => 'The Barefoot Lounge',
        'phone' => '+255 123 456 789',
        'address' => '',
        'logo' => null,
        'receipt_header' => 'THE BAREFOOT LOUNGE',
        'receipt_footer' => 'Thank you for visiting!',
        'currency' => 'TZS',
    ];

    /**
     * Absolute path of the JSON document holding the settings.
     */
    public static function file(): string
    {
        return (string) config('settings.file', storage_path('app/settings/bar.json'));
    }

    /**
     * Absolute path of the folder holding uploaded branding files.
     */
    public static function brandingDirectory(): string
    {
        return (string) config('settings.branding_directory', storage_path('app/branding'));
    }

    /**
     * Every setting, merged over the defaults.
     */
    public static function all(): array
    {
        $settings = self::DEFAULTS;
        $path = self::file();

        if (is_file($path)) {
            $stored = json_decode((string) @file_get_contents($path), true);

            if (is_array($stored)) {
                $settings = array_merge($settings, array_intersect_key($stored, self::DEFAULTS));
            }
        }

        return $settings;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /**
     * Persist the given settings, ignoring unknown keys.
     */
    public static function save(array $data): array
    {
        $settings = array_merge(self::all(), array_intersect_key($data, self::DEFAULTS));

        $directory = dirname(self::file());

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            self::file(),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return $settings;
    }

    /**
     * The currency definition for the given (or configured) currency code.
     */
    public static function currency(?string $code = null): array
    {
        $code = $code ?: (self::get('currency') ?: config('currencies.default'));

        foreach (self::currencies() as $currency) {
            if ($currency['code'] === $code) {
                return $currency;
            }
        }

        return self::currencies()[0] ?? [
            'code' => 'TZS',
            'symbol' => 'TSh',
            'name' => 'Tanzanian Shilling',
            'decimals' => 0,
        ];
    }

    /**
     * Flat list of every supported currency.
     *
     * @return array<int, array{code:string,symbol:string,name:string,decimals:int}>
     */
    public static function currencies(): array
    {
        $currencies = [];

        foreach ((array) config('currencies.groups', []) as $group) {
            foreach ((array) $group as $currency) {
                $currencies[] = $currency;
            }
        }

        return $currencies;
    }

    /**
     * Every supported currency grouped by region (for <optgroup> rendering).
     */
    public static function currencyGroups(): array
    {
        return (array) config('currencies.groups', []);
    }

    /**
     * @return array<int, string>
     */
    public static function currencyCodes(): array
    {
        return array_column(self::currencies(), 'code');
    }

    /**
     * The short symbol of the active currency.
     */
    public static function currencySymbol(): string
    {
        return self::currency()['symbol'];
    }

    /**
     * Absolute path of the uploaded logo, when one exists.
     */
    public static function logoPath(): ?string
    {
        $logo = self::get('logo');

        if (! $logo) {
            return null;
        }

        $path = self::brandingDirectory().DIRECTORY_SEPARATOR.basename($logo);

        return is_file($path) ? $path : null;
    }

    /**
     * Public URL of the uploaded logo, when one exists.
     */
    public static function logoUrl(): ?string
    {
        $path = self::logoPath();

        if (! $path) {
            return null;
        }

        // Version the public URL so a refreshed page never reuses a cached
        // response from before the logo was uploaded or replaced.
        return route('branding.logo').'?v='.(@filemtime($path) ?: 1);
    }

    /**
     * Remember the uploaded logo file name.
     */
    public static function setLogo(?string $filename): void
    {
        self::save(['logo' => $filename]);
    }

    /**
     * Delete the uploaded logo from disk and clear the setting.
     */
    public static function deleteLogo(): void
    {
        $path = self::logoPath();

        if ($path) {
            File::delete($path);
        }

        self::setLogo(null);
    }
}
