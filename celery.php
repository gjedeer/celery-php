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
	private $task_id;
	private $connection;
	private $complete_result;
	private $body;

	/**
	 * Don't instantiate AsyncResult yourself, used internally only
	 * @param string $id Task ID in Celery
	 * @param AMQPConnection $connection 
	 */
	function __construct($id, $connection)
	{
		$this->task_id = $id;
		$this->connection = $connection;
	}

	/**
	 * Connect to queue, see if there's a result waiting for us
	 * Private - to be used internally
	 */
	private function getCompleteResult()
	{
		if($this->complete_result)
		{
			return $this->complete_result;
		}

		$this->connection->connect();
		$q = new AMQPQueue($this->connection);
		$q->declare($this->task_id, AMQP_AUTODELETE);
		try
		{
			$q->bind('celeryresults', $this->task_id);
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
		$q->delete($this->task_id);
		$this->connection->disconnect();

		if(!sizeof($rv)) return false;
		else 
		{
			$this->complete_result = $rv[0];
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

		return $this->body->complete_result;
	}

	/*
	 * Python API emulation
	 * http://ask.github.com/celery/reference/celery.result.html
	 */

	/**
	 * Returns TRUE if the task failed
	 */
	function failed()
	{
		return $this->isReady() && !$this->isSuccess();
	}

	/**
	 * Forget about (and possibly remove the result of) this task
	 * Currently does nothing in PHP client
	 */
	function forget()
	{
	}

	/**
	 * Wait until task is ready, and return its result.
	 * @param float $timeout How long to wait, in seconds, before the operation times out
	 * @param bool $propagate (TODO - not working) Re-raise exception if the task failed.
	 * @param float $interval Time to wait (in seconds) before retrying to retrieve the result
	 * @throws CeleryException on timeout
	 * @return mixed result on both success and failure
	 */
	function get($timeout=10, $propagate=TRUE, $interval=0.5)
	{
		$interval_us = (int)($interval * 1000000);
		$iteration_limit = (int)($timeout / $interval);

        for($i = 0; $i < $iteration_limit; $i++)
        {
                if($this->isReady())
                {
                        break;
                }

                usleep($interval_us);
        }

        if(!$result->isReady())
        {
                throw new CeleryException(sprintf('AMQP task %s(%s) did not return after 10 seconds', $task, json_encode($args)), 4);
        }

        return $this->getResult();
	}

	/**
	 * Implementation of Python's properties: result, state
	 */
	public function __get($property)
	{
		if($property == 'result')
		{
			return $this->getResult();
		}
		elseif($property == 'state' || $property == 'status')
		{
			if($this->isReady())
			{
				return $this->getStatus();
			}
			else
			{
				return 'PENDING';
			}
		}

		return $this->$property;
	}

	/**
	 * Send revoke signal to all workers
	 * Does nothing in PHP client
	 */
	function revoke()
	{
	}

	function successful()
	{
		return $this->isSuccess();
	}

	function wait($timeout=10, $propagate=TRUE, $interval=0.5)
	{
		return get($timeout, $propagate, $interval);
	}
}
