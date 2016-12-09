<?php

namespace notamedia\relation\tests\unit;

use notamedia\sentry\SentryTarget;
use ReflectionClass;
use yii\codeception\TestCase;
use yii\log\Logger;

/**
 * Unit-tests for SentryTarget
 */
class SentryTargetTest extends TestCase
{
    /** @var string */
    public $appConfig = '@tests/unit/config.php';

    /**
     * Testing method getContextMessage()
     * - returns empty string ''
     * @see SentryTarget::getContextMessage
     */
    public function testContextMessageShouldBeEmpty()
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
    public function testLogLevels() {
        //valid level names
        $levelNames = [
            'info',
            'error',
            'warning',
            'debug',
        ];

        $loggerClass = new ReflectionClass(Logger::className());
        $loggerLevelConstants = $loggerClass->getConstants();
        foreach($loggerLevelConstants as $constant => $value) {
            if (strpos($constant, 'LEVEL_') === 0) {
                $level = SentryTarget::getLevelName($value);
                $this->assertNotEmpty($level);
                $this->assertTrue(in_array($level, $levelNames), sprintf('Level "%s" is incorrect', $level));
            }
        }
    }

    /**
     * Testing methods collect() and export()
     * - assigns messages to Target property
     * - creates Raven_Client object
     * - Raven_Client::capture is called on export()
     * @see SentryTarget::collect
     * @see SentryTarget::export
     */
    public function testCollectAndExport() {
        //test messages
        $messages = [
            ['test', Logger::LEVEL_INFO, 'test', microtime(true), []],
            ['test 2', Logger::LEVEL_INFO, 'test 2', microtime(true), []]
        ];

        //configure target
        $sentryTarget = new SentryTarget();
        $sentryTarget->exportInterval = 100;
        $sentryTarget->setLevels(Logger::LEVEL_INFO);

        //set client property accessible
        $sentryTargetClass = new ReflectionClass(SentryTarget::className());
        $clientProperty = $sentryTargetClass->getProperty('client');
        $clientProperty->setAccessible(true);

        $sentryTarget->collect($messages, false);
        $this->assertEquals(count($messages), count($sentryTarget->messages));
        
        $this->assertInstanceOf(\Raven_Client::class, $clientProperty->getValue($sentryTarget));

        //create Raven_Client mock
        $clientMock = self::createMock(\Raven_Client::class);
        $clientMock->expects($this->exactly(count($messages)))->method('capture');
        $clientProperty->setValue($sentryTarget, $clientMock);
        
        $sentryTarget->export();
    }

}