<?php

require_once('amqp.php');
require_once('vendor/autoload.php');

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

	function Connect($connection)
	{
		/* NO-OP: not required in PhpAmqpLib */
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
		$connection->close();
	}

	/**
	 * A callback function for AMQPChannel::basic_consume
	 * @param PhpAmqpLib\Message\AMQPMessage $msg
	 */
	function Consume($msg)
	{
		$this->message = $msg;
		echo "===\nGOT MESSAGE\n===";
	}

	function GetMessageBody($connection, $task_id)
	{
		if(!$this->receiving_channel)
		{
			$ch = $connection->channel();

			$ch->queue_declare(
				$task_id, 				/* queue name */
				false,					/* passive */
				false,					/* durable */
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
			echo "Timeout";
			return false;
		}

		/* Check if the callback function saved something */
		if($this->message)
		{
			return array(
				'complete_result' => $this->message,
				'body' => $this->message->body, // JSON message body
			);
		}

		return false;
	}
}
