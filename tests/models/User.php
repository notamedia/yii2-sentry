<?php

namespace tests\models;

use yii\base\BaseObject;
use yii\web\IdentityInterface;

class User extends BaseObject implements IdentityInterface
{
    /**
     * @var int
     */
    public $id = 1;

    /**
     * @var string
     */
    public $username = 'JohnDoe';

    /**
     * @var string
     */
    public $email = 'john.doe@example.com';

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id)
    {
        return new self(['id' => $id]);
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        return new self();
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey()
    {
        return '123';
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey)
    {
        return true;
    }
}
