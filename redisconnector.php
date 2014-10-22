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

    public function getHeaders() {
        return array();
    }

    public function getMessage($task) {

        $result = [];
        $result['body'] = base64_encode($task);
        $result['headers'] = $this->getHeaders();
        $result['content-type'] = $this->content_type;
        $result['content-encoding'] = 'binary';
        return $result;
    }

    public function getDeliveryMode() {
        return 2;
    }

    public function toStr($var){
        return json_encode($var);
    }

    public function toDict($raw_json) {
        return json_decode($raw_json, TRUE);
    }

    public function PostToExchange($connection, $details, $task, $params) {

        $connection = $this->Connect($connection);
        $body = json_decode($task, true);
        $message = $this->getMessage($task);
        $message['properties'] = [
            'body_encoding' => 'base64',
            'reply_to' => $body['id'],
            'delivery_info' => [
                'priority' => 0,
                'routing_key' => $details['binding'],
                'exchange' => $details['exchange'],
            ],
            'delivery_mode' => $this->getDeliveryMode(),
            'delivery_tag'  => $body['id']
        ];
        $connection->lPush($details['exchange'], $this->toStr($message));
		return TRUE;
    }

    public function Connect($connection) {
        if ($connection->isConnected()) {
            return $connection;
        } else {
            $connection->connect();
            return $connection;
        }

    }

    public function getResultKey($task_id) {
        return sprintf("%s%s", $this->celery_result_prefix, $task_id);
    }

    public function finalizeResult($connection, $task_id) {
        if ($connection->exists($this->getResultKey($task_id))) {
            $connection->del($this->getResultKey($task_id));
            return true;
        }
        return false;
    }

    public function GetMessageBody($connection, $task_id) {
        $result = $connection->get($this->getResultKey($task_id));
        if ($result) {
            $redis_result = $this->toDict($result, true);
            $result = [
                'complete_result' => $redis_result['status'],
                'body' => json_encode($redis_result)
            ];
            $this->finalizeResult($connection, $task_id);
            return $result;
        }
        else {
            return false;
        }
    }

    function GetConnectionObject($details) {
        $connect = new Predis\Client([
            'scheme' => 'tcp',
            'host'   => $details['host'],
            'port'   => $details['port'],
            'database' => $details['vhost'],
            'password' => empty($details['password']) ? null : $details['password']
        ]);
        return $connect;
    }
}
