<?php

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BookingManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class ConcurrencyTestFailure extends RuntimeException
{
}

class InjectedBookingFailure extends RuntimeException
{
}

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new ConcurrencyTestFailure($message);
    }
}

function awaitCondition(callable $condition, string $message, array $processes = []): void
{
    $deadline = microtime(true) + 20;
    while (! $condition()) {
        foreach ($processes as $process) {
            check($process->isRunning(), 'A worker exited before the synchronization point: '.$message);
        }
        check(microtime(true) < $deadline, 'Synchronization timed out: '.$message);
        usleep(20000);
    }
}

function counts(): array
{
    return array_map(fn ($table) => DB::table($table)->count(), ['bookings', 'booking_services', 'booking_status_histories']);
}

function fixture(string $scenario): array
{
    return DB::transaction(function () use ($scenario): array {
        $tag = Str::lower(Str::random(16));
        $users = [];
        for ($i = 0; $i < 2; $i++) {
            $user = User::create(['full_name' => 'Concurrency test', 'email' => $tag.$i.'@example.test',
                'password_hash' => password_hash(Str::random(32), PASSWORD_BCRYPT)]);
            $user->customerProfile()->create();
            $users[] = $user->id;
        }
        $owner = DB::table('business_owner_profiles')->insertGetId(['user_id' => $users[0]]);
        $business = Business::create(['owner_id' => $owner, 'name' => 'Concurrency test', 'slug' => $tag, 'status' => 'ACTIVE']);
        $category = ServiceCategory::create(['business_id' => $business->id, 'name' => 'Test', 'slug' => $tag]);
        $voucher = Voucher::create(['business_id' => $business->id, 'code' => strtoupper($tag), 'name' => 'Last test use',
            'discount_type' => 'FIXED_AMOUNT', 'discount_value' => '10.00', 'min_order_value' => '0.00',
            'total_quantity' => 1, 'used_quantity' => 0, 'max_usage_per_customer' => 1,
            'start_date' => now()->subDay(), 'end_date' => now()->addWeek(), 'status' => 'ACTIVE']);
        $date = now('Asia/Ho_Chi_Minh')->addDays(3);
        $branches = $staffIds = $serviceIds = [];
        for ($i = 0; $i < ($scenario === 'staff-slot' ? 1 : 2); $i++) {
            $branch = Branch::create(['business_id' => $business->id, 'name' => 'Concurrent branch '.$i,
                'status' => 'ACTIVE', 'operational_status' => 'ACTIVE', 'booking_confirmation_mode' => 'AUTO_CONFIRMATION']);
            $service = new Service(['category_id' => $category->id, 'name' => 'Test service', 'price' => '100.00',
                'duration_minutes' => 60, 'status' => 'ACTIVE', 'bookable' => true]);
            $service->business_id = $business->id;
            $branch->services()->save($service);
            $staff = $branch->staff()->create(['full_name' => 'Only worker', 'status' => 'ACTIVE', 'is_bookable' => true]);
            $staff->services()->attach($service->id);
            DB::table('branch_working_hours')->insert(['branch_id' => $branch->id, 'day_of_week' => $date->dayOfWeek,
                'open_time' => '08:00:00', 'close_time' => '18:00:00']);
            $staff->hours()->create(['day_of_week' => $date->dayOfWeek, 'start_time' => '08:00:00', 'end_time' => '18:00:00', 'is_off' => false]);
            $branches[] = $branch->id;
            $staffIds[] = $staff->id;
            $serviceIds[] = $service->id;
        }
        $requests = [];
        for ($i = 0; $i < 2; $i++) {
            $index = $scenario === 'staff-slot' ? 0 : $i;
            $requests[] = ['user' => $users[$i], 'branch' => $branches[$index], 'data' => [
                'date' => $date->toDateString(), 'time' => '10:00', 'service_ids' => [$serviceIds[$index]],
                'staff_id' => $staffIds[$index], 'voucher_code' => $voucher->code, 'request_token' => (string) Str::uuid(),
            ]];
        }
        return ['business' => $business->id, 'owner' => $owner, 'users' => $users, 'branches' => $branches,
            'voucher' => $voucher->id, 'scenario' => $scenario, 'requests' => $requests];
    });
}

function createBooking(array $request): Booking
{
    return app(BookingManager::class)->create(User::findOrFail($request['user']), Branch::findOrFail($request['branch']), $request['data']);
}

