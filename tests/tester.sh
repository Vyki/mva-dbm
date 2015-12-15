#!/bin/bash
dir=$(cd `dirname $0` && pwd)
php $dir/../vendor/nette/tester/Tester/tester $dir/cases -c $dir/php-unix.ini --setup $dir/inc/setup.php -s