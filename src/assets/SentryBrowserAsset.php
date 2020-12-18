<?php

namespace notamedia\sentry\assets;

use yii\web\View;
use yii\web\AssetBundle;

/**
 * Class SentryAsset
 * @package notamedia\sentry\assets
 */
class SentryBrowserAsset extends AssetBundle
{
    public $sourcePath = '@npm/sentry--browser';

    public $js = [
        'build/bundle.min.js',
    ];

    public $jsOptions = [
        'position' => View::POS_HEAD,
    ];
}