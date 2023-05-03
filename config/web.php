<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
Yii::setAlias('app\models\User', 'app\modules\user\User');

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'contentSecurityPolicy' => [
            'class' => \yii\filters\ContentSecurityPolicy::class,
            'policy' => [
                'default-src' => ["'self'"],
                'script-src' => ["'self'"],
                'style-src' => ["'self'"],
                'img-src' => ["'self'"],
                'font-src' => ["'self'"],
                'connect-src' => ["'self'"],
                'media-src' => ["'self'"],
                'object-src' => ["'self'"],
                'child-src' => ["'self'"],
                'frame-ancestors' => ["'none'"],
            ],
        ],
        // Безопасное управления сеансом А1
        'session' => [
            'class' => 'yii\web\Session',
            'cookieParams' => [
                'httponly' => true,
                'secure' => true,
                // Файлы coockie на одном сайте C1
                'samesite' => yii\web\Cookie::SAME_SITE_STRICT,
                'lifetime' => 3600, // Время жизни сессии в секундах
            ],
        ],
        'request' => [
            'class' => 'yii\web\Request',
            // Защита от CSRF А2 А4
            'enableCsrfValidation' => true,
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => $_ENV['COOKIE_VALIDATION_KEY'],
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\modules\api\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                '' => 'site/index',
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => [
                        'api/auth' => 'api/auth'
                    ],
                    'extraPatterns' => [
                        'OPTIONS register' => 'register',
                        'POST register' => 'register',

                        'OPTIONS login' => 'login',
                        'POST login' => 'login',

                        'OPTIONS refresh' => 'refresh',
                        'POST refresh' => 'refresh',
                    ],
                    'pluralize' => false,
                ],
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => [
                        'api/note' => 'api/note'
                    ],
                    'extraPatterns' => [
                        'OPTIONS get-note' => 'get-note',
                        'GET get-note' => 'get-note',

                        'OPTIONS create-note' => 'create-note',
                        'POST create-note' => 'create-note',

                        'OPTIONS update-note' => 'update-note',
                        'PUT update-note' => 'update-note',

                        'OPTIONS delete-note' => 'delete-note',
                        'DELETE delete-note' => 'delete-note',
                    ],
                    'tokens' => [
                        '{id}' => '<id:\d+>',
                    ],
                    'pluralize' => false,
                ]
            ],
        ],

    ],
    'modules' => [
        'api' => [
            'class' => 'app\modules\api\Module',
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
