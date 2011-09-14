<?php
/*
 * LICENSE: beerware
 * GDR! wrote this file. As long as you retain this notice you can do whatever you want with this stuff. 
 * If we meet some day, and you think this stuff is worth it, you can buy GDR! a beer in return. 
 *
 * http://massivescale.net/
 * http://gdr.geekhood.net/
 * gdr@go2.pl
 */

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

// TODO python-like API

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

		for($i = 0; $i < 20; $i++)
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

		$result = $c->PostTask('tasks.delayed', array());
		$result->isSuccess();
	}

	/**
	 * @expectedException CeleryException
	 */
	public function testPrematureGetTraceback()
	{
		$c = get_c();

		$result = $c->PostTask('tasks.delayed', array());
		$result->getTraceback();
	}

	/**
	 * @expectedException CeleryException
	 */
	public function testPrematureGetResult()
	{
		$c = get_c();

		$result = $c->PostTask('tasks.delayed', array());
		$result->getResult();
	}

	public function testFailed()
	{
		$c = get_c();

		$result = $c->PostTask('tasks.fail', array());
		$result->get();
		$this->assertTrue($result->failed());
	}

	/*
	 * Test Python API
	 * Based on http://www.celeryproject.org/tutorials/first-steps-with-celery/
	 */
	public function testGet()
	{                                                                
		$c = get_c();

		$result = $c->PostTask('tasks.add_delayed', array(4,4));
		$this->assertFalse($result->ready());
		$this->assertNull($result->result);                                                                                 
		$rv = $result->get();
		$this->assertEquals(8, $rv);
		$this->assertEquals(8, $result->result);
		$this->assertTrue($result->successful());
	}

	/**
	 * @expectedException CeleryTimeoutException
	 */
	public function testGetTimeLimit()
	{
		$c = get_c();

		$result = $c->PostTask('tasks.delayed', array());
		$result->get(1, TRUE, 0.1);
	}

	public function testStateProperty()
	{
		$c = get_c();

		$result = $c->PostTask('tasks.delayed', array());
		$this->assertEquals($result->state, 'PENDING');
		$result->get();
		$this->assertEquals($result->state, 'SUCCESS');
	}

	/* NO-OP functions should not fail */
	public function testForget()
	{
		$c = get_c();

		$result = $c->PostTask('tasks.add', array(2,2));
        $result->forget();
		$result->revoke();
	}

	public function testWait()
	{                                                                
		$c = get_c();

		$result = $c->PostTask('tasks.add', array(4,4));
		$rv = $result->wait();
		$this->assertEquals(8, $rv);
		$this->assertEquals(8, $result->result);
		$this->assertTrue($result->successful());
	}
}
