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
class CeleryException extends Exception
{
}

/**
 * Emited by AsyncResult::get() on timeout
 * @package celery-php
 */
class CeleryTimeoutException extends CeleryException
{
}

/**
 * Client for a Celery server
 * @package celery-php
 */
class Celery
{
    private $connection = null; // AMQPConnection object
    private $connection_details = array(); // array of strings required to connect

    public function __construct(
        $host,
        $login,
        $password,
        $vhost,
        $exchange = 'celery',
        $binding = 'celery',
        $port = 5672
    ) {
        if (!class_exists('AMQPConnection')) {
            throw new CeleryException(
                "Class AMQPConnection not found\n" .
                "Make sure that AMQP extension is installed and enabled:\n" .
                "http://www.php.net/manual/en/amqp.installation.php"
            );
        }

        foreach (array('host', 'login', 'password', 'vhost', 'exchange', 'binding', 'port') as $detail) {
            $this->connection_details[$detail] = $$detail;
        }

        $this->connection = Celery::InitializeAMQPConnection($this->connection_details);

        #$success = $this->connection->connect();
    }

    public static function InitializeAMQPConnection($details)
    {
        $connection = new AMQPConnection();
        $connection->setHost($details['host']);
        $connection->setLogin($details['login']);
        $connection->setPassword($details['password']);
        $connection->setVhost($details['vhost']);
        $connection->setPort($details['port']);

        return $connection;
    }

    /**
     * Post a task to Celery
     * @param string $task Name of the task, prefixed with module name (like tasks.add for function add() in task.py)
     * @param array $args Array of arguments (kwargs call when $args is associative)
     * @param int $retries Current number of times this task has been retried. Defaults to 0 if not specified.
     * @param string|int $eta Estimated time of arrival. This is the date and time in ISO 8601 format.
     * If not provided the message is not scheduled, but will be executed asap.
     * @param string|int $expires Expiration date. This is the date and time in ISO 8601 format.
     * If not provided the message will never expire. The message will be expired when the message is received
     * and the expiration date has been exceeded.
     * @param array $extensions
     *  * string 'taskset' The taskset this task is part of (if any).
     *  * string 'chord' Signifies that this task is one of the header parts of a chord.
     *      The value of this key is the body of the cord that should be executed when all of
     *      the tasks in the header has returned.
     *  * bool 'utc' If true time uses the UTC timezone, if not the current local timezone should be used.
     *  * string[] 'callbacks' A list of subtasks to apply if the task exited successfully.
     *  * string[] 'errbacks' A list of subtasks to apply if an error occurs while executing the task.
     * @throws CeleryException
     * @return AsyncResult
     */
    public function PostTask($task, $args, $retries = 0, $eta = null, $expires = null, array $extensions = array())
    {
        $this->connection->connect();
        if (!is_array($args)) {
            throw new CeleryException("Args should be an array");
        }
        $id = uniqid('php_', true);
        $ch = new AMQPChannel($this->connection);
        $xchg = new AMQPExchange($ch);
        $xchg->setName($this->connection_details['exchange']);

        /* $args is numeric -> positional args */
        if (array_keys($args) === range(0, count($args) - 1)) {
            $kwargs = array();
        } /* $args is associative -> contains kwargs */
        else {
            $kwargs = $args;
            $args = array();
        }

        $retries = intval($retries);
        if ($retries < 0) {
            $retries = 0;
        }

        // eta ISO 8601 date
        if (!empty($eta)) {
            $eta = $this->createISO8601Date($eta);
        }

        // expires ISO 8601 date
        if (!empty($expires)) {
            $expires = $this->createISO8601Date($expires);
        }

        // check params for extensions
        foreach ($extensions as $extension => &$value) {
            switch ($extension) {
                default:
                    // unknown extension... pass it as is
                    break;
                case 'taskset':
                case 'chord':
                    $value = strval($value);
                    break;
                case 'utc':
                    $value = (bool) $value;
                    break;
                case 'errbacks':
                case 'callbacks':
                    if (!is_array($value)) {
                        throw new CeleryException('Wrong callback param');
                    }

                    $value = array_map('strval', $value);
                    break;
            }
        }
        unset($value);

        $task_array = array(
            'id' => $id,
            'task' => $task,
            'args' => $args,
            'kwargs' => (object) $kwargs,
            'retries' => $retries,
            'eta' => $eta,
            'expires' => $expires,
            'extensions' => $extensions
        );
        $task = json_encode($task_array);
        $params = array(
            'content_type' => 'application/json',
            'content_encoding' => 'UTF-8',
            'immediate' => false,
        );

        $success = $xchg->publish($task, $this->connection_details['exchange'], 0, $params);
        $this->connection->disconnect();
        if (!$success) {
            throw new CeleryException('Failed publishing task');
        }

        return new AsyncResult($id, $this->connection_details, $task_array['task'], $args);
    }

