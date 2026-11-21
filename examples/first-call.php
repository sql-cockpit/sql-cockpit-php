<?php

// Prints your account's name, email and plan. Run: SQL_COCKPIT_API_KEY=your-key php examples/first-call.php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$client = new SqlCockpit\Client(); // reads SQL_COCKPIT_API_KEY

$account = $client->account->get();
echo 'Name:  ', $account->name, PHP_EOL;
echo 'Email: ', $account->email, PHP_EOL;
echo 'Plan:  ', $account->plan->name ?? 'no plan', PHP_EOL;
