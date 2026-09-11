<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = \App\Models\User::where('email', 'admin@synapse.test')->first()->createToken('test')->plainTextToken;
echo "Token: $token\n";
$model = \Laravel\Sanctum\Sanctum::personalAccessTokenModel();
$accessToken = $model::findToken($token);
echo "Access Token Class: " . get_class($accessToken) . "\n";
echo "Tokenable Class: " . ($accessToken->tokenable ? get_class($accessToken->tokenable) : 'NULL') . "\n";
