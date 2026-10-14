<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GoalConcurrencyTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function metricsAndPeriods(): array
    {
        return ['legacy days' => ['weekly', 'days'], 'daily time' => ['daily', 'minutes'], 'monthly time' => ['monthly', 'minutes']];
    }

    #[DataProvider('metricsAndPeriods')]
    public function test_parallel_creations_serialize_and_only_one_overlapping_goal_is_saved(string $period, string $metric): void
    {
        if (getenv('GOAL_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Enable GOAL_MYSQL_CONCURRENCY=1 with local MySQL database creation permissions.');
        }

        $mysql = config('database.connections.mysql');
        $this->assertContains($mysql['host'], ['127.0.0.1', 'localhost', '::1']);
        $this->assertTrue($this->app->environment('testing'));
        $database = 'study_tracker_goal_test_'.bin2hex(random_bytes(8));
        $adminConfig = [...$mysql, 'database' => 'mysql', 'url' => null];
        $testConfig = [...$mysql, 'database' => $database, 'url' => null];
        config(['database.connections.goal_test_admin' => $adminConfig]);
        $admin = DB::connection('goal_test_admin');
        $admin->statement('CREATE DATABASE `'.$database.'`');
        $originalDefault = config('database.default');
        $workers = [];

        try {
            config(['database.connections.goal_concurrency' => $testConfig, 'database.default' => 'goal_concurrency']);
            Artisan::call('migrate', [
                '--database' => 'goal_concurrency', '--force' => true, '--no-interaction' => true,
                '--path' => ['database/migrations/0001_01_01_000000_create_users_table.php', 'database/migrations/2026_09_05_013847_create_goals_table.php'],
            ]);
            $legacyUser = User::factory()->create();
            $legacyId = DB::table('goals')->insertGetId([
                'user_id' => $legacyUser->id, 'type' => 'weekly', 'target_days' => 4,
                'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18', 'active' => true,
            ]);
            Artisan::call('migrate', ['--database' => 'goal_concurrency', '--force' => true, '--no-interaction' => true]);
            $legacyGoal = DB::table('goals')->where('id', $legacyId)->first();
            $this->assertSame('days', $legacyGoal->metric);
            $this->assertSame(4, $legacyGoal->target_days);
            $this->assertNull($legacyGoal->target_minutes);
            $user = User::factory()->create();
            $connection = DB::connection('goal_concurrency');
            $connection->beginTransaction();
            $connection->table('users')->where('id', $user->id)->lockForUpdate()->first();

            $code = <<<'PHP'
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(["database.default" => "goal_concurrency", "database.connections.goal_concurrency" => json_decode(stream_get_contents(STDIN), true)]);
$user = App\Models\User::findOrFail((int) $argv[1]);
$period = $argv[2];
$metric = $argv[3];
$today = Carbon\CarbonImmutable::now($user->timezone)->startOfDay();
$start = match ($period) { "daily" => $today, "monthly" => $today->startOfMonth(), default => $today->startOfWeek(Carbon\CarbonInterface::MONDAY) };
$end = match ($period) { "daily" => $start, "monthly" => $start->endOfMonth(), default => $start->addDays(6) };
echo "ready\n";
flush();
try {
    app(App\Services\GoalService::class)->save($user, ["type" => $period, "metric" => $metric, "target_days" => 4, "target_minutes" => 90, "starts_on" => $start->toDateString(), "ends_on" => $end->toDateString()]);
    echo "created\n";
} catch (Illuminate\Validation\ValidationException $exception) {
    echo isset($exception->errors()["starts_on"]) ? "conflict\n" : "unexpected\n";
}
PHP;

            for ($index = 0; $index < 2; $index++) {
                $worker = new Process([PHP_BINARY, '-r', $code, (string) $user->id, $period, $metric], base_path(), ['APP_ENV' => 'testing'], json_encode($testConfig, JSON_THROW_ON_ERROR), 20);
                $workers[] = $worker;
                $worker->start();
                $worker->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'ready'));
                $this->assertTrue($worker->isRunning(), 'The worker must wait for the user lock.');
            }

            $connection->commit();
            $outcomes = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $outcomes[] = trim(str_replace("ready\n", '', str_replace("\r\n", "\n", $worker->getOutput())));
            }
            sort($outcomes);
            $this->assertSame(['conflict', 'created'], $outcomes);
            $this->assertSame(1, $connection->table('goals')->where('user_id', $user->id)->count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            if (isset($connection) && $connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            DB::purge('goal_concurrency');
            config(['database.default' => $originalDefault]);
            $admin->statement('DROP DATABASE `'.$database.'`');
            DB::purge('goal_test_admin');
        }
    }
}
