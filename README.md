# Sentry logger for Yii2

## Installation

```bash
composer require notamedia/yii2-sentry
```

Add target class in the application config:

```php
return [
    'components' => [
	    'log' => [
		    'traceLevel' => YII_DEBUG ? 3 : 0,
		    'targets' => [
			    [
				    'class' => 'notamedia\sentry\SentryTarget',
				    'dsn' => 'http://2682ybvhbs347:235vvgy465346@sentry.com/1,
				    'levels' => ['error', 'warning'],
			    ],
		    ],
	    ],
    ],
];
```

## Usages

Writing simple message:

```php
\Yii::error('message', 'category');
```

Writing messages with context:

```php
\Yii::error('message', 'category');
```

## How it works

