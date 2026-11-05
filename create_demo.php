<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// This script deletes every user. Refuse to run anywhere except local
// development so it can never be triggered against a production database.
if (! $app->environment('local')) {
    fwrite(STDERR, "Refusing to run: create_demo.php destroys all users and is for local development only.\n");
    exit(1);
}

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Clear existing users
User::truncate();

$user = User::create([
    'name' => 'Demo Admin',
    'business_name' => 'Demo Bar & Restaurant',
    'email' => 'demo@bms.com',
    'phone' => '+1234567890',
    'role' => 'administrator',
    'pin' => '1234',
    'password' => Hash::make('password123'),
]);

echo "User created successfully!\n";
echo "Name: " . $user->name . "\n";
echo "Email: " . $user->email . "\n";
echo "PIN (Login Code): " . $user->pin . "\n";
echo "\nYou can login with PIN: 1234\n";
