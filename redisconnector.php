<?php
/**
 * This file contains a PHP client to Celery distributed task queue
 *
 * LICENSE: 2-clause BSD
 *
 * Copyright (c) 2014, flash286
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
 * @link https://github.com/flash286/celery-php
 * @link https://github.com/gjedeer/celery-php
 *
 * @package celery-php
 * @license http://opensource.org/licenses/bsd-license.php 2-clause BSD
 * @author flash286
 * @author GDR! <gdr@go2.pl>
 */
/**
 * Created by PhpStorm.
 * User: nikolas
 * Date: 01.04.14
 * Time: 15:22
 */

Predis\Autoloader::register();

/**
 * Driver for predis - pure PHP implementation of the Redis protocol
 * composer require predis/predis:dev-master
 * @link https://github.com/nrk/predis
 * @package celery-php
 */
 class RedisConnector extends AbstractAMQPConnector {

	public $content_type = 'application/json';

	public $celery_result_prefix = 'celery-task-meta-';

	/**
	* Return headers used sent to Celery
	* Override this function to set custom headers
	*/
	protected function GetHeaders() 
	{
	   return new stdClass;
	}

	/**
	* Prepare the message sent to Celery
	*/
	protected function GetMessage($task) 
	{
	   $result = Array();
	   $result['body'] = base64_encode($task);
	   $result['headers'] = $this->GetHeaders();
	   $result['content-type'] = $this->content_type;
	   $result['content-encoding'] = 'binary';
	   return $result;
	}

	/**
	* Return preferred delivery mode
	*/
	protected function GetDeliveryMode($params=array()) 
	{
	   /* 
	   * http://celery.readthedocs.org/en/latest/userguide/optimizing.html#using-transient-queues
	   * 1 - will not be written to disk
	   * 2 - can be written to disk
	   */
	   if(isset($params['delivery_mode']))
	   {
		  return $params['delivery_mode'];
	   }
	   return 2;
	}

	/**
	* Convert the message dictionary to string
	* Override this function to use non-JSON serialization
	*/
	protected function ToStr($var)
	{
	   return json_encode($var);
	}

	/**
	* Convert the message string to dictionary
	* Override this function to use non-JSON serialization
	*/
	protected function ToDict($raw_json) 
	{
	   return json_decode($raw_json, TRUE);
	}

	/**
	* Post the message to Redis
	* This function implements the AbstractAMQPConnector interface
	*/
	public function PostToExchange($connection, $details, $task, $params) 
	{
	   $connection = $this->Connect($connection);
	   $body = json_decode($task, true);
	   $message = $this->GetMessage($task);
	   $message['properties'] = Array(
		  'body_encoding' => 'base64',
		  'reply_to' => $body['id'],
		  'delivery_info' => Array(
			 'priority' => 0,
			 'routing_key' => $details['binding'],
			 'exchange' => $details['exchange'],
		  ),
		  'delivery_mode' => $this->GetDeliveryMode($params),
		  'delivery_tag'  => $body['id']
	   );
	   $connection->lPush($details['exchange'], $this->ToStr($message));

	   return TRUE;
	}

	/**
	* Initialize connection on a given connection object
	* This function implements the AbstractAMQPConnector interface
	* @return NULL
	*/
	public function Connect($connection) 
	{
	   if ($connection->isConnected()) 
	   {
		  return $connection;
	   } 
	   else 
	   {
		  $connection->connect();
		  return $connection;
	   }
	}

	/**
	* Return the result queue name for a given task ID
	* @param string $task_id
	* @return string
	*/
	protected function GetResultKey($task_id) 
	{
	   return sprintf("%s%s", $this->celery_result_prefix, $task_id);
	}

	/**
	* Clean up after reading the message body
	* @param object $connection Predis\Client connection object returned by GetConnectionObject()
	* @param string $task_id
	* @return bool
	*/
	protected function FinalizeResult($connection, $task_id) 
	{
	   if ($connection->exists($this->GetResultKey($task_id))) 
	   {
		  $connection->del($this->GetResultKey($task_id));
		  return true;
	   }

	   return false;
	}

	/**
	* Return result of task execution for $task_id
	* @param object $connection Predis\Client connection object returned by GetConnectionObject()
	* @param string $task_id Celery task identifier
	* @param int $expire Unused in Redis
	* @param boolean $removeMessageFromQueue whether to remove message from queue
	* @return array|bool array('body' => JSON-encoded message body, 'complete_result' => library-specific message object)
	* 			or false if result not ready yet
	*/
	public function GetMessageBody($connection, $task_id, $expire=0, $removeMessageFromQueue=true) 
	{
	   $result = $connection->get($this->GetResultKey($task_id));
	   if ($result) 
	   {
		  $redis_result = $this->ToDict($result, true);
		  $result = Array(
			 'complete_result' => $redis_result,
			 'body' => json_encode($redis_result)
		  );
		  if ($removeMessageFromQueue) 
		  {
			 $this->FinalizeResult($connection, $task_id);
		  }

		  return $result;
	   }
	   else 
	   {
		  return false;
	   }
	}

	/**
	* Return Predis\Client connection object passed to all other calls
	* @param array $details Array of connection details
	* @return object
	*/
	function GetConnectionObject($details) {
	   $connect = new Predis\Client(Array(
		  'scheme' => 'tcp',
		  'host'   => $details['host'],
		  'port'   => $details['port'],
		  'database' => $details['vhost'],
		  'password' => empty($details['password']) ? null : $details['password']
	   ));
	   return $connect;
	}
 }
