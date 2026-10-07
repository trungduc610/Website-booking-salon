<?php

// Run only against the isolated glowbook_test database. Never point this at production.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Branch;
use App\Models\Business;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\BookingManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

if (getenv('GLOWBOOK_CONCURRENCY_TEST') !== '1' || DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'glowbook_test') {
    throw new RuntimeException('Use GLOWBOOK_CONCURRENCY_TEST=1 with the dedicated MySQL glowbook_test database only.');
}

if (($argv[1] ?? '') === '--worker') {
    $dir = $argv[2];
    $worker = $argv[3];
    $data = json_decode(file_get_contents($dir.'/fixture.json'), true, 512, JSON_THROW_ON_ERROR);
    file_put_contents($dir.'/ready-'.$worker, 'ready');
    $deadline = microtime(true) + 30;
    while (!file_exists($dir.'/go')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Barrier timed out.');
        }
        usleep(20000);
    }
    try {
        $booking = app(BookingManager::class)->create(
            User::findOrFail($data['user']),
            Branch::findOrFail($data['branch']),
            ['date' => $data['date'],'time' => '10:00','service_ids' => [$data['service']],'request_token' => (string) Str::uuid()]
        );
        echo json_encode(['result' => 'created','id' => $booking->id]);
    } catch (Illuminate\Validation\ValidationException $exception) {
        echo json_encode(['result' => 'conflict']);
    }
    exit;
}

$tag = Str::lower(Str::random(12));
$user = User::create(['full_name' => 'Concurrency test','email' => $tag.'@example.test','password_hash' => password_hash(Str::random(32), PASSWORD_BCRYPT)]);
$user->customerProfile()->create();
$owner = DB::table('business_owner_profiles')->insertGetId(['user_id' => $user->id]);
$business = Business::create(['owner_id' => $owner,'name' => 'Concurrency test','slug' => $tag,'status' => 'ACTIVE']);
$branch = Branch::create(['business_id' => $business->id,'name' => 'Concurrent branch','status' => 'ACTIVE','operational_status' => 'ACTIVE']);
$category = ServiceCategory::create(['business_id' => $business->id,'name' => 'Test','slug' => 'test']);
$service = new Service(['category_id' => $category->id,'name' => 'Test service','price' => '100.00','duration_minutes' => 60,'status' => 'ACTIVE','bookable' => true]);
$service->business_id = $business->id;
$branch->services()->save($service);
$staff = $branch->staff()->create(['full_name' => 'Only worker','status' => 'ACTIVE','is_bookable' => true]);
$staff->services()->attach($service->id);
$date = now('Asia/Ho_Chi_Minh')->addDays(3);
DB::table('branch_working_hours')->insert(['branch_id' => $branch->id,'day_of_week' => $date->dayOfWeek,'open_time' => '08:00:00','close_time' => '18:00:00']);
$staff->hours()->create(['day_of_week' => $date->dayOfWeek,'start_time' => '08:00:00','end_time' => '18:00:00','is_off' => false]);
$dir = storage_path('framework/testing/'.$tag);
mkdir($dir, 0777, true);
file_put_contents($dir.'/fixture.json', json_encode(['user' => $user->id,'branch' => $branch->id,'service' => $service->id,'date' => $date->toDateString()]));
$processes = [];
for ($i = 1;$i <= 2;$i++) {
    $processes[] = new Process([PHP_BINARY,'-c',php_ini_loaded_file(),__FILE__,'--worker',$dir,(string)$i], base_path(), null, null, 60);
}
foreach ($processes as $process) {
    $process->start();
}
$deadline = microtime(true) + 30;
while (!file_exists($dir.'/ready-1') || !file_exists($dir.'/ready-2')) {
    if (microtime(true) > $deadline) {
        foreach ($processes as $process) {
            $process->stop();
        }
        throw new RuntimeException('Workers did not reach barrier.');
    }
    usleep(20000);
}
file_put_contents($dir.'/go', 'go');
$results = [];
foreach ($processes as $process) {
    $process->wait();
    if (!$process->isSuccessful()) {
        throw new RuntimeException($process->getErrorOutput().$process->getOutput());
    }
    $results[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR)['result'];
}
sort($results);
if ($results !== ['conflict','created'] || Booking::where('branch_id', $branch->id)->count() !== 1) {
    throw new RuntimeException('Concurrency regression: '.json_encode($results));
}
echo "PASS: two simultaneous MySQL processes, one booking created, one conflict, one row persisted.\n";
