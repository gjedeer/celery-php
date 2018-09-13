<?php

namespace Celery;

require_once __DIR__ . "/CeleryException.php";

/**
 * Emited by CeleryAbstract::PostTask() connection failures etc
 * @package celery-php
 */
class CeleryConnectionException extends CeleryException
{
}
