<?php

namespace Dbm\Tests;

use Tester;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	echo "Install Nette Tester using `composer update`\n";
	exit(1);
}
$setupMode = TRUE;

@mkdir(__DIR__ . '/../temp');

Tester\Helpers::purge(__DIR__ . '/../temp');

