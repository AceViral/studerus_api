<?php

namespace app\modules\api\controllers;

use Yii;
use app\models\User;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\ServerErrorHttpException;
use yii\web\UnauthorizedHttpException;
use yii\web\NotFoundHttpException;

class AuthController extends Controller
{
     public function behaviors()
     {
          $behaviors = parent::behaviors();

          $behaviors['contentNegotiator'] = [
               'class' => \yii\filters\ContentNegotiator::class,
               'formats' => [
                    'application/json' => \yii\web\Response::FORMAT_JSON,
               ],
          ];
          // remove authentication filter
          $auth = $behaviors['authenticator'];
          unset($behaviors['authenticator']);

          // add CORS filter
          $behaviors['corsFilter'] = [
               'class' => \yii\filters\Cors::className(),
               'cors' => [
                    'Origin' => ['*'],
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'],
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Expose-Headers' => ['*'],
               ],
          ];

          // re-add authentication filter
          $behaviors['authenticator'] = $auth;
          // avoid authentication on CORS-pre-flight requests (HTTP OPTIONS method)
          $behaviors['authenticator']['except'] = ['options'];

          return $behaviors;
     }

     public function actionRegister()
     {
          $in = \Yii::$app->request->post();

          $email = $in['email'];
          $password = $in['password'];
          $username = $in['username'];


          if ($email && $password && $username) {

               $user = new User();

               $user->email = $email;
               $user->password = $password;
               $user->username = $username;
               $user->created_at = time();
               $user->updated_at = time();
               $refresh_token = $user->generateRefreshToken();
               $user->refresh_token = $refresh_token;

               $userSaved = $user->save();

               $jwt = $user->generateJWTtoken(
                    [
                         'user_id' => $user->id,
                         'username' => $user->username,
                         'email' => $user->email,
                    ]
               );



               if (!$userSaved) {
                    $out['err']['user not saved'] = [
                         $user->getErrors(),
                         $user->errors,
                         $user->getAttributes(),
                    ];
               } else {
                    $out['user'] = $user->getAttributes();
                    $out['access_token'] = $jwt;
                    $out['refresh_token'] = $refresh_token;
               }
          } else {
               throw new BadRequestHttpException('Not all data provided');
          }

          return $out;
     }

     public function actionLogin()
     {

          $in = \Yii::$app->request->post();

          $email = $in['email'];
          $password = $in['password'];
          $username = $in['username'];

          $user = User::find()
               ->where(['email' => $email])
               ->orWhere(['username' => $username])
               ->one();

          if ($user) {
               // Блокировка учетной записи B2
               if ($user->isLoginBlocked()) {
                    throw new ForbiddenHttpException('Your account is blocked until ' . date("d.m.Y H:i:s", $user->login_locked_until));
               }

               $userSessions = Yii::$app->session->get('userSessions', []);
               $sessionCount = count($userSessions);
               $maxSessions = 2; // максимальное количество сессий для пользователя

               if ($sessionCount >= $maxSessions) {
                    throw new ForbiddenHttpException('Maximum number of sessions reached');
               }

               $userSessions[] = session_id();
               Yii::$app->session->set('userSessions', $userSessions);

               if (Yii::$app->security->validatePassword($password, $user->password_hash)) {
                    $user->updateFailedLoginAttempts(true);

                    $jwt = $user->generateJWTtoken(
                         [
                              'user_id' => $user->id,
                              'username' => $user->username,
                              'email' => $user->email,
                         ]
                    );
                    $user->access_token = $jwt;
                    $user->save();

                    $out['user'] = $user->getAttributes();
               } else {
                    $user->updateFailedLoginAttempts(false);
               }
          } else {
               throw new NotFoundHttpException('User not found');
          }

          return $out;
     }

     public function actionCheckUser()
     {
          $jwt = Yii::$app->request->headers->get('Authorization');
          $jwt = substr($jwt, 7);

          if (!$jwt) {
               throw new UnauthorizedHttpException('Token not provided.');
          }

          $decoded = User::getUserDataFromJWT($jwt);

          return [
               'user_data' => $decoded,
          ];
     }

     public function actionLogout()
     {
          $accessToken = Yii::$app->request->headers->get('Authorization');
          $accessToken = substr($accessToken, 7);
          $user = User::findIdentityByAccessToken($accessToken);

          if ($user) {
               Yii::$app->session->destroy();
               return ['message' => 'Session destroyed'];
          } else {
               throw new UnauthorizedHttpException('Invalid access token');
          }
     }
}
