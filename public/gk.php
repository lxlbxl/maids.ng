<?php
$token=$_GET['token']??'';if($token!=='setup-now'){http_response_code(403);die;}

require __DIR__.'/../vendor/autoload.php';
$app=require_once __DIR__.'/../bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$k=\App\Models\AgentApiKey::generateKey('sms-blast','onboarding',['*']);
echo json_encode(['key'=>$k->plain_key]);
