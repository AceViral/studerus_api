<?php

namespace app\modules\api\controllers;

use Yii;
use yii\rest\ActiveController;
use app\modules\api\models\User;
use app\modules\api\models\Note;

class NoteController extends ActiveController
{
    public $modelClass = Note::class;
    public function behaviors()
    {
        $behaviors = parent::behaviors();

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
        return $behaviors;
    }
    public function beforeAction($action)
    {
        $requestMethod = Yii::$app->request->getMethod();
        if ($requestMethod !== 'OPTIONS') {

            $jwt = Yii::$app->request->headers->get('Authorization');
            $jwt = substr($jwt, 7);

            if (!$jwt) {
                throw new \yii\web\UnauthorizedHttpException('Token not provided.');
            }

            $decoded = User::getUserDataFromJWT($jwt);
            $user = User::find()
                ->where(['id' => $decoded->data->user_id])
                ->one();

            if ($user) {
                Yii::$app->user->identity = $user;
            } else {
                throw new \yii\web\UnauthorizedHttpException('User not found.');
            }
        }
        return parent::beforeAction($action);
    }

    public function actionGetNote()
    {
        $user = Yii::$app->user->identity;

        if ($user) {
            $notes = Note::find()->where(['user_id' => $user->id])->all();

            return $notes;
        } else {
            throw new \yii\web\UnauthorizedHttpException();
        }
    }

    public function actionCreateNote()
    {
        $in = \Yii::$app->request->post();

        $note = new Note();

        $user = Yii::$app->user->identity;

        if ($user) {
            $note->user_id = $user->id;
            $note->title = $in['title'];
            $note->content = $in['content'];
            $note->created_at = time();
            $note->updated_at = time();

            if ($note->save()) {
                return $note;
            } else {
                return $note->errors;
            }
        } else {
            throw new \yii\web\UnauthorizedHttpException();
        }
    }

    public function actionUpdateNote()
    {
        $id = Yii::$app->request->get('id');

        $note = Note::findOne($id);

        if (!$note) {
            throw new \yii\web\NotFoundHttpException('Записка не найдена.');
        }

        $user = Yii::$app->user->identity;

        if ($user && $note->user_id == $user->id) {
            $note->load(Yii::$app->request->post(), '');

            if ($note->save()) {
                $note->updated_at = time();
                return $note;
            } else {
                return $note->errors;
            }
        } else {
            throw new \yii\web\UnauthorizedHttpException();
        }
    }

    public function actionDeleteNote()
    {
        $id = Yii::$app->request->get('id');


        $model = Note::findOne($id);

        if (!$model) {
            throw new \yii\web\NotFoundHttpException('Записка не найдена.');
        }

        $user = Yii::$app->user->identity;

        if ($user && $model->user_id == $user->id) {
            if ($model->delete()) {
                Yii::$app->response->setStatusCode(204);
            } else {
                throw new \yii\web\ServerErrorHttpException('Не удалось удалить записку.');
            }
        } else {
            throw new \yii\web\UnauthorizedHttpException();
        }
    }
}
