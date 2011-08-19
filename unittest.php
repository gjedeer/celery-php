<?php

/*
 * INSTALL:
pear upgrade
pear channel-discover pear.phpunit.de
pear channel-discover components.ez.no
pear channel-discover pear.symfony-project.com
pear install --alldeps phpunit/PHPUnit

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

	public function testCorrectOperation()
	{
		$c = get_c();

		$result = $c->PostTask('tasks.add', array(2,2));

		for($i = 0; $i < 10; $i++)
		{
			if($result->isReady())
			{
				break;
			}
			else
			{
				sleep(1);
			}
		}
		$this->assertTrue($result->isReady());

		$this->assertTrue($result->isSuccess());
		$this->assertEquals(4, $result->getResult());
	}

	public function testFailingOperation()
	{
		$c = get_c();

		$result = $c->PostTask('tasks.fail', array());

		for($i = 0; $i < 10; $i++)
		{
			if($result->isReady())
			{
				break;
			}
			else
			{
				sleep(1);
			}
		}
		$this->assertTrue($result->isReady());

		$this->assertFalse($result->isSuccess());
		$this->assertGreaterThan(1, strlen($result->getTraceback()));
	}

	/**
	 * @expectedException CeleryException
	 */
	public function testPrematureGet()
	{
		$c = get_c();

		$result = $c->PostTask('tasks.fail', array());
		$result->isReady();
	}
}
