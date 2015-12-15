<?php

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer update --dev`';
	exit(1);
}

require_once __DIR__ . '/inc/UnitTestCase.php';
require_once __DIR__ . '/inc/DriverTestCase.php';

define('TEMP_DIR', __DIR__ . '/temp');

Tester\Environment::setup();

date_default_timezone_set('Europe/Prague');