function verifyRollback(array $fixture): void
{
    $before = counts();
    $armed = true;
    DB::listen(function (QueryExecuted $query) use (&$armed, $fixture, $before): void {
        if ($armed && str_starts_with(strtolower($query->sql), 'insert into `booking_status_histories`')) {
            $armed = false;
            check(DB::transactionLevel() === 1, 'The injected failure must happen inside the booking transaction.');
            check(counts() === array_map(fn ($count) => $count + 1, $before), 'Booking, item and history must exist before the injected failure.');
            check((int) Voucher::findOrFail($fixture['voucher'])->used_quantity === 1, 'Voucher must be reserved before the injected failure.');
            throw new InjectedBookingFailure('Fail after all booking writes.');
        }
    });
    try {
        createBooking($fixture['requests'][0]);
        throw new ConcurrencyTestFailure('The injected failure did not run.');
    } catch (InjectedBookingFailure) {
        check(! $armed, 'The failure point was not reached.');
    } finally {
        $armed = false;
    }
    check(DB::transactionLevel() === 0, 'The failed transaction was not closed.');
    check(counts() === $before, 'Rollback left booking, item or history rows.');
    check((int) Voucher::findOrFail($fixture['voucher'])->used_quantity === 0, 'Rollback left a consumed voucher use.');
    check(! Booking::withTrashed()->where('request_token', $fixture['requests'][0]['data']['request_token'])->exists(), 'Rollback left the request token.');
}

function worker(string $dir, int $index): void
{
    $fixture = json_decode(file_get_contents($dir.'/fixture.json'), true, 512, JSON_THROW_ON_ERROR);
    $target = $fixture['scenario'] === 'staff-slot' ? 'branches' : 'vouchers';
    $armed = true;
    // Pause only the test worker, immediately after the production SELECT ... FOR UPDATE.
    DB::listen(function (QueryExecuted $query) use ($dir, $index, $target, &$armed): void {
        if ($armed && str_contains(strtolower($query->sql), 'from `'.$target.'`') && str_ends_with(strtolower($query->sql), 'for update')) {
            $armed = false;
            file_put_contents($dir.'/locked-'.$index, 'locked');
            if ($index === 0) {
                awaitCondition(fn () => is_file($dir.'/release'), 'release the first row lock');
            }
        }
    });
    $connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    DB::statement('SET SESSION innodb_lock_wait_timeout = 30');
    file_put_contents($dir.'/ready-'.$index, (string) $connectionId);
    awaitCondition(fn () => is_file($dir.'/go-'.$index), 'start worker '.$index);
    try {
        $booking = createBooking($fixture['requests'][$index]);
        for ($i = 0; $i < 2; $i++) {
            check(createBooking($fixture['requests'][$index])->id === $booking->id, 'Token replay returned a different booking.');
        }
        $result = ['result' => 'created', 'id' => $booking->id, 'replays' => 2];
    } catch (ValidationException $exception) {
        $result = ['result' => 'conflict', 'fields' => array_keys($exception->errors())];
    }
    file_put_contents($dir.'/result-'.$index.'.json', json_encode($result, JSON_THROW_ON_ERROR));
}

function cleanup(array $fixture): void
{
    DB::transaction(function () use ($fixture): void {
        $ids = DB::table('bookings')->whereIn('branch_id', $fixture['branches'])->pluck('id');
        DB::table('booking_status_histories')->whereIn('booking_id', $ids)->delete();
        DB::table('booking_services')->whereIn('booking_id', $ids)->delete();
        DB::table('bookings')->whereIn('id', $ids)->delete();
        DB::table('vouchers')->where('id', $fixture['voucher'])->delete();
        DB::table('staff_profiles')->whereIn('branch_id', $fixture['branches'])->delete();
        DB::table('services')->whereIn('branch_id', $fixture['branches'])->delete();
        DB::table('branches')->whereIn('id', $fixture['branches'])->delete();
        DB::table('service_categories')->where('business_id', $fixture['business'])->delete();
        DB::table('businesses')->where('id', $fixture['business'])->delete();
        DB::table('business_owner_profiles')->where('id', $fixture['owner'])->delete();
        DB::table('users')->whereIn('id', $fixture['users'])->delete();
    });
}

