<?php

require_once('amqp.php');
//require_once('vendor/autoload.php');

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Driver for pure PHP implementation of AMQP protocol
 * @link https://github.com/videlalvaro/php-amqplib
 * @package celery-php
 */
class AMQPLibConnector extends AbstractAMQPConnector
{
	/** 
	 * How long (in seconds) to wait for a message from queue 
	 * Sadly, this can't be set to zero to achieve complete asynchronity
	 * @TODO change to 0.1 when php-amqp accepts https://github.com/videlalvaro/php-amqplib/pull/80 
	 */
    public $wait_timeout = 1;

	/**
	 * PhpAmqpLib\Message\AMQPMessage object received from the queue
	 */
	private $message = null;

	/**
	 * AMQPChannel object cached for subsequent GetMessageBody() calls
	 */
	private $receiving_channel = null;

	function GetConnectionObject($details)
	{
		return new AMQPConnection(
			$details['host'],
			$details['port'],
			$details['login'],
			$details['password'],
			$details['vhost']
		);
	}

	/* NO-OP: not required in PhpAmqpLib */
	function Connect($connection)
	{
	}

	function PostToExchange($connection, $details, $task, $params)
	{
		$ch = $connection->channel();

		$ch->queue_declare(
			$details['binding'], 	/* queue name - "celery" */
			false,					/* passive */
			true,					/* durable */
			false,					/* exclusive */
			false					/* auto_delete */
		);

		$ch->exchange_declare(
			$details['exchange'],	/* name */
			'direct',				/* type */
			false,					/* passive */
			true,					/* durable */
			false					/* auto_delete */
		);

		$ch->queue_bind(
			$details['binding'], 	/* queue name - "celery" */
			$details['exchange'] 	/* exchange name - "celery" */
		);

		$msg = new AMQPMessage(
			$task,
			$params
		);

		$ch->basic_publish($msg, $details['exchange']);

		$ch->close();

		/* Satisfy Celery::PostTask() error checking */
		/* TODO: catch some exceptions? Which ones? */
		return TRUE; 
	}

	/**
	 * A callback function for AMQPChannel::basic_consume
	 * @param PhpAmqpLib\Message\AMQPMessage $msg
	 */
	function Consume($msg)
	{
		$this->message = $msg;
	}

	/**
	 * Return result of task execution for $task_id
	 * @param object $connection AMQPConnection object
	 * @param string $task_id Celery task identifier
	 * @return array array('body' => JSON-encoded message body, 'complete_result' => AMQPMessage object)
	 * 			or false if result not ready yet
	 */
	function GetMessageBody($connection, $task_id)
	{
		if(!$this->receiving_channel)
		{
			$ch = $connection->channel();

			$ch->queue_declare(
				$task_id, 				/* queue name */
				false,					/* passive */
				true,					/* durable */
				false,					/* exclusive */
				true					/* auto_delete */
			);

			$ch->queue_bind($task_id, 'celeryresults');

			$ch->basic_consume(
				$task_id, 	/* queue */
				'', 		/* consumer tag */
				false, 		/* no_local */
				false, 		/* no_ack */
				false,		/* exclusive */
				false,		/* nowait */
				array($this, 'Consume')	/* callback */
			);
			$this->receiving_channel = $ch;
		}

		try
		{
			$this->receiving_channel->wait(null, false, $this->wait_timeout);
		}
		catch(PhpAmqpLib\Exception\AMQPTimeoutException $e)
		{
			return false;
		}

		/* Check if the callback function saved something */
		if($this->message)
		{
			$this->receiving_channel->queue_delete($task_id);
			$this->receiving_channel->close();
			$connection->close();

			return array(
				'complete_result' => $this->message,
				'body' => $this->message->body, // JSON message body
			);
		}

		return false;
	}
}
