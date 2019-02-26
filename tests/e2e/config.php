<?php

return [
    'id' => 'app-tests',
    'class' => 'yii\console\Application',
    'basePath' => \Yii::getAlias('@tests'),
    'runtimePath' => \Yii::getAlias('@tests/_output'),
    'bootstrap' => ['log'],
    'components' => [
        'log' => [
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                ],
                [
                    'class' => notamedia\sentry\SentryTarget::class,
                    'dsn' => getenv('SENTRY_DSN'),
                ],
            ],
        ],
    ],
];
