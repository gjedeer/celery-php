PHP client capable of executing Celery tasks and reading asynchronous results.

Requires AMQP extension from PECL and the following setting in Celery:
CELERY_RESULT_EXCHANGE_TYPE = "json"

POSTING TASKS

$c = new Celery('localhost', 'myuser', 'mypass', 'myvhost');
$result = $c->PostTask('tasks.add', array(2,2));

READING ASYNC RESULTS

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
