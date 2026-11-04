<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Blade::directive('heroicon', function (string $expression) {
            $parts = explode(',', $expression, 2);
            $name = trim($parts[0]);
            $class = isset($parts[1]) ? trim($parts[1]) : "''";

            if (str_starts_with($name, "'") || str_starts_with($name, "\"")) {
                $name = "{$name}";
                return "<?php echo e(svg('heroicon-' . {$name}, {$class})); ?>";
            }

            return "<?php echo e(svg('heroicon-' . ({$name}), {$class})); ?>";
        });
    }
}
