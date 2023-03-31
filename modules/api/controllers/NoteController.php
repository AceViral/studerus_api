<?php

namespace app\modules\api\controllers;

use Yii;
use yii\rest\ActiveController;
use app\models\User;
use yii\filters\auth\HttpBearerAuth;
use app\models\Note;

class NoteController extends ActiveController
{
    public $modelClass = Note::class;

    public function beforeAction($action)
    {
        $accessToken = Yii::$app->request->headers->get('Authorization');
        $accessToken = substr($accessToken, 7);

        if (!$accessToken) {
            throw new \yii\web\UnauthorizedHttpException('Отсутствует токен авторизации.');
        }

        $user = User::findIdentityByAccessToken($accessToken);

        if (!$user) {
            throw new \yii\web\UnauthorizedHttpException($accessToken);
        }

        return parent::beforeAction($action);
    }

    public function actionGetNote()
    {
        $accessToken = Yii::$app->request->headers->get('Authorization');
        $accessToken = substr($accessToken, 7);
        $user = User::findIdentityByAccessToken($accessToken);

        if ($user) {
            $notes = Note::find()->where(['user_id' => $user->id])->all();

            return [
                'ok' => 1,
                'status' => 200,
                'notes' => $notes,
            ];
        } else {
            throw new \yii\web\UnauthorizedHttpException();
        }
    }

    public function actionCreateNote()
    {
        $in = \Yii::$app->request->post();

        $note = new Note();

        $accessToken = Yii::$app->request->headers->get('Authorization');
        $accessToken = substr($accessToken, 7);
        $user = User::findIdentityByAccessToken($accessToken);

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

        $accessToken = Yii::$app->request->headers->get('Authorization');
        $accessToken = substr($accessToken, 7);
        $user = User::findIdentityByAccessToken($accessToken);

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

        $accessToken = Yii::$app->request->headers->get('Authorization');
        $accessToken = substr($accessToken, 7);
        $user = User::findIdentityByAccessToken($accessToken);

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
