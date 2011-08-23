<?php
/*
 * LICENSE: beerware
 * GDR! wrote this file. As long as you retain this notice you can do whatever you want with this stuff. 
 * If we meet some day, and you think this stuff is worth it, you can buy GDR! a beer in return. 
 *
 * http://massivescale.net/
 * http://gdr.geekhood.net/
 * gdr@go2.pl
 */

require_once('celery.php');

$c = new Celery('localhost', 'gdr', 'test', 'wutka', 'celery', 'celery');
$result = $c->PostTask('tasks.add', array(2,2));
#$result = $c->PostTask('tasks.fail', array());
#echo $result;

while(!$result->isReady())
{
	sleep(1);
	echo '...';
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
