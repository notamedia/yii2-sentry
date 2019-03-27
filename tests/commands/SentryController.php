<?php

namespace tests\commands;

use yii\console\Controller;
use yii\log\Logger;

class SentryController extends Controller
{
    /**
     * Send test messages to the Sentry.
     */
    public function actionFill()
    {
        /* @var $logger \yii\log\Logger */
        $logger = \Yii::createObject(Logger::class);
        \Yii::setLogger($logger);
        \Yii::$app->log->setLogger(\Yii::getLogger());

        foreach ($this->logsProvider() as $log) {
            \Yii::getLogger()->log($log['message'], $log['level'], $log['category']);
        }

        \Yii::getLogger()->flush();
    }

    protected function logsProvider()
    {
        return [
            [
                'level' => Logger::LEVEL_ERROR,
                'message' => [
                    'msg' => new \RuntimeException('Connection error', 999, new \Exception),
                    'extra' => 'Hello, World!',
                    'tags' => ['db-name' => 'bulling']
                ],
                'category' => 'dbms'
            ],
            [
                'level' => Logger::LEVEL_ERROR,
                'message' => new \RuntimeException('Oops... This is exception.', 999, new \Exception),
                'category' => 'exceptions'
            ],
            [
                'level' => Logger::LEVEL_INFO,
                'message' => [
                    'msg' => 'Message from bulling service',
                    'extra' => 'Hello, World!',
                    'tags' => ['currency' => 'RUB']
                ],
                'category' => 'monitoring'
            ],
            [
                'level' => Logger::LEVEL_WARNING,
                'message' => 'Invalid request',
                'category' => 'UI'
            ]
        ];
    }
}
