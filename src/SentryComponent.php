<?php

namespace notamedia\sentry;

use Yii;
use Throwable;
use yii\web\View;
use Sentry\SentrySdk;
use yii\helpers\Json;
use yii\base\Component;
use Sentry\ClientBuilder;
use Sentry\Integration\IntegrationInterface;
use notamedia\sentry\assets\SentryTracingAsset;
use Sentry\Integration\ErrorListenerIntegration;
use Sentry\Integration\ExceptionListenerIntegration;
use Sentry\Integration\FatalErrorListenerIntegration;

/**
 * Class SentryComponent
 * @package notamedia\sentry
 */
class SentryComponent extends Component
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

        if (empty($this->jsDsn)) {
            $this->jsDsn = $this->dsn;
        }

        try {
            $view = Yii::$app->getView();
            SentryTracingAsset::register($view);
            $jsOptions = array_merge(['dsn' => $this->jsDsn], $this->jsClientOptions);
            $view->registerJs('Sentry.init(' . Json::encode($jsOptions) . ');', View::POS_HEAD);
        } catch (Throwable $e) {
            // initialize Sentry component even if unable to register the assets
            Yii::error($e->getMessage());
        }
    }

    /**
     * Sentry::init for php errors
     */
    private function phpInit(): void
    {
        $userOptions = array_merge(['dsn' => $this->dsn], $this->clientOptions);
        $builder = ClientBuilder::create($userOptions);

        $options = $builder->getOptions();
        $options->setIntegrations(static function (array $integrations) {
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
}