function runScenario(string $scenario): void
{
    $fixture = fixture($scenario);
    $dir = storage_path('framework/testing/concurrency-'.Str::uuid());
    mkdir($dir, 0700, true);
    $processes = [];
    try {
        verifyRollback($fixture);
        $before = counts();
        file_put_contents($dir.'/fixture.json', json_encode($fixture, JSON_THROW_ON_ERROR));
        for ($i = 0; $i < 2; $i++) {
            $command = [PHP_BINARY];
            if (php_ini_loaded_file()) {
                array_push($command, '-c', php_ini_loaded_file());
            }
            array_push($command, __FILE__, '--worker', $dir, (string) $i);
            $processes[$i] = new Process($command, base_path(), null, null, 60);
            $processes[$i]->start();
        }
        awaitCondition(fn () => is_file($dir.'/ready-0') && is_file($dir.'/ready-1'), 'both worker connections ready', $processes);
        $blocking = (int) file_get_contents($dir.'/ready-0');
        $waiting = (int) file_get_contents($dir.'/ready-1');
        check($blocking !== $waiting, 'Workers must use separate MySQL connections.');
        file_put_contents($dir.'/go-0', 'go');
        awaitCondition(fn () => is_file($dir.'/locked-0'), 'first worker holds the row lock', $processes);
        file_put_contents($dir.'/go-1', 'go');
        // Require server-side evidence of a real lock wait before releasing worker 0.
        awaitCondition(fn () => DB::selectOne(
            'SELECT 1 AS waiting FROM performance_schema.data_lock_waits w
             JOIN performance_schema.threads r ON r.THREAD_ID = w.REQUESTING_THREAD_ID
             JOIN performance_schema.threads b ON b.THREAD_ID = w.BLOCKING_THREAD_ID
             WHERE r.PROCESSLIST_ID = ? AND b.PROCESSLIST_ID = ? LIMIT 1',
            [$waiting, $blocking]
        ) !== null, 'MySQL reports worker 1 waiting for worker 0', $processes);
        check(! is_file($dir.'/locked-1'), 'The second worker acquired the row lock before release.');
        file_put_contents($dir.'/release', 'release');
        foreach ($processes as $process) {
            $process->wait();
            check($process->isSuccessful(), 'A worker failed; no concurrency result can be claimed.');
        }
        $winner = json_decode(file_get_contents($dir.'/result-0.json'), true, 512, JSON_THROW_ON_ERROR);
        $loser = json_decode(file_get_contents($dir.'/result-1.json'), true, 512, JSON_THROW_ON_ERROR);
        check($winner['result'] === 'created' && $winner['replays'] === 2, 'The lock holder must create one booking and replay the same token.');
        $field = $scenario === 'staff-slot' ? 'time' : 'voucher_code';
        check($loser['result'] === 'conflict' && $loser['fields'] === [$field], 'The losing request must fail for '.$field.'.');
        check(is_file($dir.'/locked-1'), 'The losing worker never resumed its locking query.');
        check(counts() === array_map(fn ($count) => $count + 1, $before), 'The race or replay left extra bookings, items or histories.');
        check(DB::table('bookings')->whereIn('branch_id', $fixture['branches'])->count() === 1, 'The race persisted multiple bookings.');
        $booking = Booking::findOrFail($winner['id']);
        check($booking->items()->count() === 1 && (int) $booking->items()->first()->staff_id === $fixture['requests'][0]['data']['staff_id'], 'The winning booking must use the requested staff exactly once.');
        check($booking->appointment_start_time === '10:00:00' && $booking->appointment_end_time === '11:00:00', 'The winning booking must retain the requested interval.');
        check((int) $booking->voucher_id === $fixture['voucher'] && (string) $booking->final_amount === '90.00', 'The winning voucher discount was not persisted correctly.');
        check(! Booking::withTrashed()->where('request_token', $fixture['requests'][1]['data']['request_token'])->exists(), 'The losing request left a booking/token.');
        $voucher = Voucher::findOrFail($fixture['voucher']);
        check((int) $voucher->used_quantity === 1 && (int) $voucher->total_quantity === 1, 'The race or replay consumed more than the last voucher use.');
        echo 'PASS '.$scenario.": two processes, observed MySQL row-lock wait, one booking, one conflict, two token replays, late rollback verified.\n";
    } finally {
        // Stop workers before deleting their own fixture; never truncate/reset the database.
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        cleanup($fixture);
        foreach (glob($dir.'/*') as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
}

try {
    require __DIR__.'/mysql-concurrency-bootstrap.php';
    if (($argv[1] ?? '') === '--migrate') {
        check(Artisan::call('migrate', ['--force' => true]) === 0, 'Test database migrations failed.');
        echo "PASS: migrated the protected glowbook_test database.\n";
        exit(0);
    }
    $tables = ['bookings', 'booking_services', 'booking_status_histories', 'branches', 'vouchers'];
    $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', 'glowbook_test')->whereIn('TABLE_NAME', $tables)->pluck('ENGINE', 'TABLE_NAME');
    check($engines->count() === count($tables) && $engines->every(fn ($engine) => $engine === 'InnoDB'), 'Run --migrate first; all contested/transactional tables must use InnoDB.');
    check((int) DB::selectOne('SELECT @@performance_schema AS enabled')->enabled === 1, 'Enable performance_schema to verify actual lock contention.');
    DB::select('SELECT REQUESTING_THREAD_ID FROM performance_schema.data_lock_waits LIMIT 0');
    DB::select('SELECT THREAD_ID, PROCESSLIST_ID FROM performance_schema.threads LIMIT 0');
    if (($argv[1] ?? '') === '--worker') {
        worker($argv[2], (int) $argv[3]);
        exit(0);
    }
    check(($argv[1] ?? '') === '', 'Supported options: --migrate, or no option to run the suite.');
    check((int) DB::selectOne("SELECT GET_LOCK('glowbook_mysql_concurrency_suite', 0) AS acquired")->acquired === 1, 'Another concurrency suite is using this dedicated database.');
    try {
        runScenario('staff-slot');
        runScenario('voucher-last-use');
    } finally {
        DB::selectOne("SELECT RELEASE_LOCK('glowbook_mysql_concurrency_suite')");
    }
} catch (Throwable $exception) {
    // Do not echo connection errors/SQL/env: they can contain credentials or private values.
    $message = $exception instanceof ConcurrencyTestFailure ? $exception->getMessage() : 'Connection or harness failure ('.$exception::class.'); check the dedicated test setup.';
    fwrite(STDERR, 'FAIL: '.$message."\n");
    exit(1);
}
