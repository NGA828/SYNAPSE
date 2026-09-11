<?php
$app = require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Teachers table ===" . PHP_EOL;
$teachers = \DB::table('teachers')->get();
foreach ($teachers as $t) {
    echo json_encode($t) . PHP_EOL;
}

echo PHP_EOL . "=== Students table ===" . PHP_EOL;
$students = \DB::table('students')->get();
foreach ($students as $s) {
    echo json_encode($s) . PHP_EOL;
}

echo PHP_EOL . "=== Routes: teacher gradebook ===" . PHP_EOL;
$route = app('router')->getRoutes()->getByAction(\App\Http\Controllers\Api\Teacher\GradebookController::class.'@index');
if ($route) {
    echo "URI: " . $route->uri() . PHP_EOL;
    echo "Methods: " . implode(',', $route->methods()) . PHP_EOL;
    echo "Wheres: " . json_encode($route->wheres) . PHP_EOL;
} else {
    echo "Route not found!" . PHP_EOL;
}

echo PHP_EOL . "=== Routes: teacher attendance ===" . PHP_EOL;
$route = app('router')->getRoutes()->getByAction(\App\Http\Controllers\Api\Teacher\AttendanceController::class.'@index');
if ($route) {
    echo "URI: " . $route->uri() . PHP_EOL;
    echo "Methods: " . implode(',', $route->methods()) . PHP_EOL;
} else {
    echo "Route not found!" . PHP_EOL;
}
