<?php

require __DIR__ . "/../vendor/autoload.php";

use FaktoryQueue\FaktoryClient;
use FaktoryQueue\FaktoryWorker;
use FaktoryQueue\Socket;

// $client = new FaktoryClient('faktory', '7419', 'insecure_password'); // Example with password
$client = new FaktoryClient(Socket::class, 'localhost', '7419', null, true);
$worker = new FaktoryWorker($client, null, ["testing", "example"]);
$worker->setQueues(array("default", "another.queue"));

$worker->register('cooljob', function ($job) {
    echo "something cool: " . $job['args'][0] . ' ' . $job['args'][1] . "\n";
});

$worker->register('cooljob2', function ($job) {
    echo "This is cooler: " . $job['args'][0] . ' ' . $job['args'][1] . "\n";
});

$worker->run();

echo "all done!";
