<?php
/**
 * Created by PhpStorm.
 * User: nikolas
 * Date: 01.04.14
 * Time: 15:22
 */

require 'predis/autoload.php';
Predis\Autoloader::register();


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
        return CJSON::encode($var);
    }

    public function toDict($raw_json) {
        return CJSON::decode($raw_json);
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
            'database' => $details['vhost']
        ]);
        return $connect;
    }
}
