<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // bootstrap/app.php registers schedule tasks via Console\Application::starting(),
        // which only fires once the console application boots. RefreshDatabase skips
        // the migration (and thus the console boot) on tests after the first one,
        // so boot Artisan explicitly before asserting on the schedule.
        Artisan::call('inspire');
    }

    public function test_nightly_backup_scheduled_with_rclone_upload_and_retention(): void
    {
        $backup = collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->getSummaryForDisplay())
            ->first(fn ($summary) => str_contains($summary, 'app:backup'));

        $this->assertNotNull($backup, 'app:backup must be registered on the scheduler');
        $this->assertStringContainsString('--db-only', $backup);
        $this->assertStringContainsString('--rclone', $backup);
        $this->assertStringContainsString('--keep-days=14', $backup);
    }

    public function test_all_scheduled_tasks_log_output_to_scheduler_log(): void
    {
        $expected = storage_path('logs/scheduler.log');
        $events = app(Schedule::class)->events();

        $this->assertNotEmpty($events, 'schedule must contain registered events');

        foreach ($events as $event) {
            $this->assertSame(
                $expected,
                $event->output,
                "Event [{$event->getSummaryForDisplay()}] must append output to scheduler.log"
            );
        }
    }
}
