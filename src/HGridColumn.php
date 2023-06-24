<?php

namespace app\hgrid\src;

use app\hgrid\src\helpers\Obfuscator;
use Closure;
use Exception;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\grid\DataColumn;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class HGridColumn extends DataColumn
{
    const DEFAULT_INPUT_TYPE = 'text';
    /**
     * @var $relation array
     * holds the relation for the given column if it is a related record
     */
    public array $relation;

    /**
     * @var string|Closure|null an anonymous function or a string that is used to determine the value to display in the current column.
     */
    public $formInput = self::DEFAULT_INPUT_TYPE;

    /**
     * @var array $primaryKeys
     */
    private array $primaryKeys = [];

    /**
     * @var string $formName
     * Holds the formName of the current model
     */
    private string $formName;

    /**
     * Renders a data cell.
     * @param mixed $model the data model being rendered
     * @param mixed $key the key associated with the data model
     * @param int $index the zero-based index of the data item among the item array returned by [[GridView::dataProvider]].
     * @return string the rendering result
     * @throws InvalidConfigException
     */
    public function renderDataCell($model, $key, $index): string
    {
        /* @var $model ActiveRecord */

        $this->relation = $this->extractRelation($model, $this->attribute ?? $this->value, $key);

        if ($this->contentOptions instanceof Closure) {
            $options = call_user_func($this->contentOptions, $model, $key, $index, $this);
        } else {
            $options = $this->contentOptions;
        }

        $content = $this->renderDataCellContent($model, $key, $index);

        //Will not be queried again and again
        $t = $this->relation['model']::getTableSchema()->getColumn($this->relation['attribute'])->type;

        if ($this->relation['attribute'] !== null &&
            !in_array($this->relation['attribute'], $this->relation['primaryKeys']) /*disable primary key update*/) {
            $span = Html::tag('span', $content, [
                'class' => 'h-cell-data',
                'data' => [
                    'model-content' => $this->relation['model']->formName() . '[' . $this->relation['uniqueId'] . '][' . $this->relation['attribute'] . ']'
                ]
            ]);

            $formInput = $this->createFormInput($model, $key, $index);

            return Html::tag('td', $span . $formInput, $options);
        }
        return Html::tag('td', $content, $options);
    }

    protected function extractRelation($model, $attribute, $key): ?array
    {
        /* @var ActiveRecord $model */
        try {
            $explode = explode('.', $attribute);
            $attribute = array_pop($explode);
            if (empty($attribute)) {
                throw new Exception('Attribute not found.');
            }
            $relation = implode('.', $explode);

            $relationalModelData = ArrayHelper::getValue($model, $relation);

            /* @var ActiveRecord $relationalModelData */
            if (empty($relationalModelData)) {
                throw new Exception('Relational model not found.');
            }
            return [
                'model' => $relationalModelData,
                'attribute' => $attribute,
                'primaryKeys' => $relationalModelData::primaryKey(),
                'uniqueId' => $this->getPrimaryKeys($relationalModelData)[0]
            ];
        } catch (Exception $e) {
            return [
                'model' => $model,
                'attribute' => $attribute,
                'primaryKeys' => $model::primaryKey(),
                'uniqueId' => $key
            ];
        }
    }

    /**
     * @param $model
     * @return array
     * Extracts the primary keys of the model
     */
    protected function getPrimaryKeys($model)
    {
        $keys = [];
        $pks = $model::primaryKey();
        if (count($pks) === 1) {
            $pk = $pks[0];
            $keys[] = $model[$pk];
        } else {
            $kk = [];
            foreach ($pks as $pk) {
                $kk[$pk] = $model[$pk];
            }
            $keys[] = $kk;
        }
        return $keys;
    }

    protected function createFormInput($model, $key, $index)
    {
        $cellValue = $this->getDataCellValue($model, $key, $index);
        $inputOptions = [
            'style' => 'display:none;',
            'class' => 'h-cell-data-input',
            'disabled' => 'disabled',
            'autofocus' => true,
            'tab-index' => 0,
            'name' => $this->relation['model']->formName() . '[' . $this->relation['uniqueId'] . '][' . $this->relation['attribute'] . ']',
            'data' => [
                'attribute' => $this->relation['attribute'],
                'model' => 'Models[' . $this->relation['model']->formName() . '][' . $this->relation['uniqueId'] . ']',
                'classToken' => Yii::$app->getSecurity()->maskToken(get_class($this->relation['model'])),
            ]
        ];

        $formInput = strtolower($this->formInput);
        if (!in_array(strtolower($formInput), ['textarea'], true)) {
            $input = Html::input(
                $formInput,
                $this->relation['model']->formName() . '[' . $this->relation['uniqueId'] . '][' . $this->relation['attribute'] . ']',
                $cellValue,
                $inputOptions
            );
        } else {
            $input = Html::beginTag($formInput, $inputOptions);
            $input .= $cellValue;
            $input .= Html::endTag($formInput);
        }
        return $input;
    }
}