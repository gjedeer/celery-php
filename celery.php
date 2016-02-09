<?php

/**
 * This file contains a PHP client to Celery distributed task queue
 *
 * LICENSE: 2-clause BSD
 *
 * Copyright (c) 2012, GDR!
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met: 
 * 
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer. 
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution. 
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * The views and conclusions contained in the software and documentation are those
 * of the authors and should not be interpreted as representing official policies, 
 * either expressed or implied, of the FreeBSD Project. 
 *
 * @link http://massivescale.net/
 * @link http://gdr.geekhood.net/
 * @link https://github.com/gjedeer/celery-php
 *
 * @package celery-php
 * @license http://opensource.org/licenses/bsd-license.php 2-clause BSD
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
 * Emited by CeleryAbstract::PostTask() connection failures etc
 * @package celery-php
 */
class CeleryPublishException extends CeleryException {};

require('amqp.php');

/**
 * Simple client for a Celery server
 *
 * for when queue and results are in the same broker
 * Use this class if you don't know what the above means
 * @package celery-php
 */
class Celery extends CeleryAbstract 
{
   /**
    * @param string host
	* @param string login
	* @param string password
	* @param string vhost AMQP vhost, may be left empty or NULL for non-AMQP backends like Redis
	* @param string exchange AMQP exchange to use. For Redis it maps to queue key name. See CELERY_DEFAULT_EXCHANGE in Celery docs. (set to 'celery' when in doubt)
	* @param string binding AMQP binding a.k.a. routing key. See CELERY_DEFAULT_ROUTING_KEY. (set to 'celery' when in doubt)
	* @param int port
	* @param string connector Which connector library to use. One of: 'pecl', 'php-amqplib', 'php-amqplib-ssl', 'redis'
	* @param bool persistent_messages False = transient queue, True = persistent queue. Check "Using Transient Queues" in Celery docs (set to false when in doubt)
	* @param int result_expire Expire time for result queue, milliseconds (for AMQP exchanges only)
	* @param array ssl_options Used only for 'php-amqplib-ssl' connections, an associative array with values as defined here: http://php.net/manual/en/context.ssl.php
	*/

	function __construct($host, $login, $password, $vhost, $exchange='celery', $binding='celery', $port=5672, $connector = false, $persistent_messages=false, $result_expire=0, $ssl_options = array() )
	{
		$broker_connection = array(
			'host' => $host,
			'login' => $login,
			'password' => $password,
			'vhost' => $vhost,
			'exchange' => $exchange,
			'binding' => $binding,
			'port' => $port,
			'connector' => $connector,
			'result_expire' => $result_expire,
			'ssl_options' => $ssl_options
		);
		$backend_connection = $broker_connection;

		$items = $this->BuildConnection($broker_connection);
		$items = $this->BuildConnection($backend_connection, true);
	}
}

/**
 * Client for a Celery server - with a constructor supporting separate backend queue
 * @package celery-php
 */
class CeleryAdvanced extends CeleryAbstract 
{
    /**
	 * @param array broker_connection - array for connecting to task queue, see Celery class above for supported keys
	 * @param array backend_connection - array for connecting to result backend, see Celery class above for supported keys
	 */
	function __construct($broker_connection, $backend_connection=false) 
	{
		if($backend_connection == false) 
		{ 
			$backend_connection = $broker_connection;
		}

		$items = $this->BuildConnection($broker_connection);
		$items = $this->BuildConnection($backend_connection, true);
	}
}


/**
* Client for a Celery server - abstract base class implementing actual logic
* @package celery-php
*/
abstract class CeleryAbstract
{

	private $broker_connection = null;
	private $broker_connection_details = array();
	private $broker_amqp = null;

	private $backend_connection = null;
	private $backend_connection_details = array();
	private $backend_amqp = null;

	private $isConnected = false;

	private function SetDefaultValues($details) {
		$defaultValues = array("host" => "", "login" => "", "password" => "", "vhost" => "", "exchange" => "celery", "binding" => "celery", "port" => 5672, "connector" => false, "persistent_messages" => false, "result_expire" => 0, "ssl_options" => array());

		$returnValue = array();

		foreach(array('host', 'login', 'password', 'vhost', 'exchange', 'binding', 'port', 'connector', 'persistent_messages', 'result_expire', 'ssl_options') as $detail)
		{
			if (!array_key_exists($detail, $details)) { $returnValue[$detail] = $defaultValues[$detail]; }
			else $returnValue[$detail] = $details[$detail];
		}
		return $returnValue;
	}

	public function BuildConnection($connection_details, $is_backend = false) {
		$connection_details = $this->SetDefaultValues($connection_details);
		$ssl = !empty($connection['ssl_options']);

		if ($connection_details['connector'] === false)
		{
			$connection_details['connector'] = AbstractAMQPConnector::GetBestInstalledExtensionName($ssl);
		}
		$amqp = AbstractAMQPConnector::GetConcrete($connection_details['connector']);
		$connection = self::InitializeAMQPConnection($connection_details);
		$amqp->Connect($connection);

		if ($is_backend) {
			$this->backend_connection_details = $connection_details;
			$this->backend_connection = $connection;
			$this->backend_amqp = $amqp;
		}
		else {
			$this->broker_connection_details = $connection_details;
			$this->broker_connection = $connection;
			$this->broker_amqp = $amqp;
		}
	}

	static function InitializeAMQPConnection($details)
	{
		$amqp = AbstractAMQPConnector::GetConcrete($details['connector']);
		return $amqp->GetConnectionObject($details);
	}

