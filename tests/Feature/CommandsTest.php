<?php

namespace Tests\Feature;

use Tests\TestCase;

class CommandsTest extends TestCase
{
    public function test_install_command_is_registered(): void
    {
        $this->artisan('rating-kit:install')->assertSuccessful();
    }

    public function test_decay_command_is_safe_when_decay_is_disabled(): void
    {
        $this->artisan('rating-kit:decay')
            ->expectsOutputToContain('Decayed 0 rating(s).')
            ->assertSuccessful();
    }
}
