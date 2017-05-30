<?php

namespace Celery;

use PhpAmqpLib\Exception\AMQPProtocolConnectionException;

/**
 * Client for a Celery server - abstract base class implementing actual logic
 * @package celery-php
 */
abstract class CeleryAbstract
{
    private $broker_connection = null;
    private $broker_connection_details = [];
    private $broker_amqp = null;

    private $backend_connection = null;
    private $backend_connection_details = [];
    private $backend_amqp = null;

    private $isConnected = false;

    private function SetDefaultValues($details)
    {
        $defaultValues = ["host" => "", "login" => "", "password" => "", "vhost" => "", "exchange" => "celery", "binding" => "celery", "port" => 5672, "connector" => false, "persistent_messages" => false, "result_expire" => 0, "ssl_options" => []];

        $returnValue = [];

        foreach (['host', 'login', 'password', 'vhost', 'exchange', 'binding', 'port', 'connector', 'persistent_messages', 'result_expire', 'ssl_options'] as $detail) {
            if (!array_key_exists($detail, $details)) {
                $returnValue[$detail] = $defaultValues[$detail];
            } else {
                $returnValue[$detail] = $details[$detail];
            }
        }
        return $returnValue;
    }

    public function BuildConnection($connection_details, $is_backend = false)
    {
        $connection_details = $this->SetDefaultValues($connection_details);
        $ssl = !empty($connection['ssl_options']);

        if ($connection_details['connector'] === false) {
            $connection_details['connector'] = AbstractAMQPConnector::GetBestInstalledExtensionName($ssl);
        }
        $amqp = AbstractAMQPConnector::GetConcrete($connection_details['connector']);
        $connection = self::InitializeAMQPConnection($connection_details);
        $amqp->Connect($connection);

        if ($is_backend) {
            $this->backend_connection_details = $connection_details;
            $this->backend_connection = $connection;
            $this->backend_amqp = $amqp;
        } else {
            $this->broker_connection_details = $connection_details;
            $this->broker_connection = $connection;
            $this->broker_amqp = $amqp;
        }
    }

    /**
     * @throws CeleryConnectionException on connection failure.
     */
    public static function InitializeAMQPConnection($details)
    {
        $amqp = AbstractAMQPConnector::GetConcrete($details['connector']);
        try {
            return $amqp->GetConnectionObject($details);
        }
        catch (AMQPProtocolConnectionException $e) {
            throw new CeleryConnectionException("Failed to establish a AMQP connection. Check credentials.");
        }
        catch (Exception $e) {
            throw new CeleryConnectionException("Failed to establish a AMQP connection. Unspecified failure.");
        }
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
    public function PostTask($task, $args, $async_result=true, $routing_key="celery", $task_args=[])
    {
        if (!is_array($args)) {
            throw new CeleryException("Args should be an array");
        }

        if (!$this->isConnected) {
            $this->broker_amqp->Connect($this->broker_connection);
            $this->isConnected = true;
        }

        $id = uniqid('php_', true);

        /* $args is numeric -> positional args */
        if (array_keys($args) === range(0, count($args) - 1)) {
            $kwargs = [];
        }
        /* $args is associative -> contains kwargs */
        else {
            $kwargs = $args;
            $args = [];
        }

        /*
         *	$task_args may contain additional arguments such as eta which are useful in task execution
         *	The usecase of this field is as follows:
         *	$task_args = array( 'eta' => "2014-12-02T16:00:00" );
         */
        $task_array = array_merge(
            [
                'id' => $id,
                'task' => $task,
                'args' => $args,
                'kwargs' => (object)$kwargs,
            ],
            $task_args
        );

        $task = json_encode($task_array);
        $params = [
            'content_type' => 'application/json',
            'content_encoding' => 'UTF-8',
            'immediate' => false,
        ];

        if ($this->broker_connection_details['persistent_messages']) {
            $params['delivery_mode'] = 2;
        }

        $this->broker_connection_details['routing_key'] = $routing_key;

        $success = $this->broker_amqp->PostToExchange(
            $this->broker_connection,
            $this->broker_connection_details,
            $task,
            $params
        );

        if (!$success) {
            throw new CeleryPublishException();
        }

        if ($async_result) {
            return new AsyncResult($id, $this->backend_connection_details, $task_array['task'], $args);
        } else {
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
