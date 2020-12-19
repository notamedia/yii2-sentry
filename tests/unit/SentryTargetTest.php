<?php

namespace notamedia\sentry\tests\unit;

use Codeception\Test\Unit;
use notamedia\sentry\Target;
use ReflectionClass;
use yii\log\Logger;

/**
 * Unit-tests for SentryTarget
 */
class SentryTargetTest extends Unit
{
    /** @var array test messages */
    protected $messages = [
        ['test', Logger::LEVEL_INFO, 'test', 1481513561.197593, []],
        ['test 2', Logger::LEVEL_INFO, 'test 2', 1481513572.867054, []]
    ];

    /**
     * Testing method getContextMessage()
     * - returns empty string ''
     * @see Target::getContextMessage
     */
    public function testGetContextMessage()
    {
        $class = new ReflectionClass(Target::className());
        $method = $class->getMethod('getContextMessage');
        $method->setAccessible(true);

        $sentryTarget = new Target();
        $result = $method->invokeArgs($sentryTarget, []);

        $this->assertEmpty($result);
    }

    /**
     * Testing method getLevelName()
     * - returns level name for each logger level
     * @see Target::getLevelName
     */
    public function testGetLevelName()
    {
        //valid level names
        $levelNames = [
            'info',
            'error',
            'warning',
            'debug',
        ];

        $loggerClass = new ReflectionClass(Logger::className());
        $loggerLevelConstants = $loggerClass->getConstants();
        foreach ($loggerLevelConstants as $constant => $value) {
            if (strpos($constant, 'LEVEL_') === 0) {
                $level = Target::getLevelName($value);
                $this->assertNotEmpty($level);
                $this->assertTrue(in_array($level, $levelNames), sprintf('Level "%s" is incorrect', $level));
            }
        }

        //check default level name
        $this->assertEquals('error', Target::getLevelName(''));
        $this->assertEquals('error', Target::getLevelName('somerandomstring' . uniqid()));
    }

    /**
     * Testing method collect()
     * - assigns messages to Target property
     * - creates Sentry object
     * @see Target::collect
     */
    public function testCollect()
    {
        $sentryTarget = $this->getConfiguredSentryTarget();

        $sentryTarget->collect($this->messages, false);
        $this->assertEquals(count($this->messages), count($sentryTarget->messages));
    }

    /**
     * Testing method export()
     * - Sentry::capture is called on collect([...], true)
     * - messages stack is cleaned on  collect([...], true)
     * - Sentry::capture is called on export()
     * @see Target::export
     */
    public function testExport()
    {
        $sentryTarget = $this->getConfiguredSentryTarget();

        //test calling client and clearing messages on final collect
        $sentryTarget->collect($this->messages, true);
        $this->assertEmpty($sentryTarget->messages);

        //add messages and test simple export() method
        $sentryTarget->collect($this->messages, false);
        $sentryTarget->export();
        $this->assertEquals(count($this->messages), count($sentryTarget->messages));
    }

    /**
     * Returns configured SentryTarget object
     *
     * @return Target
     * @throws \yii\base\InvalidConfigException
     */
    protected function getConfiguredSentryTarget()
    {
        $sentryTarget = new Target();
        $sentryTarget->exportInterval = 100;
        $sentryTarget->setLevels(Logger::LEVEL_INFO);
        return $sentryTarget;
    }

    /**
     * Returns reflected 'client' property
     *
     * @param Target $sentryTarget
     * @return \ReflectionProperty
     */
    protected function getAccessibleClientProperty(Target $sentryTarget)
    {
        $sentryTargetClass = new ReflectionClass("\Sentry\Client");
        $clientProperty = $sentryTargetClass->getProperty('transport');
        $clientProperty->setAccessible(true);

        return $clientProperty;
    }

    /**
     * Compatible version of creating mock method
     *
     * @param string $className
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockCompatible($className)
    {
        return method_exists($this, 'createMock') ?
            self::createMock($className) : $this->getMock($className);
    }
}
