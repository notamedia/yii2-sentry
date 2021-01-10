<?php

namespace notamedia\sentry;

use Yii;
use Throwable;
use yii\web\View;
use Sentry\SentrySdk;
use yii\helpers\Json;
use yii\web\Application;
use Sentry\ClientBuilder;
use Sentry\Integration\IntegrationInterface;
use notamedia\sentry\assets\TracingAsset;
use Sentry\Integration\ErrorListenerIntegration;
use Sentry\Integration\ExceptionListenerIntegration;
use Sentry\Integration\FatalErrorListenerIntegration;

/**
 * Class SentryComponent
 * @package notamedia\sentry
 */
class Component extends yii\base\Component
{
    /**
     * @var string Sentry client key.
     */
    public $dsn;
    /**
     * @var array Options of the \Sentry.
     */
    public $clientOptions = [];
    /**
     * collect JavaScript errors
     * @var bool
     */
    public $jsNotifier = false;
    /**
     * @var string Sentry javascript client key.
     */
    public $jsDsn;
    /**
     * Sentry browser configuration array
     * @var array
     * @see https://docs.sentry.io/platforms/javascript/configuration/
     */
    public $jsClientOptions = [];
    /**
     * @var bool Write the context information. The default implementation will dump user information, system variables, etc.
     */
    public $context = true;
    /**
     * @var string Environment application
     */
    public $environment = YII_ENV;

    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->jsInit();
        $this->phpInit();
    }

    /**
     * Sentry::init for javascript errors
     */
    private function jsInit(): void
    {
        if ($this->jsNotifier === false) {
            return;
        }

        if (!Yii::$app instanceof Application) {
            return;
        }

        try {
            $options = $this->getJsClienOptions();
            $this->registerAssets($options);
        } catch (Throwable $e) {
            // initialize Sentry component even if unable to register the assets
            Yii::error($e->getMessage(), __METHOD__);
        }
    }

    /**
     * Sentry::init for php errors
     */
    private function phpInit(): void
    {
        if (empty($this->dsn)) {
            return;
        }

        $options = $this->getClienOptions();

        $builder = ClientBuilder::create($options);
        $builder->getOptions()->setIntegrations(static function (array $integrations) {
            // Remove the default error and fatal exception listeners to let us handle those
            return array_filter($integrations, static function (IntegrationInterface $integration): bool {
                if ($integration instanceof ErrorListenerIntegration) {
                    return false;
                }
                if ($integration instanceof ExceptionListenerIntegration) {
                    return false;
                }
                if ($integration instanceof FatalErrorListenerIntegration) {
                    return false;
                }

                return true;
            });
        });

        SentrySdk::init()->bindClient($builder->getClient());
    }

    /**
     * @param array $options
     */
    protected function registerAssets($options)
    {
        $view = Yii::$app->getView();
        TracingAsset::register($view);
        $view->registerJs('Sentry.init(' . Json::encode($options) . ');', View::POS_HEAD);
    }

    /**
     * @return array
     */
    protected function getJsClienOptions()
    {
        $options = $this->jsClientOptions;
        $options['dsn'] = $this->jsDsn ?: $this->dsn;
        $options['environment'] = $this->environment;

        return $options;
    }

    /**
     * @return array
     */
    protected function getClienOptions()
    {
        $options = $this->clientOptions;
        $options['dsn'] = $this->dsn;
        $options['environment'] = $this->environment;

        return $options;
    }
}