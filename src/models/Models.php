<?php

namespace app\hgrid\src\models;

use Exception;
use Yii;
use yii\db\ActiveRecord;

class Models implements ModelsInterface
{

    /**
     * @throws Exception
     */
    public static function load($postData): array
    {
        if (isset($postData['Models'])) {
            try {
                $modelDatas = $postData['Models'];
                $_models = [];
                foreach ($modelDatas as $modelName => $models) {
                    foreach ($models as $key => $model) {
                        //every model should have modelClass
                        if (empty($model['classToken'])) {
                            throw new Exception('classToken not found.');
                        }
                        if ($validateClassPath = self::validateModelToken($modelName, $model['classToken'])) {
                            /* @var ActiveRecord $validateClassPath */
                            $modelClass = $validateClassPath::findOne($key);
                            $modelClass->load($model, '');
                            unset($model['classToken']);
                            $_models[
                                $modelClass->formName() . '[' . $key . ']'
                            ] = [
                                'primaryModelClass' => $modelClass,
                                'modelClass' => $model
                            ];
                        }
                    }
                }
                unset($modelDatas);
                return $_models;
            } catch (Exception $e) {
                throw new Exception('Could not load data into model.');
            }
        }
        return [];
    }

    /**
     * Returns the unmasked class path
     * @param $modelClass
     * @param $masked
     * @return string|null
     */
    public static function validateModelToken($modelClass, $masked): ?string
    {
        if (self::getShortName($unmasked = Yii::$app->getSecurity()->unmaskToken($masked)) === $modelClass) {
            return $unmasked;
        }
        return null;
    }

    /**
     * Extracts the shortname of the model class
     * @param $modelClassPath
     * @return false|string|null
     */
    public static function getShortName($modelClassPath)
    {
        if (!empty($modelClassPath)) {
            $classPath = $modelClassPath;
            $lastBackslashIndex = strrpos($classPath, '\\');
            return substr($classPath, $lastBackslashIndex + 1);
        }
        return null;
    }
}