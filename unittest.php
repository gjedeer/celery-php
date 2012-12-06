<?php
// phpunit CeleryTest unittest.php
/*
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

require_once('celery.php');

class CeleryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @return Celery
     */
    protected function getConnection()
    {
        return new Celery(
            'localhost', // host
            'guest', // login
            'guest', // password
            '/', // vhost
            'celery',  // exchange
            'celery', // binding
            5672 // port
        );
    }

    /**
     * @expectedException CeleryException
     */
    public function testArgsValidation()
    {
        $c = $this->getConnection();

        $c->PostTask('task.test', 'arg');
    }

    public function testCorrectOperation()
    {
        $c = $this->getConnection();

        $result = $c->PostTask('tasks.add', array(2, 2));

        for ($i = 0; $i < 10; $i++) {
            if ($result->isReady()) {
                break;
            } else {
                sleep(1);
            }
        }
        $this->assertTrue($result->isReady());

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(4, $result->getResult());
    }

    public function testFailingOperation()
    {
        $c = $this->getConnection();

        $result = $c->PostTask('tasks.fail', array());

        for ($i = 0; $i < 20; $i++) {
            if ($result->isReady()) {
                break;
            } else {
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
        $c = $this->getConnection();

        $result = $c->PostTask('tasks.delayed', array());
        $result->isSuccess();
    }

    /**
     * @expectedException CeleryException
     */
    public function testPrematureGetTraceback()
    {
        $c = $this->getConnection();

        $result = $c->PostTask('tasks.delayed', array());
        $result->getTraceback();
    }

    /**
     * @expectedException CeleryException
     */
    public function testPrematureGetResult()
    {
        $c = $this->getConnection();

        $result = $c->PostTask('tasks.delayed', array());
        $result->getResult();
    }

    public function testFailed()
    {
        $c = $this->getConnection();

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
        $c = $this->getConnection();

        $result = $c->PostTask('tasks.add_delayed', array(4, 4));
        $this->assertFalse($result->ready());
        $this->assertNull($result->result);
        $rv = $result->get();
        $this->assertEquals(8, $rv);
        $this->assertEquals(8, $result->result);
        $this->assertTrue($result->successful());
    }

    public function testKwargs()
    {
        $c = $this->getConnection();

        $result = $c->PostTask('tasks.add_delayed', array('x' => 4, 'y' => 4));
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
        $c = $this->getConnection();

        $result = $c->PostTask('tasks.delayed', array());
        $result->get(1, true, 0.1);
    }

    public function testStateProperty()
    {
        $c = $this->getConnection();

        $result = $c->PostTask('tasks.delayed', array());
        $this->assertEquals($result->state, 'PENDING');
        $result->get();
        $this->assertEquals($result->state, 'SUCCESS');
    }

    /* NO-OP functions should not fail */
    public function testForget()
    {
        $c = $this->getConnection();

        $result = $c->PostTask('tasks.add', array(2, 2));
        $result->forget();
        $result->revoke();
    }

    public function testWait()
    {
        $c = $this->getConnection();

        $result = $c->PostTask('tasks.add', array(4, 4));
        $rv = $result->wait();
        $this->assertEquals(8, $rv);
        $this->assertEquals(8, $result->result);
        $this->assertTrue($result->successful());
    }
}
