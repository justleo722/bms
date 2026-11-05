<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Generate HTTPS links when the application is served over TLS, even
        // when it sits behind a proxy/load balancer.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        // Never allow `migrate:fresh`, `db:wipe`, etc. to run in production.
        if ($this->app->isProduction()) {
            DB::prohibitDestructiveCommands();
        }

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
