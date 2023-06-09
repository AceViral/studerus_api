<?php

namespace app\modules\api\models;

use Yii;
use yii\web\ForbiddenHttpException;
use Firebase\JWT\Key;
use Firebase\JWT\JWT;
use yii\web\UnauthorizedHttpException;
use yii\web\NotFoundHttpException;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;


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
class User extends ActiveRecord implements IdentityInterface
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
    public $refresh_token;

    public function rules()
    {
        return [
            [['username', 'email'], 'required'],
            [['created_at', 'updated_at', 'login_locked_until', 'failed_login_attempts'], 'default', 'value' => null],
            [['created_at', 'updated_at', 'login_locked_until', 'failed_login_attempts'], 'integer'],
            [['username'], 'string', 'max' => 32],
            [['email', 'password_hash', 'refresh_token_hash'], 'string', 'max' => 500],
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
            'refresh_token_hash' => 'Refresh Token Hash',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public static function findIdentity($id)
    {
        return static::findOne($id);
    }

    public function getId()
    {
        return $this->id;
    }

    public static function findIdentityByAccessToken($accessToken, $type = null)
    {
        return static::findOne(['access_token' => $accessToken]);
    }

    public function getAuthKey()
    {
        throw new NotFoundHttpException();
    }

    public function validateAuthKey($authKey)
    {
        throw new NotFoundHttpException();
    }

    public function beforeSave($insert)
    {
        // Хэширование паролей А3
        if ($this->password) {
            $this->password_hash = Yii::$app->security->generatePasswordHash($this->password);
        }
        // Хэширование рефреш токена
        if ($this->refresh_token) {
            $this->refresh_token_hash = Yii::$app->security->generatePasswordHash($this->refresh_token);
        }

        return parent::beforeSave($insert);
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
                    $this->login_locked_until = time() + (60 * 30); // Заблокировать на 30 минут
                    $this->save();
                    throw new ForbiddenHttpException('Your account is blocked until ' . date("d.m.Y H:i:s", $this->login_locked_until));
            }
        }

        $this->save();
    }

    public function generateAccessToken($payload)
    {
        $token = [
            'iss' => $_ENV['ISSUER_FOR_TOKENS'],
            'aud' =>  $_ENV['AUDIENCE_FOR_TOKENS'],
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + $_ENV['EXP_TIME_FOR_ACCESS_TOKEN'],
            'data' => $payload
        ];

        return JWT::encode($token, $_ENV['ACCESS_TOKEN_KEY'], $_ENV['ALGORITHM_FOR_TOKENS']);
    }

    public static function getUserDataFromAccessToken($jwt)
    {
        try {
            $decoded = JWT::decode($jwt, new Key($_ENV['ACCESS_TOKEN_KEY'],  $_ENV['ALGORITHM_FOR_TOKENS']));
        } catch (\Exception $e) {
            throw new UnauthorizedHttpException('Invalid access token.');
        }

        return $decoded;
    }

    public function generateRefreshToken($payload)
    {
        $token = [
            'iss' =>  $_ENV['ISSUER_FOR_TOKENS'],
            'aud' =>  $_ENV['AUDIENCE_FOR_TOKENS'],
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + $_ENV['EXP_TIME_FOR_REFRESH_TOKEN'],
            'data' => $payload
        ];

        return JWT::encode($token, $_ENV['REFRESH_TOKEN_KEY'],  $_ENV['ALGORITHM_FOR_TOKENS']);
    }

    public static function getUserDataFromRefreshToken($jwt)
    {
        try {
            $decoded = JWT::decode($jwt, new Key($_ENV['REFRESH_TOKEN_KEY'],  $_ENV['ALGORITHM_FOR_TOKENS']));
        } catch (\Exception $e) {
            throw new UnauthorizedHttpException('Invalid refresh token.');
        }

        return $decoded;
    }
}
