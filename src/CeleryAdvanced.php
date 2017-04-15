<?php

namespace Celery;

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
    public function __construct($broker_connection, $backend_connection=false)
    {
        if ($backend_connection == false) {
            $backend_connection = $broker_connection;
        }

        $items = $this->BuildConnection($broker_connection);
        $items = $this->BuildConnection($backend_connection, true);
    }
}
