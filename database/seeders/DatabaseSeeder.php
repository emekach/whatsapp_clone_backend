<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo user 1
        User::create([
            'name'     => 'John Doe',
            'phone'    => '+2341234567890',
            'password' => Hash::make('password'),
            'about'    => 'Hey there! I am using WhatsApp.',
        ]);

        // Demo user 2
        User::create([
            'name'     => 'Jane Smith',
            'phone'    => '+2349876543210',
            'password' => Hash::make('password'),
            'about'    => 'Available',
        ]);

        echo "✅ Demo users created\n";
        echo "   User 1: +2341234567890 / password\n";
        echo "   User 2: +2349876543210 / password\n";
    }
}