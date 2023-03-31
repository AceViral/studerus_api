<?php

namespace app\models;

use Yii;
use yii\web\ForbiddenHttpException;

/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password_hash
 * @property string $access_token
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Note[] $notes
 */
class User extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user';
    }
    /**
     * {@inheritdoc}
     */

    public $password;

    public function rules()
    {
        return [
            [['username', 'email'], 'required'],
            [['created_at', 'updated_at', 'login_locked_until', 'failed_login_attempts'], 'default', 'value' => null],
            [['created_at', 'updated_at', 'login_locked_until', 'failed_login_attempts'], 'integer'],
            [['username'], 'string', 'max' => 32],
            [['email', 'password_hash', 'access_token'], 'string', 'max' => 255],
            [['access_token'], 'unique'],
            [['email'], 'unique'],
            [['username'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'username' => 'Username',
            'email' => 'Email',
            'password_hash' => 'Password Hash',
            'access_token' => 'Access Token',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public function beforeSave($insert)
    {
        // Хэширование паролей А3
        if ($this->password) {
            $this->password_hash = Yii::$app->security->generatePasswordHash($this->password);
        }
        return parent::beforeSave($insert);
    }

    public static function findIdentityByAccessToken($accessToken, $type = null)
    {
        return static::findOne(['access_token' => $accessToken]);
    }

    // Блокировка учетной записи B2
    public function isLoginBlocked()
    {
        if ($this->login_locked_until && $this->login_locked_until > time()) {
            return true;
        }

        return false;
    }

    public function updateFailedLoginAttempts($successfulLogin)
    {
        if ($successfulLogin) {
            $this->failed_login_attempts = 0;
            $this->login_locked_until = null;
        } else {
            $this->failed_login_attempts++;
            Yii::info($this->failed_login_attempts, '$this->failed_login_attempts');
            switch ($this->failed_login_attempts) {
                case 1:
                    $this->save();
                    throw new ForbiddenHttpException('You have 4 attempts left otherwise your account will be blocked for 30 minutes');
                    break;
                case 2:
                    $this->save();
                    throw new ForbiddenHttpException('You have 3 attempts left otherwise your account will be blocked for 30 minutes');
                    break;
                case 3:
                    $this->save();
                    throw new ForbiddenHttpException('You have 2 attempts left otherwise your account will be blocked for 30 minutes');
                    break;
                case 4:
                    $this->save();
                    throw new ForbiddenHttpException('You have 1 attempts left otherwise your account will be blocked for 30 minutes');
                    break;
                default:
                    // 60 * 180 Потому что в php time() относительно МСК отстает на 3 часа
                    $this->login_locked_until = time() + (60 * 180) + (60 * 30); // Заблокировать на 30 минут
                    $this->save();
                    throw new ForbiddenHttpException('Your account is blocked until ' . date("d.m.Y H:i:s", $this->login_locked_until));
            }
        }

        $this->save();
    }
}
