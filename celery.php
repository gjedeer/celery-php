<?php

class Celery
{
	private $connection = null;

	function __construct($host, $login, $password, $vhost, $exchange='celery', $binding='celery', $port=5672)
	{
		$this->connection = new AMQPConnection();
		$this->connection->setHost($host);
		$this->connection->setLogin($login);
		$this->connection->setPassword($password);
		$this->connection->setVhost($vhost);
		$this->connection->setPort($port);

		$success = $this->connection->connect();
	}

	function PostTask($task, $args)
	{
		$id = uniqid('php_');
		$xchg = new AMQPExchange($this->connection, 'celery');
		$task = '{"id":"'.$id.'", "task":"tasks.add", "args": [2,2], "kwargs": {}}';
		$params = array('Content-type' => 'application/json',
			'Content-encoding' => 'UTF-8',
			'immediate' => false,
			);
		$success = $xchg->publish($task, 'celery', 0, $params);
		return $id;
	}

	function CheckResult($id)
	{
		try
		{
			echo "Construct...\n";
			$q = new AMQPQueue($this->connection);
			$q->declare($id."__");
			echo "bind...\n";
			$q->bind('celeryresults', $id);
			echo "Consume...\n";
			$rv = $q->consume(array(
				'min' => 0,
				'max' => 1,
				'ack' => false,
			));

			if(!sizeof($rv)) return false;
			else print_r($rv);
		}
		catch(AMQPQueueException $e)
		{
			return false;
		}
	}
}
