For FreeBSD User

## Build the librabbitmq
    cd rabbitmq-c
    autoreconf -i && ./configure && gmake
    make install

## Build the amqp-php
    cd amqp-1.0.3
    phpize
    ./configure --with-amqp
    make
    make install




PHP client capable of executing [Celery](celeryproject.org) tasks and reading asynchronous results.

Requires [AMQP extension from PECL](http://www.php.net/manual/en/amqp.setup.php) and the following settings in Celery:

	CELERY_RESULT_SERIALIZER = "json"
	CELERY_TASK_RESULT_EXPIRES = None

PHP-AMQP is supported in version 1.0.0 and higher because its API has been completely remade when it entered 1.0. 
There is a separate branch for 0.3.

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

## PYTHON-LIKE API

An API compatible to AsyncResult in Python is available too.

        $c = new Celery('localhost', 'myuser', 'mypass', 'myvhost');
        $result = $c->PostTask('tasks.add', array(2,2));

        $result->get();
        if($result->successful())
        {
                echo $result->result;
        }


## ABOUT

Created in mere 2 days, so expect no wonders. Based on [this blog post](http://www.toforge.com/2011/01/run-celery-tasks-from-php/) and reading Celery sources. Thanks to Skrat, author of [Celerb](https://github.com/skrat/celerb) for a tip about response encoding. Created for the needs of my consulting work at [Massive Scale](http://massivescale.net/).

## SUPPORT

If you need help integrating Celery in your PHP app, you may be interested in hiring me as a [consultant](http://massivescale.net/performance-for-developers.html).
