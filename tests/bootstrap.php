<?php
// The Nette Tester command-line runner can be
// invoked through the command: ../vendor/bin/tester .

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer update --dev`';
	exit(1);
}

Tester\Environment::setup();

date_default_timezone_set('Europe/Prague');

$conn = new Mva\Dbm\Connection([
	'driver' => 'Mongo',
	'database' => 'mva_test',
	'client' => new MongoClient()
]);

$conn->connect();

return $conn;
