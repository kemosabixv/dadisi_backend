<?php
echo "Starting bootstrap test...\n";
echo "Loading environment...\n";

$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'true';

echo "Requiring autoload...\n";
require __DIR__ . '/vendor/autoload.php';

echo "Autoload complete\n";

echo "Creating Laravel application...\n";
$app = require __DIR__ . '/bootstrap/app.php';

echo "Bootstrap complete\n";
echo "Registering middleware...\n";

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

echo "Kernel created\n";

echo "✅ Bootstrap successful!\n";
