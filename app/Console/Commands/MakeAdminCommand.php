<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Promote an existing user to admin, or create a new admin account.
 *
 *   php artisan aroma:make-admin admin@aroma.sa
 *   php artisan aroma:make-admin admin@aroma.sa --name="Store Owner" --password=secret
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'aroma:make-admin
                            {email : The admin email address}
                            {--name=Administrator : Name for a newly created admin}
                            {--password= : Password (required when creating a new user)}
                            {--role=admin : admin|staff}';

    protected $description = 'Grant a user back-office (admin/staff) access, creating them if needed';

    public function handle(): int
    {
        $email = $this->argument('email');
        $role  = $this->option('role') === User::ROLE_STAFF ? User::ROLE_STAFF : User::ROLE_ADMIN;

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['role' => $role]);
            $this->info("Existing user {$email} is now '{$role}'.");

            return self::SUCCESS;
        }

        $password = $this->option('password');
        if (! $password) {
            $this->error('User not found. Pass --password to create a new admin account.');

            return self::FAILURE;
        }

        User::create([
            'name'              => $this->option('name'),
            'email'             => $email,
            'password'          => Hash::make($password),
            'email_verified_at' => now(),
            'is_active'         => true,
            'role'              => $role,
        ]);

        $this->info("Created new {$role}: {$email}");

        return self::SUCCESS;
    }
}
