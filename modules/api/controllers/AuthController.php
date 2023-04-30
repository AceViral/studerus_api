<?php

namespace app\modules\api\controllers;

use Yii;
use app\modules\api\models\User;
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
          $behaviors['corsFilter'] = [
               'class' => \yii\filters\Cors::class,
               'cors' => [
                    'Origin' => ['http://localhost:3000'],
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'],
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Allow-Headers' => ['Content-Type', 'Authorization'],
                    'Access-Control-Max-Age' => 3600,
                    'Access-Control-Expose-Headers' => ['Content-Type', 'Authorization'],
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

          $user = User::find()
               ->where(['email' => $email])
               ->orWhere(['username' => $username])
               ->one();
          if ($user) {
               throw new ForbiddenHttpException('User has already been taken');
          }

          if ($email && $password && $username) {

               $user = new User();

               $user->email = $email;
               $user->password = $password;
               $user->username = $username;
               $user->created_at = time();
               $user->updated_at = time();

               // $userSaved = $user->save();
               $user->save();
               $jwt = $user->generateJWTtoken(
                    [
                         'user_id' => $user->id,
                         'username' => $user->username,
                         'email' => $user->email,
                    ]
               );
               Yii::debug('$jwt', $jwt);
               $refresh_token = $user->generateRefreshToken(
                    [
                         'user_id' => $user->id,
                    ]
               );
               Yii::debug('$refresh_token', $refresh_token);
               $user->refresh_token = $refresh_token;
               $userSaved = $user->save();

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

               if (Yii::$app->security->validatePassword($password, $user->password_hash)) {
                    $user->updateFailedLoginAttempts(true);

                    $userSaved = $user->save();

                    $jwt = $user->generateJWTtoken(
                         [
                              'user_id' => $user->id,
                              'username' => $user->username,
                              'email' => $user->email,
                         ]
                    );

                    $refresh_token = $user->generateRefreshToken(
                         [
                              'user_id' => $user->id,
                         ]
                    );
                    $user->refresh_token = $refresh_token;

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
                    $user->updateFailedLoginAttempts(false);
               }
          } else {
               throw new NotFoundHttpException('User not found');
          }

          return $out;
     }

     public function actionRefresh()
     {
          $requestMethod = Yii::$app->request->getMethod();
          Yii::info('requestMethod', $requestMethod);
          if ($requestMethod !== 'OPTIONS') {
               $refresh_token = Yii::$app->request->headers->get('Authorization');
               Yii::info('refresh_token', $refresh_token);
               $refresh_token = substr($refresh_token, 7);

               $decoded = User::getUserDataFromJWT($refresh_token);

               $user = User::find()
                    ->where(['id' => $decoded->data->user_id])
                    ->one();

               if ($user) {
                    if (Yii::$app->security->validatePassword($refresh_token, $user->refresh_token_hash)) {

                         $refresh_token = $user->generateRefreshToken(
                              [
                                   'user_id' => $user->id,
                              ]
                         );
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
                              $out['access_token'] = $jwt;
                              $out['refresh_token'] = $refresh_token;
                         }
                    } else {
                         $user->updateFailedLoginAttempts(false);
                    }
               } else {
                    throw new NotFoundHttpException('User not found');
               }

               return $out;
          }
     }
}
