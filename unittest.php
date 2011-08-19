<?php

/*
 * INSTALL:
pear channel-discover pear.phpunit.de
pear channel-discover components.ez.no
pear channel-discover pear.symfony-project.com
pear install phpunit/PHPUnit

 * RUN:
phpunit CeleryTest unittest.php
 */

require_once('celery.php');

function get_c()
{
	return new Celery('localhost', 'gdr', 'test', 'wutka', 'celery', 'celery');
}

class CeleryTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @expectedException CeleryException
	 */
	public function testArgsValidation()
	{
		$c = get_c();

		$c->PostTask('task.test', 'arg');
	}
}
