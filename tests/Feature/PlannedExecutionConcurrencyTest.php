<?php

namespace Tests\Feature;

use App\Models\PlannedStudySession;
use App\Models\StudyRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PlannedExecutionConcurrencyTest extends TestCase
{
    /** @return array<string, array{bool}> */
    public static function submissions(): array
    {
        return ['duplicate submission' => [true], 'independent executions' => [false]];
    }

    #[DataProvider('submissions')]
    public function test_parallel_executions_preserve_existing_studies_and_prevent_duplicates(bool $sameKey): void
    {
        if (getenv('PLANNED_EXECUTION_MYSQL') !== '1') {
            $this->markTestSkipped('Enable PLANNED_EXECUTION_MYSQL=1 with local MySQL database creation permissions.');
        }
        $mysql = config('database.connections.mysql');
        $this->assertContains($mysql['host'], ['127.0.0.1', 'localhost', '::1']);
        $this->assertTrue($this->app->environment('testing'));
        $database = 'study_tracker_execution_test_'.bin2hex(random_bytes(8));
        config(['database.connections.execution_admin' => [...$mysql, 'database' => 'mysql', 'url' => null]]);
        $admin = DB::connection('execution_admin');
        $admin->statement('CREATE DATABASE `'.$database.'`');
        $originalDefault = config('database.default');
        $testConfig = [...$mysql, 'database' => $database, 'url' => null];
        $workers = [];

        try {
            config(['database.connections.execution_test' => $testConfig, 'database.default' => 'execution_test']);
            Artisan::call('migrate', ['--database' => 'execution_test', '--force' => true, '--no-interaction' => true]);
            $session = PlannedStudySession::factory()->create();
            $user = $session->user;
            $date = CarbonImmutable::now($user->timezone)->toDateString();
            $record = StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => $date, 'studied' => true, 'minutes' => 15]);
            $originalItem = $record->items()->firstOrFail();
            $connection = DB::connection('execution_test');
            $connection->beginTransaction();
            $connection->table('users')->where('id', $user->id)->lockForUpdate()->first();
            $key = (string) Str::uuid();
            $code = <<<'PHP'
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(["database.default" => "execution_test", "database.connections.execution_test" => json_decode(stream_get_contents(STDIN), true)]);
$session = App\Models\PlannedStudySession::findOrFail((int) $argv[1]);
$user = $session->user;
echo "ready\n";
flush();
$result = app(App\Services\PlannedExecutionService::class)->record($user, $session, [
    "study_date" => Carbon\CarbonImmutable::now($user->timezone)->toDateString(),
    "actual_minutes" => 20, "submission_key" => $argv[2],
]);
echo $result["created"] ? "created\n" : "repeated\n";
PHP;
            for ($index = 0; $index < 2; $index++) {
                $workerKey = $sameKey ? $key : (string) Str::uuid();
                $worker = new Process([PHP_BINARY, '-r', $code, (string) $session->id, $workerKey], base_path(), ['APP_ENV' => 'testing'], json_encode($testConfig, JSON_THROW_ON_ERROR), 60);
                $workers[] = $worker;
                $worker->start();
                $worker->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'ready'));
                $this->assertTrue($worker->isRunning());
            }
            $connection->commit();
            $outcomes = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $outcomes[] = trim(str_replace("ready\n", '', str_replace("\r\n", "\n", $worker->getOutput())));
            }
            sort($outcomes);
            $this->assertSame($sameKey ? ['created', 'repeated'] : ['created', 'created'], $outcomes);
            $this->assertSame($sameKey ? 35 : 55, $record->fresh()->minutes);
            $this->assertSame($sameKey ? 2 : 3, $record->items()->count());
            $this->assertSame(15, $originalItem->fresh()->minutes);
            $this->assertSame(1, $user->studyRecords()->count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            if (isset($connection) && $connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            DB::purge('execution_test');
            config(['database.default' => $originalDefault]);
            $admin->statement('DROP DATABASE `'.$database.'`');
            DB::purge('execution_admin');
        }
    }
}
