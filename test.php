<?php

require_once('celery.php');

$c = new Celery('localhost', 'gdr', 'test', 'wutka', 'celery', 'celery');
$result = $c->PostTask('tasks.add', array(2,2));
#$result = $c->PostTask('tasks.fail', array());
#echo $result;

while(!$result->isReady())
{
	sleep(0.5);
}

if($result->isSuccess())
{
	echo $result->getResult();
}
else
{
	echo "ERROR";
	echo $result->getTraceback();
}
