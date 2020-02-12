<?php
/**
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace notamedia\sentry;

use Sentry\Severity;
use Sentry\State\Scope;
use yii\helpers\ArrayHelper;
use yii\log\Logger;
use yii\log\Target;

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
     * @var \Sentry
     */
    protected $client;

    /**
     * @inheritdoc
     */
    public function collect($messages, $final)
    {
        \Sentry\init(array_merge(['dsn' => $this->dsn], $this->clientOptions));

        parent::collect($messages, $final);
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
    public function export()
    {
        foreach ($this->messages as $message) {
            list($text, $level, $category, $timestamp) = $message;
            $traces = $message[4] ?? [];

            $data = [
                'level' => static::getLevelName($level),
                'timestamp' => $timestamp, //but it not used by current client
                'tags' => [
                    'category' => $category,
                ],
                'extra' => [
                    'trace' => $traces,
                ]
            ];

            if ($text instanceof \Throwable ) {
                $data['exception'] = $text;
            } elseif (is_array($text)) {
                if (isset($text['msg'])) {
                    $data['message'] = $text['msg'];
                    unset($text['msg']);
                }

                if (isset($text['tags'])) {
                    $data['tags'] = ArrayHelper::merge($data['tags'], $text['tags']);
                    unset($text['tags']);
                }

                $data['extra'] = $text;
            } else {
                $data['message'] = $text;
            }

            if ($this->context){
                $context = ArrayHelper::filter($GLOBALS, $this->logVars);
                foreach ($this->maskVars as $var) {
                    if (ArrayHelper::getValue($context, $var) !== null) {
                        ArrayHelper::setValue($context, $var, '***');
                    }
                }
                $data['extra']['context'] = $context;
            }


            $data = $this->runExtraCallback($text, $data);

            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($data): void {
                foreach ($data['tags'] as $key => $value) {
                    $scope->setTag($key, $value);
                }
                foreach ($data['extra'] as $key => $value) {
                    $scope->setExtra((string)$key, $value);
                }
                if (isset($data['exception'])) {
                    \Sentry\captureException($data['exception']);
                } elseif (isset($data['message'])) {
                    \Sentry\captureMessage($data['message'], $data['level']);
                } else {
                    \Sentry\captureEvent($data);
                }
            });
        }
    }

    /**
     * Calls the extra callback if it exists
     *
     * @param $text
     * @param array $data
     * @return array
     */
    public function runExtraCallback($text, $data) : array
    {
        if (is_callable($this->extraCallback)) {
            $data['extra'] = call_user_func($this->extraCallback, $text, $data['extra'] ?? []);
        }

        return $data;
    }

    /**
     * Returns the text display of the specified level for the Sentry.
     *
     * @param integer $level The message level, e.g. [[LEVEL_ERROR]], [[LEVEL_WARNING]].
     * @return Severity
     */
    public static function getLevelName($level) : Severity
    {
        static $levels = [
            Logger::LEVEL_ERROR => Severity::ERROR,
            Logger::LEVEL_WARNING => Severity::WARNING,
            Logger::LEVEL_INFO => Severity::INFO,
            Logger::LEVEL_TRACE => Severity::DEBUG,
            Logger::LEVEL_PROFILE_BEGIN => Severity::DEBUG,
            Logger::LEVEL_PROFILE_END => Severity::DEBUG,
        ];

        $level = $levels[$level] ?? Severity::ERROR;
        return new Severity($level);
    }
}
