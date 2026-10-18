<?php

namespace JoshDaugherty\HomeDashboard\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateUserCommand extends Command
{
    protected $signature = 'home-dashboard:user';

    protected $description = 'Create or update the dashboard user from DASHBOARD_USER_* environment variables';

    public function handle(): int
    {
        $email = config('home-dashboard.user.email');
        $password = config('home-dashboard.user.password');

        if (! $email || ! $password) {
            $this->components->warn('DASHBOARD_USER_EMAIL / DASHBOARD_USER_PASSWORD not set; nothing to do.');

            return self::SUCCESS;
        }

        $model = config('auth.providers.users.model');

        $model::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('home-dashboard.user.name') ?: 'Family',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $this->components->info("Dashboard user {$email} is ready.");

        return self::SUCCESS;
    }
}
