<?php

namespace app\modules\api\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\UnauthorizedHttpException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use app\models\User;

class TokenController extends Controller
{
    public function actionGenerateToken()
    {
        $params = Yii::$app->getRequest()->getBodyParams();

        $username = $params['username'];
        $password = $params['password'];

        $user = User::findOne(['username' => $username]);

        if (!$user || !Yii::$app->getSecurity()->validatePassword($password, $user->password_hash)) {
            throw new UnauthorizedHttpException('Incorrect username or password.');
        }

        $token = [
            'iss' => 'your-issuer-here',
            'aud' => 'your-audience-here',
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 3600,
            'data' => [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]
        ];

        $jwt = JWT::encode($token, 'your-secret-key-here', 'HS256');
        $user->access_token = $jwt;
        $user->save();

        return [
            'access_token' => $jwt,
            'token_type' => 'bearer',
            'expires_in' => 3600
        ];
    }

    public function actionValidateToken()
    {
        $jwt = Yii::$app->getRequest()->getHeaders()->get('Authorization');
        $jwt = substr($jwt, 7);

        if (!$jwt) {
            throw new UnauthorizedHttpException('Token not provided.');
        }

        try {
            $decoded = JWT::decode($jwt, new Key('your-secret-key-here', 'HS256'));
        } catch (\Exception $e) {
            throw new UnauthorizedHttpException('Invalid token.');
        }

        if ($decoded->nbf > time() || $decoded->exp < time()) {
            throw new UnauthorizedHttpException('Expired token.');
        }

        $user = User::findOne(['id' => $decoded->data->user_id]);

        if (!$user) {
            throw new UnauthorizedHttpException('Invalid token.');
        }

        // Далее можно проверить права пользователя для выполнения запрошенного действия

        return [
            'allow' => true,
            'user_data' => $decoded->data,
        ];
    }
}
