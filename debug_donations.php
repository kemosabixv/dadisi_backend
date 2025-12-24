<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('username', 'kemosabi')->first();
if (!$user) {
    echo "User kemosabi not found\n";
    exit;
}

echo "User ID: " . $user->id . "\n";
echo "User Email: " . $user->email . "\n";

$donations = \App\Models\Donation::all();
echo "Total Donations in DB: " . $donations->count() . "\n";

$kemosabiDonations = \App\Models\Donation::where('user_id', $user->id)->get();
echo "Donations linked to kemosabi: " . $kemosabiDonations->count() . "\n";

$nullUserDonations = \App\Models\Donation::whereNull('user_id')->get();
echo "Donations with NULL user_id: " . $nullUserDonations->count() . "\n";

foreach ($nullUserDonations as $d) {
    echo "ID: " . $d->id . ", Name: " . $d->donor_name . ", Email: " . $d->donor_email . ", Amount: " . $d->amount . "\n";
}
