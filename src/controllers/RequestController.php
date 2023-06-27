<?php

namespace app\hgrid\src\controllers;

use app\hgrid\src\models\Models;
use Exception;
use Yii;
use yii\db\ActiveRecord;
use yii\web\Controller;
use yii\web\Response;

class RequestController extends Controller
{
    public function actionData()
    {
        if (Yii::$app->request->isPost) {

            Yii::$app->getResponse()->format = Response::FORMAT_JSON;

            $models = Models::load(Yii::$app->request->post());

            $_responseData = [];

            foreach ($models as $key => $modelData) {
                /* @var ActiveRecord $modelData */
                try {
                    $attributesToUpdate = array_keys($modelData['modelClass']);
                    $rowsAffected = $modelData['primaryModelClass']->update();
                    $modelData['primaryModelClass']->refresh();
                    $_responseData[$key] = [
                        'status' => 200,
                        'rowsAffected' => $rowsAffected,
                        'message' => 'updated',
                        'attributes' => $modelData['primaryModelClass']->getAttributes($attributesToUpdate)
                    ];
                } catch (Exception $e) {
                    $_responseData[$key][] = [
                        'status' => 500,
                        'rowsAffected' => 0,
                        'attributes' => null,
                        'message' => 'failed'
                    ];
                }
            }
            return $_responseData;
        }
    }
}