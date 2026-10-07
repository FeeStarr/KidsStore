<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ConsoleCommandsRegistrationTest extends TestCase
{
    public function test_every_command_class_loads_and_is_concrete(): void
    {
        $files = glob(app_path('Console/Commands/*.php'));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $class = 'App\\Console\\Commands\\' . basename($file, '.php');

            try {
                $reflection = new \ReflectionClass($class);
            } catch (\Throwable $e) {
                $this->fail(basename($file) . ' failed to load: ' . $e->getMessage());
            }

            $this->assertTrue(
                $reflection->isSubClassOf(Command::class) && ! $reflection->isAbstract(),
                basename($file) . ' is not a concrete Artisan command'
            );
        }
    }

    public function test_pickups_check_expired_command_is_registered(): void
    {
        Artisan::call('pickups:check-expired');

        $this->assertStringContainsString('4-day pickup window', Artisan::output());
    }

    public function test_pickups_send_reminders_command_is_registered(): void
    {
        Artisan::call('pickups:send-reminders');

        $this->assertStringContainsString('pickup reminder', Artisan::output());
    }
}
