<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DEV/TEST ACCOUNTS ONLY.
 *
 * Throwaway logins to exercise Auth::attempt() + role middleware locally.
 * Passwords are plain "password123" on purpose (local dev convenience) —
 * DO NOT reuse these outside local development, and delete/rotate before
 * anything resembling a real deployment.
 *
 * Depends on SubjectSeeder having run first (attaches the faculty test
 * account to a few subjects so dashboard.faculty has something to show).
 *
 * Run standalone: php artisan db:seed --class=UserSeeder
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Test Admin', 'email' => 'admin@test.syllabihub', 'password' => 'password123', 'role' => 'admin'],
            ['name' => 'Test Faculty', 'email' => 'faculty@test.syllabihub', 'password' => 'password123', 'role' => 'faculty'],
            ['name' => 'Test Intern', 'email' => 'intern@test.syllabihub', 'password' => 'password123', 'role' => 'intern'],
        ];

        foreach ($users as $data) {
            // User::$casts hashes 'password' automatically on save.
            User::updateOrCreate(['email' => $data['email']], $data);
        }

        $faculty = User::where('email', 'faculty@test.syllabihub')->first();
        $someSubjectIds = Subject::query()->orderBy('id')->limit(3)->pluck('id');

        if ($faculty && $someSubjectIds->isNotEmpty()) {
            $faculty->subjects()->syncWithoutDetaching($someSubjectIds);
        }

        $this->command?->info('Seeded ' . count($users) . ' test accounts (admin/faculty/intern), password: password123');
    }
}
