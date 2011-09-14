<?php

/**
 * This file contains a PHP client to Celery distributed task queue
 *
 * LICENSE: beerware.
 *
 * GDR! wrote this file. As long as you retain this notice you can do whatever you want with this stuff. 
 * If we meet some day, and you think this stuff is worth it, you can buy GDR! a beer in return. 
 *
 * @link http://massivescale.net/
 * @link http://gdr.geekhood.net/
 * @link https://github.com/gjedeer/celery-php
 *
 * @package celery-php
 * @license http://en.wikipedia.org/wiki/Beerware Beerware
 * @author GDR! <gdr@go2.pl>
 */

/**
 * General exception class
 * @package celery-php
 */
class CeleryException extends Exception {};
/**
 * Emited by AsyncResult::get() on timeout
 * @package celery-php
 */
class CeleryTimeoutException extends CeleryException {};

/**
 * Client for a Celery server
 * @package celery-php
 */
class Celery
{
	private $connection = null;

	function __construct($host, $login, $password, $vhost, $exchange='celery', $binding='celery', $port=5672)
	{
		if(!class_exists('AMQPConnection'))
		{
            throw new CeleryException("Class AMQPConnection not found\nMake sure that AMQP extension is installed and enabled:\nhttp://www.php.net/manual/en/amqp.installation.php");
		}

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
	 * @param array $args Array of arguments (kwargs call when $args is associative)
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

		/* $args is numeric -> positional args */
		if(array_keys($args) === range(0, count($args) - 1))
		{
			$kwargs = array();
		}
		/* $args is associative -> contains kwargs */
		else
		{
			$kwargs = $args;
			$args = array();
		}
                                                                            
		$task_array = array(
			'id' => $id,
			'task' => $task,
			'args' => $args,
			'kwargs' => (object)$kwargs,
		);
		$task = json_encode($task_array);
		$params = array('Content-type' => 'application/json',
			'Content-encoding' => 'UTF-8',
			'immediate' => false,
			);
		$this->connection->connect();
		$success = $xchg->publish($task, 'celery', 0, $params);
		$this->connection->disconnect();

		return new AsyncResult($id, $this->connection, $task_array['task'], $args);
	}
}

/*
 * Asynchronous result of Celery task
 * @package celery-php
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
	 * @param string task_name
	 * @param array task_args
	 */
	function __construct($id, $connection, $task_name=NULL, $task_args=NULL)
	{
		$this->task_id = $id;
		$this->connection = $connection;
		$this->task_name = $task_name;
		$this->task_args = $task_args;
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

		return $this->body->result;
	}

	/****************************************************************************
	 * Python API emulation                                                     *
	 * http://ask.github.com/celery/reference/celery.result.html                *
	 ****************************************************************************/

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
	 * @throws CeleryTimeoutException on timeout
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

        if(!$this->isReady())
        {
                throw new CeleryTimeoutException(sprintf('AMQP task %s(%s) did not return after 10 seconds', $this->task_name, json_encode($this->task_args)), 4);
        }

        return $this->getResult();
	}

	/**
	 * Implementation of Python's properties: result, state/status
	 */
	public function __get($property)
	{
		/**
		 * When the task has been executed, this contains the return value. 
		 * If the task raised an exception, this will be the exception instance.
		 */
		if($property == 'result')
		{
			if($this->isReady())
			{
				return $this->getResult();
			}
			else
			{
				return NULL;
			}
		}
		/**
		 * state: The tasks current state.
		 *
		 * Possible values includes:
		 *
		 * PENDING
		 * The task is waiting for execution.
		 *
		 * STARTED
		 * The task has been started.
		 *
		 * RETRY
		 * The task is to be retried, possibly because of failure.
		 *
		 * FAILURE
		 * The task raised an exception, or has exceeded the retry limit. The result attribute then contains the exception raised by the task.
		 *
		 * SUCCESS
		 * The task executed successfully. The result attribute then contains the tasks return value.
		 *
		 * status: Deprecated alias of state.
		 */
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
	 * Returns True if the task has been executed.
	 * If the task is still running, pending, or is waiting for retry then False is returned.
	 */
	function ready()
	{
		return $this->isReady();
	}

	/**
	 * Send revoke signal to all workers
	 * Does nothing in PHP client
	 */
	function revoke()
	{
	}

	/**
	 * Returns True if the task executed successfully.
	 */
	function successful()
	{
		return $this->isSuccess();
	}

	/**
	 * Deprecated alias to get()
	 */
	function wait($timeout=10, $propagate=TRUE, $interval=0.5)
	{
		return $this->get($timeout, $propagate, $interval);
	}
}
