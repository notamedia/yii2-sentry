<?php
/**
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace notamedia\sentry;

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
     * @var string Client key.
     */
    public $dsn;
    /**
     * @var array Options of the \Raven_Client.
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
     * @var \Raven_Client
     */
    protected $client;

    /**
     * {@inheritdoc}
     */
    public function collect($messages, $final)
    {
        if (!isset($this->client)) {
            $this->client = new \Raven_Client($this->dsn, $this->clientOptions);
        }

        parent::collect($messages, $final);
    }

    /**
     * {@inheritdoc}
     */
    protected function getContextMessage()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function export()
    {
        foreach ($this->messages as $message) {
            list($context, $level, $category, $timestamp, $traces) = $message;
            $tags = $extra = [];

            if ($context instanceof \Throwable || $context instanceof \Exception) {
                $this->client->captureException($context);
                $description = $context->getMessage();
            } elseif (isset($context['msg'])) {
                $description = $context['msg'];
                if (isset($context['tags'])) {
                    $tags = $context['tags'];
                    unset($context['tags']);
                }
                $extra = $context;
                unset($extra['msg']);
            } else {
                $description = $context;
            }

            if ($this->context) {
                $extra['context'] = parent::getContextMessage();
            }

            if (is_callable($this->extraCallback)) {
                $extra = call_user_func($this->extraCallback, $context, $extra);
            }

            $data = [
                'level' => static::getLevelName($level),
                'timestamp' => $timestamp,
                'message' => $description,
                'extra' => $extra,
                'tags' => array_merge($tags, ['category' => $category])
            ];

            $this->client->capture($data, $traces);
        }
    }

    /**
     * Returns the text display of the specified level for the Sentry.
     *
     * @param integer $level The message level, e.g. [[LEVEL_ERROR]], [[LEVEL_WARNING]].
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

        return isset($levels[$level]) ? $levels[$level] : 'error';
    }
}
