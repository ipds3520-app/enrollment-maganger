<?php

namespace Tests;

use App\Models\UserIntern;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(UserContract $user, $guard = null)
    {
        return parent::actingAs($user, $guard ?? $this->guardFor($user));
    }

    public function assertAuthenticatedAs($user, $guard = null)
    {
        return parent::assertAuthenticatedAs($user, $guard ?? $this->guardFor($user));
    }

    public function assertGuest($guard = null)
    {
        foreach ($guard === null ? ['admin', 'operator', 'intern'] : [$guard] as $name) {
            parent::assertGuest($name);
        }

        return $this;
    }

    private function guardFor(UserContract $user): string
    {
        if ($user instanceof UserIntern) {
            return 'intern';
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return 'admin';
        }

        return method_exists($user, 'isOperator') && $user->isOperator() ? 'operator' : 'web';
    }
}
