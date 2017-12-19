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
sudo apt-get remove phpunit
composer global require 'phpunit/phpunit'

 * RUN:
 phpunit
#or:
 phpunit CeleryAMQPLibTest unittest/CeleryAMQPLibTest.php
 */

namespace Celery\Tests;

abstract class CeleryTest extends \PHPUnit_Framework_TestCase
{
    private static function waitForReady(
        \Celery\AsyncResult $result,
        $max = 10
    ) {
        for ($i = 0; $i < $max; $i++) {
            if ($result->isReady()) {
                return;
            } else {
                sleep(1);
            }
        }
    }

    /**
     * @expectedException \Celery\CeleryException
     */
    public function testArgsValidation()
    {
        $c = $this->get_c();

        $c->PostTask('task.test', 'arg');
    }

    public function testCorrectOperation()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.add', [2, 2]);

        self::waitForReady($result);
        $this->assertTrue($result->isReady());

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(4, $result->getResult());
    }

    public function testFailingOperation()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.fail', []);

        self::waitForReady($result, 20);
        $this->assertTrue($result->isReady());

        $this->assertFalse($result->isSuccess());
        $this->assertGreaterThan(1, strlen($result->getTraceback()));
    }

    /**
     * @expectedException \Celery\CeleryException
     */
    public function testPrematureGet()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.delayed', []);
        $result->isSuccess();
    }

    /**
     * @expectedException \Celery\CeleryException
     */
    public function testPrematureGetTraceback()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.delayed', []);
        $result->getTraceback();
    }

    /**
     * @expectedException \Celery\CeleryException
     */
    public function testPrematureGetResult()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.delayed', []);
        $result->getResult();
    }

    public function testFailed()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.fail', []);
        $result->get();
        $this->assertTrue($result->failed());
    }

    /*
     * Test Python API
     * Based on http://www.celeryproject.org/tutorials/first-steps-with-celery/
     */
    public function testGet()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.add_delayed', [4, 4]);
        $this->assertFalse($result->ready());
        $this->assertNull($result->result);
        $rv = $result->get();
        $this->assertEquals(8, $rv);
        $this->assertEquals(8, $result->result);
        $this->assertTrue($result->successful());
    }

    public function testKwargs()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.add_delayed', ['x' => 4, 'y' => 4]);
        $this->assertFalse($result->ready());
        $this->assertNull($result->result);
        $rv = $result->get();
        $this->assertEquals(8, $rv);
        $this->assertEquals(8, $result->result);
        $this->assertTrue($result->successful());
    }

    /**
     * @expectedException \Celery\CeleryTimeoutException
     */
    public function testzzzzGetTimeLimit()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.delayed', []);
        $result->get(1, true, 0.1);
    }

    public function testStateProperty()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.delayed', []);
        $this->assertEquals($result->state, 'PENDING');
        $result->get();
        $this->assertEquals($result->state, 'SUCCESS');
    }

    /* NO-OP functions should not fail */
    public function testForget()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.add', [2, 2]);
        $result->forget();
        $result->revoke();
    }

    public function testWait()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.add', [4, 4]);
        $rv = $result->wait();
        $this->assertEquals(8, $rv);
        $this->assertEquals(8, $result->result);
        $this->assertTrue($result->successful());
    }

    public function testSerialization()
    {
        $c = $this->get_c();

        $result_tmp = $c->PostTask('tasks.add_delayed', [4, 4]);
        $result_serialized = serialize($result_tmp);
        $result = unserialize($result_serialized);
        $rv = $result->get();
        $this->assertEquals(8, $rv);
        $this->assertEquals(8, $result->result);
        $this->assertTrue($result->successful());
    }

    public function testGetAsyncResult()
    {
        $c = $this->get_c();

        $result_tmp = $c->PostTask('tasks.add', [427552, 1]);
        $id = $result_tmp->getId();
        sleep(1);
        $result = $c->getAsyncResultMessage('tasks.add', $id, null, false);
        $this->assertTrue(strpos($result['body'], '427553') >= 0);
        $result = $c->getAsyncResultMessage('tasks.add', $id, null, false);
        $this->assertTrue(strpos($result['body'], '427553') >= 0);
        $result = $c->getAsyncResultMessage('tasks.add', $id, null, true);
        $this->assertTrue(strpos($result['body'], '427553') >= 0);
    }

    public function testReturnedArray()
    {
        $c = $this->get_c();

        $result = $c->PostTask('tasks.get_fibonacci', []);
        $rv = $result->wait();
        $this->assertEquals(1, $rv[0]);
        $this->assertEquals(34, $rv[8]);
    }

    public function testDoNotKeepMessagesInQueue()
    {
        // What's tested is explained here:
        // https://github.com/gjedeer/celery-php/pull/108

        $config = new \Celery\Config([
            'keep_messages_in_queue' => false, // Default config.
        ]);
        $c = $this->get_c($config);

        // Test that AsyncResult deletes messages after fetching them.
        $result = $c->PostTask('tasks.long_running_with_progress', []);
        $serialized = serialize($result);

        // Sleep some time so we know for sure we'll get over "5% progress".
        sleep(5);

        // 1st
        self::waitForReady($result);
        $r1 = $result->getResult();
        // First result is "20% progress"
        $this->assertEquals(20, $r1->progress);

        // Sleep just the "bad" amount of time. We get right between the
        // moments when the first message ("20% progress") is already deleted,
        // but the second getResult() call will still have a timeout, because
        // the second message ("40%") is still yet to be reported.
        // See the long delay at tasks.py.
        sleep(4);

        // The original result message is DELETED by now, so expect a
        // Celery\CeleryException down below.
        $this->expectException(\Celery\CeleryException::class);

        // 2nd - this will timeout.
        $r = unserialize($serialized);
        self::waitForReady($r, 2); // Force timeout after 2 secs to save time.
        $r2 = $r->getResult();

    }

    public function testKeepMessagesInQueue()
    {
        // What's tested is explained here:
        // https://github.com/gjedeer/celery-php/pull/108

        $config1 = new \Celery\Config([
            'keep_messages_in_queue' => true, // Do not delete messages.
        ]);
        $c = $this->get_c($config1);

        // Test that AsyncResult deletes messages after fetching them.
        $result = $c->PostTask('tasks.long_running_with_progress', []);
        $serialized = serialize($result);

        // Sleep some time so we know for sure we'll get over "5% progress".
        sleep(5);

        // 1st
        self::waitForReady($result);
        $r1 = $result->getResult();
        // First result is "20% progress"
        $this->assertEquals(20, $r1->progress);

        // Sleep just the "bad" amount of time. (See the test above.)
        sleep(4);

        // 2nd
        $r = unserialize($serialized);
        self::waitForReady($r, 2);
        $r2 = $r->getResult();
        // Second result refers to the same "20% progress"
        $this->assertEquals(20, $r2->progress);

        // 3nd
        $r = unserialize($serialized);
        self::waitForReady($r, 2);
        $r3 = $r->getResult();
        // Third result refers to the same  "20% progress"
        $this->assertEquals(20, $r3->progress);

        // 4th
        $r = unserialize($serialized);
        self::waitForReady($r, 2);
        $r4 = $r->getResult();
        // Fourth result refers to the same  "20% progress"
        $this->assertEquals(20, $r4->progress);

        // Because the result message is NOT deleted from the queue after
        // fetching it, all getResult methods of the same AsyncResult being
        // unserialized return (refer to) the same specific result message.

    }

}
