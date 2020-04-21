<?php

namespace tests\commands;

use RuntimeException;
use tests\models\User;
use Yii;
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
        $logger = Yii::createObject(Logger::class);
        Yii::setLogger($logger);
        Yii::$app->log->setLogger(Yii::getLogger());

        foreach ($this->logsProvider() as $log) {
            Yii::$app->user->logout();
            if (isset($log['user'])) {
                Yii::$app->user->login($log['user']);
            }
            $_SERVER['REMOTE_ADDR'] = $log['ip'] ?? null;

            Yii::getLogger()->log($log['message'], $log['level'], $log['category']);
            // We need to final flush logs for change ip and user on fly
            Yii::getLogger()->flush(true);
        }
    }

    protected function logsProvider()
    {
        return [
            [
                'level' => Logger::LEVEL_ERROR,
                'message' => [
                    'msg' => new RuntimeException('Connection error', 999, new \Exception),
                    'extra' => 'Hello, World!',
                    'tags' => ['db-name' => 'bulling'],
                ],
                'category' => 'dbms',
            ],
            [
                'level' => Logger::LEVEL_ERROR,
                'message' => new RuntimeException('Oops... This is exception.', 999, new \Exception),
                'category' => 'exceptions',
                'user' => new User(['id' => 42]),
                'ip' => '127.0.0.42',
            ],
            [
                'level' => Logger::LEVEL_INFO,
                'message' => [
                    'msg' => 'Message from bulling service',
                    'extra' => 'Hello, World!',
                    'tags' => ['currency' => 'RUB'],
                ],
                'category' => 'monitoring',
                'user' => new User(['id' => 543]),
                'ip' => '2607:f0d0:1002:51::4',
            ],
            [
                'level' => Logger::LEVEL_WARNING,
                'message' => 'Invalid request',
                'category' => 'UI',
            ],
            [
                'level' => null,
                'message' => [1, 2, 3],
                'category' => null,
            ],
            [
                'level' => '',
                'message' => ['one' => 'value 1', 'two' => 'value 2'],
                'category' => null,
            ],
        ];
    }
}
