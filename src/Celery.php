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

namespace Celery;

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
     * @param int result_expire Expire time for result queue, milliseconds (for AMQP exchanges only)
     * @param array ssl_options Used only for 'php-amqplib-ssl' connections, an associative array with values as defined here: http://php.net/manual/en/context.ssl.php
     */
    public function __construct($host, $login, $password, $vhost, $exchange='celery', $binding='celery', $port=5672, $connector=false, $result_expire=0, $ssl_options=[])
    {
        $broker_connection = [
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
        ];
        $backend_connection = $broker_connection;

        $items = $this->BuildConnection($broker_connection);
        $items = $this->BuildConnection($backend_connection, true);
    }
}
