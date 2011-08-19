PHP client capable of executing [Celery](celeryproject.org) tasks and reading asynchronous results.

Requires [AMQP extension from PECL](http://www.php.net/manual/en/amqp.setup.php) and the following setting in Celery:

    CELERY_RESULT_EXCHANGE_TYPE = "json"

## POSTING TASKS                                                                                                                           

	$c = new Celery('localhost', 'myuser', 'mypass', 'myvhost');
	$result = $c->PostTask('tasks.add', array(2,2));

## READING ASYNC RESULTS

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

## ABOUT

Created in mere 2 days, so expect no wonders. Based on [this blog post](http://www.toforge.com/2011/01/run-celery-tasks-from-php/) and reading Celery sources. Thanks to Skrat, author of [Celerb](https://github.com/skrat/celerb) for a tip about response encoding. Created for the needs of my consulting work at [Massive Scale](http://massivescale.net/).
