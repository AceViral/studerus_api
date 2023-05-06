<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

class SiteController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['contentNegotiator'] = [
            'class' => \yii\filters\ContentNegotiator::class,
            'only' => ['index'],
            'formats' => [
                'application/json' => \yii\web\Response::FORMAT_JSON,
                'text/html' => \yii\web\Response::FORMAT_HTML,
            ]
        ];

        return $behaviors;
    }

    public function actionIndex()
    {
        return $this->render('index');
    }
}