	/**
	 * Post a task to Celery
	 * @param string $task Name of the task, prefixed with module name (like tasks.add for function add() in task.py)
	 * @param array $args Array of arguments (kwargs call when $args is associative)
	 * @param bool $async_result Set to false if you don't need the AsyncResult object returned
	 * @param string $routing_key Set to routing key name if you're using something other than "celery"
	 * @param array $task_args Additional settings for Celery - normally not needed
	 * @return AsyncResult
	 */
	function PostTask($task, $args, $async_result=true,$routing_key="celery", $task_args=array())
	{
		if(!is_array($args))
		{
			throw new CeleryException("Args should be an array");
		}

		if (!$this->isConnected) {
			$this->broker_amqp->Connect($this->broker_connection);
			$this->isConnected = true;
		}

		$id = uniqid('php_', TRUE);

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
                
		 /* 
		 *	$task_args may contain additional arguments such as eta which are useful in task execution 
		 *	The usecase of this field is as follows:
		 *	$task_args = array( 'eta' => "2014-12-02T16:00:00" );
		 */
		$task_array = array_merge(
			array(
				'id' => $id,
				'task' => $task,
				'args' => $args,
				'kwargs' => (object)$kwargs,
			),
			$task_args
		);
		
		$task = json_encode($task_array);
		$params = array('content_type' => 'application/json',
			'content_encoding' => 'UTF-8',
			'immediate' => false,
		 );

		if($this->broker_connection_details['persistent_messages'])
		{
			$params['delivery_mode'] = 2;
		}

        $this->broker_connection_details['routing_key'] = $routing_key;

		$success = $this->broker_amqp->PostToExchange(
			$this->broker_connection,
			$this->broker_connection_details,
			$task,
			$params
		);

		if(!$success)
		{
		   throw new CeleryPublishException();
		}

        if($async_result) 
        {
			return new AsyncResult($id, $this->backend_connection_details, $task_array['task'], $args);
        } 
        else 
        {
			return true;
		}
	}

	/**
	 * Get the current message of the async result. If there is no async result for a task in the queue false will be returned.
	 * Can be used to pass custom states to the client as mentioned in http://celery.readthedocs.org/en/latest/userguide/tasks.html#custom-states
	 *
	 * @param string $taskName Name of the called task, like 'tasks.add'
	 * @param string $taskId The Task ID - from AsyncResult::getId()
	 * @param null|array $args Task arguments
	 * @param boolean $removeMessageFromQueue whether to remove the message from queue. If not celery will remove the message
	 * due to its expire parameter
	 * @return array|boolean array('body' => JSON-encoded message body, 'complete_result' => library-specific message object)
	 * 			or false if result not ready yet
	 *
	 */
	public function getAsyncResultMessage($taskName, $taskId, $args = null, $removeMessageFromQueue = true)
	{
		$result = new AsyncResult($taskId, $this->backend_connection_details, $taskName, $args);

		$messageBody = $result->amqp->GetMessageBody(
			$result->connection,
			$taskId,
			$this->backend_connection_details['result_expire'],
			$removeMessageFromQueue
		);

		return $messageBody;
	}

}

/*
 * Asynchronous result of Celery task
 * @package celery-php
 */
class AsyncResult 
{
	private $task_id; // string, queue name
	private $connection; // AMQPConnection instance
	private $connection_details; // array of strings required to connect
	private $complete_result; // Backend-dependent message instance (AMQPEnvelope or PhpAmqpLib\Message\AMQPMessage)
	private $body; // decoded array with message body (whatever Celery task returned)
	private $amqp = null; // AbstractAMQPConnector implementation

	/**
	 * Don't instantiate AsyncResult yourself, used internally only
	 * @param string $id Task ID in Celery
	 * @param array $connection_details used to initialize AMQPConnection, keys are the same as args to Celery::__construct
	 * @param string task_name
	 * @param array task_args
	 */
	function __construct($id, $connection_details, $task_name=NULL, $task_args=NULL)
	{
		$this->task_id = $id;
		$this->connection = Celery::InitializeAMQPConnection($connection_details);
		$this->connection_details = $connection_details;
		$this->task_name = $task_name;
		$this->task_args = $task_args;
		$this->amqp = AbstractAMQPConnector::GetConcrete($connection_details['connector']);
	}

	function __wakeup()
	{
		if($this->connection_details)
		{
			$this->connection = Celery::InitializeAMQPConnection($this->connection_details);
		}
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

		$message = $this->amqp->GetMessageBody($this->connection, $this->task_id,$this->connection_details['result_expire'], true);
		
		if($message !== false)
		{
			$this->complete_result = $message['complete_result'];
			$this->body = json_decode(
				$message['body']
			);
		}

		return false;
	}

	/**
	 * Helper function to return current microseconds time as float 
	 */
	static private function getmicrotime()
	{
			list($usec, $sec) = explode(" ",microtime());
			return ((float)$usec + (float)$sec); 
	}

	/**
	 * Get the Task Id
	 * @return string
	 */
	 function getId()
	 {
		return $this->task_id;
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

		$start_time = self::getmicrotime();
		while(self::getmicrotime() - $start_time < $timeout)
		{
				if($this->isReady())
				{
						break;
				}

				usleep($interval_us);
		}

		if(!$this->isReady())
		{
				throw new CeleryTimeoutException(sprintf('AMQP task %s(%s) did not return after %d seconds', $this->task_name, json_encode($this->task_args), $timeout), 4);
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

