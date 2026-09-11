<?php
$app = require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Login as teacher
$response = \Illuminate\Support\Facades\Http::post('http://127.0.0.1:8000/api/login', [
    'email' => 'teacher@synapse.test',
    'password' => 'password123',
]);

echo "Login status: " . $response->status() . PHP_EOL;
echo "Login response: " . $response->body() . PHP_EOL;

if ($token = $response->json('token')) {
    echo PHP_EOL . "=== Trying gradebook (class 5, subject 1) ===" . PHP_EOL;
    $response2 = \Illuminate\Support\Facades\Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ])->get('http://127.0.0.1:8000/api/teacher/classes/5/subjects/1/gradebook');

    echo "Status: " . $response2->status() . PHP_EOL;
    echo "Body: " . $response2->body() . PHP_EOL;

    echo PHP_EOL . "=== Trying attendance (class 5) ===" . PHP_EOL;
    $response3 = \Illuminate\Support\Facades\Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ])->get('http://127.0.0.1:8000/api/teacher/classes/5/attendance?date=2026-09-11');

    echo "Status: " . $response3->status() . PHP_EOL;
    echo "Body: " . $response3->body() . PHP_EOL;

    echo PHP_EOL . "=== Trying teacher dashboard ===" . PHP_EOL;
    $response4 = \Illuminate\Support\Facades\Http::withHeaders([
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ])->get('http://127.0.0.1:8000/api/teacher/dashboard');

    echo "Status: " . $response4->status() . PHP_EOL;
    echo "Body: " . $response4->body() . PHP_EOL;
}
