<?php

namespace app\hgrid\src;

use Closure;
use Exception;
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
     * @var $inputType string|null
     * holds the input area to be created
     */

    public ?string $inputType = self::DEFAULT_INPUT_TYPE;

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

        $this->relation = $this->extractRelation($model, $this->attribute, $key);

//        if (false !== strpos($this->attribute, '.') && $key !== 1) {
//            echo "<pre>";
//            var_dump($this->relation['attribute'], $this->relation['model']);
//            echo "</pre>";
//            die;
//        }
        if ($this->contentOptions instanceof Closure) {
            $options = call_user_func($this->contentOptions, $model, $key, $index, $this);
        } else {
            $options = $this->contentOptions;
        }

        $content = $this->renderDataCellContent($model, $key, $index);

        if ($this->relation['attribute'] !== null &&
            !in_array($this->relation['attribute'], $this->relation['primaryKeys']) /*disable primary key update*/) {
            $span = Html::tag('span', $content, [
                'class' => 'h-cell-data'
            ]);

            $inputType = strtolower($this->inputType);

            if (!in_array(strtolower($inputType), ['textarea'], true)) {
                $input = Html::input($inputType, $this->relation['model']->formName() . '[' . $this->relation['uniqueId'] . '][' . $this->relation['attribute'] . ']', $this->getDataCellValue($model, $key, $index), [
                    'style' => 'display:none;',
                    'class' => 'h-cell-data-input',
                    'disabled' => 'disabled',
                    'autofocus' => true,
                    'tab-index' => 0
                ]);
            } else {
                $input = Html::beginTag($inputType, [
                    'style' => 'display:none;',
                    'class' => 'h-cell-data-input',
                    'disabled' => 'disabled',
                    'autofocus' => true,
                    'tab-index' => 0,
                    'value' => $this->getDataCellValue($model, $key, $index),
                    'name' => $this->relation['model']->formName() . '[' . $this->relation['uniqueId'] . '][' . $this->relation['attribute'] . ']',
                ]);
                $input .= $this->getDataCellValue($model, $key, $index);
                $input .= Html::endTag($inputType);
            }

            return Html::tag('td', $span . $input, $options);
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
                throw new Exception("Attribute not found.");
            }
            $relation = implode('.', $explode);
            $relationalModel = ArrayHelper::getValue($model, $relation);
            /* @var ActiveRecord $relationalModel */
            if (empty($relationalModel)) {
                throw new Exception("Relational model not found.");
            }
            return [
                'model' => ArrayHelper::getValue($model, $relation),
                'attribute' => $attribute,
                'primaryKeys' => $relationalModel::primaryKey(),
                'uniqueId' => $this->getPrimaryKeys($relationalModel)[0]
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
}