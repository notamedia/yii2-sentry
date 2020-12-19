<?php

namespace notamedia\sentry\assets;

use yii\web\View;
use yii\web\AssetBundle;

/**
 * Class SentryAsset
 * @package notamedia\sentry\assets
 */
class TracingAsset extends AssetBundle
{
    public $sourcePath = '@npm/sentry--tracing';

    public $js = [
        'build/bundle.tracing.min.js',
    ];

    public $jsOptions = [
        'position' => View::POS_HEAD,
    ];

    public $depends = [
        BrowserAsset::class,
    ];
}