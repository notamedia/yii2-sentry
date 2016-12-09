<?php

namespace notamedia\relation\tests\unit;

use yii\codeception\TestCase;

/**
 * Unit-tests for SentryTarget
 */
class SentryTargetTest extends TestCase
{
    /** @var string */
    public $appConfig = '@tests/unit/config.php';

    /**
     * Dummy test case
     */
    public function testAssert()
    {
        $this->assertEquals(1, 1);
    }

}