<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Static module pages.
 *
 * These used to be route closures. They live in a controller so that
 * `php artisan route:cache` works in production (closures cannot be cached).
 */
class PageController extends Controller
{
    public function noAccess(): View
    {
        return view('pages.no-access', ['activeNav' => '']);
    }

    public function orders(): View
    {
        return view('pages.orders', ['activeNav' => 'orders']);
    }

    public function tables(): View
    {
        return view('pages.tables', ['activeNav' => 'tables']);
    }

    public function customers(): View
    {
        return view('pages.customers', ['activeNav' => 'customers']);
    }

    public function dashboardFallback(string $page): View
    {
        return view('pages.dashboard', ['activeNav' => $page]);
    }
}
