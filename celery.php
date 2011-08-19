<?php

class CeleryException extends Exception {};

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
		if(!is_array($args))
		{
			throw new CeleryException("Args should be an array");
		}
		$id = uniqid('php_', TRUE);
		$xchg = new AMQPExchange($this->connection, 'celery');
		$task_array = array(
			'id' => $id,
			'task' => $task,
			'args' => $args,
			'kwargs' => (object)array(),
		);
		$task = json_encode($task_array);
		$params = array('Content-type' => 'application/json',
			'Content-encoding' => 'UTF-8',
			'immediate' => false,
			);
		$this->connection->connect();
		$success = $xchg->publish($task, 'celery', 0, $params);
		$this->connection->disconnect();

		return new AsyncResult($id, $this->connection);
	}
}

class AsyncResult 
{
	private $id;
	private $connection;
	private $result;
	private $body;

	function __construct($id, $connection)
	{
		$this->id = $id;
		$this->connection = $connection;
	}

	function getCompleteResult()
	{
		if($this->result)
		{
			return $this->result;
		}

		$this->connection->connect();
		$q = new AMQPQueue($this->connection);
		$q->declare($this->id, AMQP_AUTODELETE);
		try
		{
			$q->bind('celeryresults', $this->id);
		}
		catch(AMQPQueueException $e)
		{
			return false;
		}
		$rv = $q->consume(array(
			'min' => 0,
			'max' => 1,
			'ack' => true,
		));
		$q->delete($this->id);
		$this->connection->disconnect();

		if(!sizeof($rv)) return false;
		else 
		{
			$this->result = $rv[0];
			if($rv[0]['Content-type'] != 'application/json')
			{
				throw new CeleryException('Response was not encoded using JSON - check your CELERY_RESULT_SERIALIZER setting!');
			}
			$this->body = json_decode($rv[0]['message_body']);
			return $rv[0];
		}
	}

	function isReady()
	{
		return ($this->getCompleteResult() !== false);
	}

	function getStatus()
	{
		if(!$this->body)
		{
			throw new CeleryException('Called getStatus before task was ready');
		}
		return $this->body->status;
	}

	function isSuccess()
	{
		return($this->getStatus() == 'SUCCESS');
	}

	function getTraceback()
	{
		if(!$this->body)
		{
			throw new CeleryException('Called getTraceback before task was ready');
		}
		return $this->body->traceback;
	}

	function getResult()
	{
		if(!$this->body)
		{
			throw new CeleryException('Called getResult before task was ready');
		}

		return $this->body->result;
	}
}
