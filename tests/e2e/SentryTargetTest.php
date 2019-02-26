<?php

namespace notamedia\sentry\tests\e2e;

use Codeception\Test\Unit;
use yii\log\Logger;

class SentryTargetTest extends Unit
{
    protected function _before()
    {
        parent::_before();

        /* @var $logger \yii\log\Logger */
        $logger = \Yii::createObject(Logger::class);
        \Yii::setLogger($logger);
        \Yii::$app->log->setLogger(\Yii::getLogger());
    }

    /**
     * @dataProvider logsProvider
     */
    public function testWriteLog($level, $message, $category)
    {
        \Yii::getLogger()->log($message, $level, $category);
    }

    public function logsProvider()
    {
        return [
            [
                Logger::LEVEL_ERROR,
                'Simple Message',
                'simple-category'
            ],
            [
                Logger::LEVEL_INFO,
                ['msg' => 'Message', 'extra' => 'value'],
                'category'],
            [
                Logger::LEVEL_WARNING,
                'Warning',
                'subcategory'],
            [
                Logger::LEVEL_TRACE,
                'Trace',
                'subcategory'
            ],
        ];
    }
}
