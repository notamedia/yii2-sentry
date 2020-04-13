<?php
/**
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace notamedia\sentry;

use Sentry\Severity;
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
            [$text, $level, $category] = $message;

            $data = [
                'message' => '',
                'tags' => ['category' => $category],
                'extra' => [],
                'userData' => [],
            ];

            $request = Yii::$app->getRequest();
            if ($request instanceof Request) {
                $data['userData']['ip_address'] = $request->getUserIP();
            }

            try {
                /** @var User $user */
                $user = Yii::$app->has('user', true) ? Yii::$app->get('user', false) : null;
                if ($user && ($identity = $user->getIdentity(false))) {
                    $data['userData']['id'] = $identity->getId();
                }
            } catch (Throwable $e) {}

            \Sentry\withScope(function (Scope $scope) use ($text, $level, $data) {
                if (is_array($text)) {
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
                    $data['message'] = (string) $text;
                }

                if ($this->context) {
                    $data['extra']['context'] = parent::getContextMessage();
                }

                $data['extra'] = $this->runExtraCallback($text, $data['extra']);

                $scope->setUser($data['userData'], true);
                foreach ($data['extra'] as $key => $value) {
                    $scope->setExtra((string) $key, $value);
                }
                foreach ($data['tags'] as $key => $value) {
                    if ($value) {
                        $scope->setTag($key, $value);
                    }
                }

                if ($text instanceof Throwable) {
                    \Sentry\captureException($text);
                } else {
                    \Sentry\captureMessage($data['message'], $this->getLogLevel($level));
                }
            });
        }
    }

    /**
     * Calls the extra callback if it exists
     *
     * @param mixed $text
     * @param array $extra
     *
     * @return array
     */
    protected function runExtraCallback($text, array $extra): array
    {
        if (is_callable($this->extraCallback)) {
            $extra = call_user_func($this->extraCallback, $text, $extra);
        }

        return $extra;
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
