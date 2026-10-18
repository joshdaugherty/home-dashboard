<?php

namespace JoshDaugherty\HomeDashboard\Tests\Feature;

use JoshDaugherty\HomeDashboard\Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    public function test_does_nothing_when_env_is_unset(): void
    {
        config(['home-dashboard.user.email' => null, 'home-dashboard.user.password' => null]);

        $this->artisan('home-dashboard:user')->assertSuccessful();
    }
}
