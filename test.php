<?php

require_once('celery.php');

$c = new Celery('localhost', 'gdr', 'test', 'wutka', 'celery', 'celery');
$id = $c->PostTask('a', 'b');
echo $id;
do
{
	$rv = $c->CheckResult($id);
	sleep(0.1);
	echo "...\n";
} while($rv === false);
