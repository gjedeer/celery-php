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

	/**
	 * Post a task to Celery
	 * @param string $task Name of the task, prefixed with module name (like tasks.add for function add() in task.py)
	 * @param array $args (Non-associative) Array of arguments
	 * @return AsyncResult
	 */
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

/*
 * Asynchronous result of Celery task
 */
class AsyncResult 
{
	private $id;
	private $connection;
	private $result;
	private $body;

	/**
	 * Don't instantiate AsyncResult yourself, used internally only
	 * @param string $id Task ID in Celery
	 * @param AMQPConnection $connection 
	 */
	function __construct($id, $connection)
	{
		$this->id = $id;
		$this->connection = $connection;
	}

	/**
	 * Connect to queue, see if there's a result waiting for us
	 * Private - to be used internally
	 */
	private function getCompleteResult()
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

	/**
	 * Check if a task result is ready
	 * @return bool
	 */
	function isReady()
	{
		return ($this->getCompleteResult() !== false);
	}

	/**
	 * Return task status (needs to be called after isReady() returned true)
	 * @return string 'SUCCESS', 'FAILURE' etc - see Celery source
	 */
	function getStatus()
	{
		if(!$this->body)
		{
			throw new CeleryException('Called getStatus before task was ready');
		}
		return $this->body->status;
	}

	/**
	 * Check if task execution has been successful or resulted in an error
	 * @return bool
	 */
	function isSuccess()
	{
		return($this->getStatus() == 'SUCCESS');
	}

	/**
	 * If task execution wasn't successful, return a Python traceback
	 * @return string
	 */
	function getTraceback()
	{
		if(!$this->body)
		{
			throw new CeleryException('Called getTraceback before task was ready');
		}
		return $this->body->traceback;
	}

	/**
	 * Return a result of successful execution.
	 * In case of failure, this returns an exception object
	 * @return mixed Whatever the task returned
	 */
	function getResult()
	{
		if(!$this->body)
		{
			throw new CeleryException('Called getResult before task was ready');
		}

		return $this->body->result;
	}
}
