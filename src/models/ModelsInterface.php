<?php

namespace app\hgrid\src\models;

interface ModelsInterface
{
    /**
     * @param $modelClassPath
     * @return mixed
     */
    public static function getShortName($modelClassPath);

    /**
     * Validates the model token and returns class path if validated else will return null
     * @param $modelClass
     * @param $masked
     * @return string|null Unmasked class path if not validated return null
     */
    public static function validateModelToken($modelClass, $masked): ?string;

    /**
     * Loads data into corresponding models using model tokens
     * Using their primary keys
     * @param $postData
     * @return array
     */

    public static function load($postData): array;
}