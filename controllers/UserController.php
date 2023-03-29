<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\filters\auth\HttpBearerAuth;
use app\models\User;

class UserController extends ActiveController
{
     public $modelClass = User::class;

     public function behaviors()
     {
          $behaviors = parent::behaviors();
          $behaviors['authenticator'] = [
               'class' => HttpBearerAuth::class,
               'except' => ['create']
          ];

          return $behaviors;
     }

     public function actions()
     {
          $actions = parent::actions();
          unset($actions['index'], $actions['update'], $actions['delete']);

          return $actions;
     }
     public function actionSignup()
     {
          $model = new User();
          $params = Yii::$app->request->post();
          if(!$params) {
               Yii::$app->response->statusCode = Status::STATUS_BAD_REQUEST;
               return [
                    'status' => Status::STATUS_BAD_REQUEST,
                    'message' => "Need username, password, and email.",
                    'data' => ''
               ];
          }


          $model->username = $params['username'];
          $model->email = $params['email'];

          $model->setPassword($params['password']);
          $model->generateAuthKey();
          $model->status = User::STATUS_ACTIVE;

          if ($model->save()) {
               Yii::$app->response->statusCode = Status::STATUS_CREATED;
               $response['isSuccess'] = 201;
               $response['message'] = 'You are now a member!';
               $response['user'] = \app\models\User::findByUsername($model->username);
               return [
                    'status' => Status::STATUS_CREATED,
                    'message' => 'You are now a member',
                    'data' => User::findByUsername($model->username),
               ];
          } else {
               Yii::$app->response->statusCode = Status::STATUS_BAD_REQUEST;
               $model->getErrors();
               $response['hasErrors'] = $model->hasErrors();
               $response['errors'] = $model->getErrors();
               return [
                    'status' => Status::STATUS_BAD_REQUEST,
                    'message' => 'Error saving data!',
                    'data' => [
                         'hasErrors' => $model->hasErrors(),
                         'getErrors' => $model->getErrors(),
                    ]
               ];
          }
     }
     // public function checkAccess($action, $model = null, $params = [])
     // {
     //      if (Yii::$app->user->id !== $model->id) {
     //           throw new \yii\web\ForbiddenHttpException('You do not have permission to access this user data.');
     //      }
     // }

     public function actionCreate()
     {
          $model = new User();
          $params = Yii::$app->request->post();

          $model->username = $params['username'];
          $model->email = $params['email'];
          $model->setPassword($params['password']);

          $model->password_hash = Yii::$app->getSecurity()->generatePasswordHash($params['password']);
          $model->generateAuthKey();
          $model->access_token = Yii::$app->getSecurity()->generateRandomString();
          $model->created_at = time();
          $model->updated_at = time();

          Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;


          if ($model->save()) {
               return ['message' => 'User created successfully', 'access_token' => $model->access_token];
          } else {
               return ['errors' => $model->getErrors()];
          }


          return $model->getErrors();
     }

}
