<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

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