    /**
     * Checks (or convert) ISO8601 date
     *
     * @param int|string $date Timestamp to convert to ISO8601 date or string to check if it's correct date
     * @return string ISO8601 date
     * @throws CeleryException
     */
    protected function createISO8601Date($date)
    {
        if (is_string($date) && strval(intval($date)) !== strval($date)) {
            $parsedDate = date_parse_from_format(DateTime::ISO8601, $date);
            if (!is_array($parsedDate) || (isset($parsedDate['error_count']) && $parsedDate['error_count'] > 0) ||
                !isset($parsedDate['year']) || !isset($parsedDate['month']) || !isset($parsedDate['day']) ||
                !checkdate($parsedDate['month'], $parsedDate['day'], $parsedDate['year'])
            ) {
                throw new CeleryException('Wrong date param');
            }
        } else if (is_numeric($date)) {
            $date = date('c', intval($date));
        } else {
            throw new CeleryException('Wrong date param');
        }

        return $date;
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
    private $complete_result; // AMQPEnvelope instance
    private $body; // decoded array with message body (whatever Celery task returned)

    /**
     * Don't instantiate AsyncResult yourself, used internally only
     * @param string $id Task ID in Celery
     * @param array $connection_details used to initialize AMQPConnection, keys are the same as args to __construct
     * @param string task_name
     * @param array task_args
     */
    public function __construct($id, $connection_details, $task_name = null, $task_args = null)
    {
        $this->task_id = $id;
        $this->connection = Celery::InitializeAMQPConnection($connection_details);
        $this->connection_details = $connection_details;
        $this->task_name = $task_name;
        $this->task_args = $task_args;
    }

    public function __wakeup()
    {
        if ($this->connection_details) {
            $this->connection = Celery::InitializeAMQPConnection($this->connection_details);
        }
    }

    /**
     * Connect to queue, see if there's a result waiting for us
     * Private - to be used internally
     */
    private function getCompleteResult()
    {
        if ($this->complete_result) {
            return $this->complete_result;
        }

        $this->connection->connect();
        $ch = new AMQPChannel($this->connection);
        $q = new AMQPQueue($ch);
        $q->setName($this->task_id);
        $q->setFlags(AMQP_AUTODELETE);
        // $q->setArgument('x-expires', 86400000);
        $q->declare();
        try {
            $q->bind('celeryresults', $this->task_id);
        } catch (AMQPQueueException $e) {
            return false;
        }

        $message = $q->get(AMQP_AUTOACK);

        if (!$message) {
            return false;
        }

        $this->complete_result = $message;

        if ($message->getContentType() != 'application/json') {
            $q->delete();
            $this->connection->disconnect();

            throw new CeleryException('Response was not encoded using JSON - found ' .
                $message->getContentType() .
                ' - check your CELERY_RESULT_SERIALIZER setting!');
        }

        $this->body = json_decode($message->getBody());

        $q->delete();
        $this->connection->disconnect();

        return false;
    }

    /**
     * Get the Task Id
     * @return string
     */
    public function getId()
    {
        return $this->task_id;
    }

    /**
     * Check if a task result is ready
     * @return bool
     */
    public function isReady()
    {
        return ($this->getCompleteResult() !== false);
    }

    /**
     * Return task status (needs to be called after isReady() returned true)
     * @throws CeleryException
     * @return string 'SUCCESS', 'FAILURE' etc - see Celery source
     */
    public function getStatus()
    {
        if (!$this->body) {
            throw new CeleryException('Called getStatus before task was ready');
        }
        return $this->body->status;
    }

    /**
     * Check if task execution has been successful or resulted in an error
     * @return bool
     */
    public function isSuccess()
    {
        return ($this->getStatus() == 'SUCCESS');
    }

    /**
     * If task execution wasn't successful, return a Python traceback
     * @throws CeleryException
     * @return string
     */
    public function getTraceback()
    {
        if (!$this->body) {
            throw new CeleryException('Called getTraceback before task was ready');
        }
        return $this->body->traceback;
    }

    /**
     * Return a result of successful execution.
     * In case of failure, this returns an exception object
     * @throws CeleryException
     * @return mixed Whatever the task returned
     */
    public function getResult()
    {
        if (!$this->body) {
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
    public function failed()
    {
        return $this->isReady() && !$this->isSuccess();
    }

    /**
     * Forget about (and possibly remove the result of) this task
     * @TODO Currently does nothing in PHP client
     */
    public function forget()
    {
    }

    /**
     * Wait until task is ready, and return its result.
     * @param float|int $timeout How long to wait, in seconds, before the operation times out
     * @param bool $propagate (TODO - not working) Re-raise exception if the task failed.
     * @param float $interval Time to wait (in seconds) before retrying to retrieve the result
     * @throws CeleryTimeoutException
     * @return mixed result on both success and failure
     */
    public function get($timeout = 10, $propagate = true, $interval = 0.5)
    {
        $interval_us = (int)($interval * 1000000);
        $iteration_limit = (int)($timeout / $interval);

        for ($i = 0; $i < $iteration_limit; $i++) {
            if ($this->isReady()) {
                break;
            }

            usleep($interval_us);
        }

        if (!$this->isReady()) {
            throw new CeleryTimeoutException(sprintf(
                'AMQP task %s(%s) did not return after 10 seconds',
                $this->task_name,
                json_encode($this->task_args)
            ), 4);
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
        if ($property == 'result') {
            if ($this->isReady()) {
                return $this->getResult();
            } else {
                return null;
            }
        } /**
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
         * The task raised an exception, or has exceeded the retry limit.
         * The result attribute then contains the exception raised by the task.
         *
         * SUCCESS
         * The task executed successfully. The result attribute then contains the tasks return value.
         *
         * status: Deprecated alias of state.
         */
        elseif ($property == 'state' || $property == 'status') {
            if ($this->isReady()) {
                return $this->getStatus();
            } else {
                return 'PENDING';
            }
        }

        return $this->$property;
    }

    /**
     * Returns True if the task has been executed.
     * If the task is still running, pending, or is waiting for retry then False is returned.
     */
    public function ready()
    {
        return $this->isReady();
    }

    /**
     * Send revoke signal to all workers
     * @TODO Does nothing in PHP client
     */
    public function revoke()
    {
    }

    /**
     * Returns True if the task executed successfully.
     */
    public function successful()
    {
        return $this->isSuccess();
    }

    /**
     * Deprecated alias to get()
     */
    public function wait($timeout = 10, $propagate = true, $interval = 0.5)
    {
        return $this->get($timeout, $propagate, $interval);
    }
}

