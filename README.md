# Sentry logger for Yii2

[![Build Status](https://travis-ci.org/notamedia/yii2-sentry.svg)](https://travis-ci.org/notamedia/yii2-sentry)
[![Latest Stable Version](https://poser.pugx.org/notamedia/yii2-sentry/v/stable)](https://packagist.org/packages/notamedia/yii2-sentry) 
[![Total Downloads](https://poser.pugx.org/notamedia/yii2-sentry/downloads)](https://packagist.org/packages/notamedia/yii2-sentry) 
[![License](https://poser.pugx.org/notamedia/yii2-sentry/license)](https://packagist.org/packages/notamedia/yii2-sentry)

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
				    'context' => true // Write the context information. The default is true.
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

Writing messages with extra data:

```php
\Yii::warning([
    'msg' => message',
    'extra' => 'value'
], 'category');
```

## Log levels

Yii2 log levels converts to Sentry levels:

```
\yii\log\Logger::LEVEL_ERROR => 'error',
\yii\log\Logger::LEVEL_WARNING => 'warning',
\yii\log\Logger::LEVEL_INFO => 'info',
\yii\log\Logger::LEVEL_TRACE => 'debug',
\yii\log\Logger::LEVEL_PROFILE_BEGIN => 'debug',
\yii\log\Logger::LEVEL_PROFILE_END => 'debug',
```