<?php
/**
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace notamedia\sentry;

use Sentry\Breadcrumb;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Frame;
use Sentry\Integration\ErrorListenerIntegration;
use Sentry\Integration\ExceptionListenerIntegration;
use Sentry\Integration\FatalErrorListenerIntegration;
use Sentry\Integration\IntegrationInterface;
use Sentry\SentrySdk;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\Severity;
use Sentry\StacktraceBuilder;
use Sentry\State\Scope;
use Throwable;
use Yii;
use yii\helpers\ArrayHelper;
use yii\log\Logger;
use yii\log\Target;
use yii\web\Request;
use yii\web\User;

/**
 * SentryTarget records log messages in a Sentry.
 *
 * @see https://sentry.io
 */
class SentryTarget extends Target
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
     * @var bool Write the context information. The default implementation will dump user information, system variables, etc.
     */
    public $context = true;
    /**
     * @var callable Callback function that can modify extra's array
     */
    public $extraCallback;

    /**
     * @var StacktraceBuilder Builder fo creation stack trace frames
     *
     * It available in \Sentry\Client, but it`s private.
     */
    protected $stacktraceBuilder;

    /**
     * @inheritDoc
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

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

        $this->stacktraceBuilder = new StacktraceBuilder($options, new RepresentationSerializer($options));
    }

    /**
     * @inheritdoc
     */
    protected function getContextMessage()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function collect($messages, $final)
    {
        foreach ($messages as $message) {
            list($text, $level, $category, $timestamp, $traces) = $message;

            [$message, $tags, $exception, $type, $extra] = $this->parseText($text);

            $metadata = [];
            if(!empty($tags))
                $metadata['tags'] = $tags;

            if(!empty($exception))
                $metadata['exception'] = $exception;

            if(!empty($extra))
                $metadata['extra'] = $extra;

            // $timestamp parameter will be added soon
            // @see https://github.com/getsentry/sentry-php/pull/1193
            $bc = new Breadcrumb($this->getLogLevel($level), $type, $category, $message, $metadata, $timestamp);
            \Sentry\addBreadcrumb($bc);
        }
        parent::collect($messages, $final);
    }

    /**
     * @inheritdoc
     */
    public function export()
    {
        foreach ($this->messages as $message) {
            [$text, $level, $category, $timestamp, $traces] = $message;

            $data = [
                'message' => '',
                'tags' => ['category' => $category],
                'extra' => [],
                'userData' => [],
            ];

            $request = Yii::$app->getRequest();
            if ($request instanceof Request && $request->getUserIP()) {
                $data['userData']['ip_address'] = $request->getUserIP();
            }

            try {
                /** @var User $user */
                $user = Yii::$app->has('user', true) ? Yii::$app->get('user', false) : null;
                if ($user && ($identity = $user->getIdentity(false))) {
                    $data['userData']['id'] = $identity->getId();
                }
            } catch (Throwable $e) {}

            \Sentry\withScope(function (Scope $scope) use ($text, $level, $timestamp, $traces, $data) {
                [$data['message'], $tags, $data['exception'], $breadcrumbType, $data['extra']] = $this->parseText($text);

                $data['tags'] = ArrayHelper::merge($data['tags'], $tags);

                if ($this->context) {
                    $data['extra']['context'] = parent::getContextMessage();
                }

                $data = $this->runExtraCallback($text, $data);

                $scope->setUser($data['userData']);

                foreach ($data['tags'] as $key => $value) {
                    if ($value) {
                        $scope->setTag($key, $value);
                    }
                }

                if ($text instanceof Throwable) {
                    \Sentry\captureException($text);
                } else {
                    $event = Event::createEvent();
                    $event->setMessage($data['message']);
                    $event->setLevel($this->getLogLevel($level));
                    $event->setTimestamp($timestamp);

                    \Sentry\captureEvent($event, EventHint::fromArray(array_filter([
                        'exception' => $data['exception'] ?? null,
                        'extra' => $data['extra'] ?? [],
                        'stacktrace' => $this->buildStacktrace($traces),
                    ])));
                }
            });
        }
    }

    /**
     * Parse the text variable
     * @param $text
     * @return array
     */
    protected function parseText($text)
    {
        $message = '';
        $tags = [];
        $exception = null;
        $extra = [];
        $type = Breadcrumb::TYPE_DEFAULT;
        if (is_array($text)) {
            if (isset($text['msg'])) {
                $message = (string)$text['msg'];
                unset($text['msg']);
            }

            if (isset($text['message'])) {
                $message = (string)$text['message'];
                unset($text['message']);
            }

            if (isset($text['tags'])) {
                $tags = (array)$text['tags'];
                unset($text['tags']);
            }

            if (isset($text['type'])) {
                $type = $text['type'];
                unset($text['type']);
            }

            if (isset($text['exception']) && $text['exception'] instanceof Throwable) {
                $exception = $text['exception'];
                unset($text['exception']);
            }
            $extra = $text;
        } else {
            $message = (string) $text;
        }
        return [$message, $tags, $exception, $type, $extra];
    }

    /**
     * Builds \Sentry\Stacktrace from traces array
     * @param $traces Yii message traces
     * @return null|\Sentry\Stacktrace
     */
    protected function buildStacktrace($traces)
    {
        if(is_array($traces) && ! empty($traces)) {
            return $this->stacktraceBuilder->buildFromBacktrace($traces, Frame::INTERNAL_FRAME_FILENAME , 0);
        }
        return null;
    }

    /**
     * Calls the extra callback if it exists
     *
     * @param mixed $text
     * @param array $data
     *
     * @return array
     */
    public function runExtraCallback($text, $data)
    {
        if (is_callable($this->extraCallback)) {
            $data['extra'] = call_user_func($this->extraCallback, $text, $data['extra'] ?? []);
        }

        return $data;
    }

    /**
     * Returns the text display of the specified level for the Sentry.
     *
     * @deprecated Deprecated from 1.5, will remove in 2.0
     *
     * @param int $level The message level, e.g. [[LEVEL_ERROR]], [[LEVEL_WARNING]].
     *
     * @return string
     */
    public static function getLevelName($level)
    {
        static $levels = [
            Logger::LEVEL_ERROR => 'error',
            Logger::LEVEL_WARNING => 'warning',
            Logger::LEVEL_INFO => 'info',
            Logger::LEVEL_TRACE => 'debug',
            Logger::LEVEL_PROFILE_BEGIN => 'debug',
            Logger::LEVEL_PROFILE_END => 'debug',
        ];

        return $levels[$level] ?? 'error';
    }

    /**
     * Translates Yii2 log levels to Sentry Severity.
     *
     * @param int $level
     *
     * @return Severity
     */
    protected function getLogLevel($level): Severity
    {
        switch ($level) {
            case Logger::LEVEL_PROFILE:
            case Logger::LEVEL_PROFILE_BEGIN:
            case Logger::LEVEL_PROFILE_END:
            case Logger::LEVEL_TRACE:
                return Severity::debug();
            case Logger::LEVEL_WARNING:
                return Severity::warning();
            case Logger::LEVEL_ERROR:
                return Severity::error();
            case Logger::LEVEL_INFO:
            default:
                return Severity::info();
        }
    }
}
