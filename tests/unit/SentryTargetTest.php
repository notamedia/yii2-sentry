<?php

namespace notamedia\sentry\tests\unit;

use Codeception\Test\Unit;
use notamedia\sentry\SentryTarget;
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
     * @see SentryTarget::getContextMessage
     */
    public function testGetContextMessage()
    {
        $class = new ReflectionClass(SentryTarget::className());
        $method = $class->getMethod('getContextMessage');
        $method->setAccessible(true);

        $sentryTarget = new SentryTarget();
        $result = $method->invokeArgs($sentryTarget, []);

        $this->assertEmpty($result);
    }

    /**
     * Testing method getLevelName()
     * - returns level name for each logger level
     * @see SentryTarget::getLevelName
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
                $level = SentryTarget::getLevelName($value);
                $this->assertNotEmpty($level);
                $this->assertTrue(in_array($level, $levelNames), sprintf('Level "%s" is incorrect', $level));
            }
        }

        //check default level name
        $this->assertEquals('error', SentryTarget::getLevelName(''));
        $this->assertEquals('error', SentryTarget::getLevelName('somerandomstring' . uniqid()));
    }

    /**
     * Testing method collect()
     * - assigns messages to Target property
     * - creates Raven_Client object
     * @see SentryTarget::collect
     */
    public function testCollect()
    {
        $sentryTarget = $this->getConfiguredSentryTarget();
        $clientProperty = $this->getAccessibleClientProperty($sentryTarget);

        $sentryTarget->collect($this->messages, false);
        $this->assertEquals(count($this->messages), count($sentryTarget->messages));
        $this->assertInstanceOf('Raven_Client', $clientProperty->getValue($sentryTarget));
    }

    /**
     * Testing method export()
     * - Raven_Client::capture is called on collect([...], true)
     * - messages stack is cleaned on  collect([...], true)
     * - Raven_Client::capture is called on export()
     * @see SentryTarget::export
     */
    public function testExport()
    {
        $sentryTarget = $this->getConfiguredSentryTarget();
        $clientProperty = $this->getAccessibleClientProperty($sentryTarget);

        //set Raven_Client mock on 'client' property
        $clientMock = $this->getMockCompatible('Raven_Client');
        $clientMock->expects($this->exactly(count($this->messages) * 2))->method('capture');
        $clientProperty->setValue($sentryTarget, $clientMock);

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
     * @return SentryTarget
     * @throws \yii\base\InvalidConfigException
     */
    protected function getConfiguredSentryTarget()
    {
        $sentryTarget = new SentryTarget();
        $sentryTarget->exportInterval = 100;
        $sentryTarget->setLevels(Logger::LEVEL_INFO);

        return $sentryTarget;
    }

    /**
     * Returns reflected 'client' property
     *
     * @param SentryTarget $sentryTarget
     * @return \ReflectionProperty
     */
    protected function getAccessibleClientProperty(SentryTarget $sentryTarget) {
        $sentryTargetClass = new ReflectionClass($sentryTarget::className());
        $clientProperty = $sentryTargetClass->getProperty('client');
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
            self::createMock($className) :
            $this->getMock($className);
    }

}
