<?php

namespace notamedia\sentry\tests\unit;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\Options;
use Sentry\Stacktrace;
use yii\log\Logger;
use ReflectionClass;
use RuntimeException;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Codeception\Test\Unit;
use Sentry\ClientInterface;
use notamedia\sentry\SentryTarget;

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

    public function testExceptionPassing()
    {
        $sentryTarget = $this->getConfiguredSentryTarget();

        $logData = [
            'message' => 'This exception was caught, but still needs to be reported',
            'exception' => new RuntimeException('Package loss detected'),
            'something_extra' => ['foo' => 'bar'],
        ];

        $messageWasSent = false;

        $client = $this->getClientMock();
        $client->expects($this->once())
            ->method('captureEvent')
            ->willReturnCallback(function (Event $event, ?EventHint $hint = null, ?Scope $scope = null) use ($logData, &$messageWasSent): ?EventId {
                $messageWasSent = true;
                $this->assertSame($logData['exception'], $hint->exception);
                $this->assertSame($logData['message'], $event->getMessage());

                return EventId::generate();
            });

        SentrySdk::getCurrentHub()->bindClient($client);

        $sentryTarget->collect([[$logData, Logger::LEVEL_INFO, 'application', 1481513561.197593, []]], true);
        $this->assertTrue($messageWasSent);
    }

    public function messageDataProvider()
    {
        $msg = 'A message';

        yield [$msg, $msg];

        yield [$msg, ['msg' => $msg]];

        yield [$msg, ['message' => $msg]];

        yield [$msg, ['message' => $msg, 'msg' => 'Ignored']];
    }

    /**
     * @dataProvider messageDataProvider
     */
    public function testMessageConverting($expectedMessageText, $loggedMessage)
    {
        $sentryTarget = $this->getConfiguredSentryTarget();
        $messageWasSent = false;

        $client = $this->getClientMock();
        $client->expects($this->once())
               ->method('captureEvent')
               ->willReturnCallback(function (Event $event, ?EventHint $hint = null, ?Scope $scope = null) use ($expectedMessageText, &$messageWasSent): ?EventId {
                   $messageWasSent = true;
                   $this->assertSame($expectedMessageText, $event->getMessage());

                   return EventId::generate();
               });

        SentrySdk::getCurrentHub()->bindClient($client);

        $sentryTarget->collect([[$loggedMessage, Logger::LEVEL_INFO, 'application', 1481513561.197593, []]], true);
        $this->assertTrue($messageWasSent);
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
     * - creates Sentry object
     * @see SentryTarget::collect
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
     * @see SentryTarget::export
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

    public function breadcrumbsDataProvider()
    {
        $msg = 'A message';

        yield [
            [
                ['msg' => $msg, 'category' => 'category']
            ],
            $msg,
            Breadcrumb::TYPE_DEFAULT,
            'category',
            1
        ];
        yield [
            [
                ['msg' => $msg, 'category' => 'Multiple'],
                ['msg' => $msg, 'category' => 'Multiple'],
                ['msg' => $msg, 'category' => 'Multiple'],
            ],
            $msg,
            Breadcrumb::TYPE_DEFAULT,
            'Multiple',
            3
        ];
        yield [
            [
                [
                    'msg' => ['msg' => $msg, 'type' => Breadcrumb::TYPE_HTTP],
                    'category' => 'Test type'
                ],
            ],
            $msg,
            Breadcrumb::TYPE_HTTP,
            'Test type',
            1
        ];
    }

    /**
     * Test breadcrumbs adding in collect method
     * @dataProvider breadcrumbsDataProvider
     */
    public function testBreadcrumbs($breadcrumbs, $msg, $type, $category, $count)
    {
        $sentryTarget = $this->getConfiguredSentryTarget();

        $client = $this->getClientMock();
        $client->expects($this->exactly($count))
            ->method('captureEvent')
            ->willReturnCallback(function (Event $event, ?EventHint $hint = null, ?Scope $scope = null) use ($msg, $type, $category, $count): ?EventId {
                $scope->applyToEvent($event, $hint);
                $breadcrumbs = $event->getBreadcrumbs();
                $this->assertEquals($count, count($breadcrumbs));

                foreach ($breadcrumbs as $breadcrumb) {
                    $this->assertEquals($type, $breadcrumb->getType());
                    $this->assertEquals($msg, $breadcrumb->getMessage());
                    $this->assertEquals($category, $breadcrumb->getCategory());
                }

                return EventId::generate();
            });

        SentrySdk::getCurrentHub()->bindClient($client);

        $lastEvent = array_pop($breadcrumbs);
        foreach ($breadcrumbs as $breadcrumb) {
            $sentryTarget->collect([[$breadcrumb['msg'], Logger::LEVEL_INFO, $breadcrumb['category'], microtime(true), []]], false);
        }
        $sentryTarget->collect([[$lastEvent['msg'], Logger::LEVEL_INFO, $lastEvent['category'], microtime(true), []]], true);
    }

    public function stacktraceDataProvider()
    {
        yield [
            []
        ];

        yield [
            [['file' => '/dir/file1.php', 'line'=> 42]]
        ];

        yield [
            [['file' => '/dir/file1.php', 'line'=> 42], ['file' => '/dir/file2.php', 'line'=> 30]]
        ];
    }

    /**
     * Testing method buildStacktrace()
     *
     * @dataProvider stacktraceDataProvider
     */
    public function testBuildStacktrace($traces)
    {
        $class = new ReflectionClass(SentryTarget::className());
        $method = $class->getMethod('buildStacktrace');
        $method->setAccessible(true);

        $sentryTarget = $this->getConfiguredSentryTarget();

        /**
         * @var $stacktrace Stacktrace
         */
        $stacktrace = $method->invokeArgs($sentryTarget, [$traces]);

        if(empty($traces))
            $this->assertNull($stacktrace);
        else {
            // Plus one final frame
            $expects = count($traces) + 1;

            $this->assertEquals($expects, count($stacktrace->getFrames()));
        }

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
     * Returns configured client object
     *
     * @return \PHPUnit\Framework\MockObject\MockObject|ClientInterface
     */
    protected function getClientMock()
    {
        $client = $this->createConfiguredMock(ClientInterface::class, [
            'getOptions' => new Options()
        ]);
        return $client;
    }

    /**
     * Returns reflected 'client' property
     *
     * @param SentryTarget $sentryTarget
     * @return \ReflectionProperty
     */
    protected function getAccessibleClientProperty(SentryTarget $sentryTarget)
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
