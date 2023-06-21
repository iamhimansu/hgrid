<?php

namespace iamhimansu\hgrid;

use Closure;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\grid\DataColumn;
use yii\helpers\Html;

class HGridColumn extends DataColumn
{
    const DEFAULT_INPUT_TYPE = 'text';
    /**
     * @var $relation string|null
     * holds the relation for the given column if it is a related record
     */
    public ?string $relation;

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
     * Renders a data cell.
     * @param mixed $model the data model being rendered
     * @param mixed $key the key associated with the data model
     * @param int $index the zero-based index of the data item among the item array returned by [[GridView::dataProvider]].
     * @return string the rendering result
     * @throws InvalidConfigException
     */
    public function renderDataCell($model, $key, $index, $primaryKeys = []): string
    {
        if (!empty($this->relation) && isset($model->getRelatedRecords()[$this->relation])) {
            $model = $model->getRelatedRecords()[$this->relation];
            echo "<pre>";
            var_dump($this->relation);
            echo "</pre>";
            die;
        }
        /* @var $model ActiveRecord */
        if ($this->contentOptions instanceof Closure) {
            $options = call_user_func($this->contentOptions, $model, $key, $index, $this);
        } else {
            $options = $this->contentOptions;
        }

        $content = $this->renderDataCellContent($model, $key, $index);

        if ($this->attribute !== null &&
            !in_array($this->attribute, $primaryKeys) /*disable primary key update*/) {
            $span = Html::tag('span', $content, [
                'class' => 'h-cell-data'
            ]);

            $inputType = strtolower($this->inputType);

            if (!in_array(strtolower($inputType), ['textarea'], true)) {
                $input = Html::input($this->inputType, $model->formName() . '[' . $key . '][' . $this->attribute . ']', $this->getDataCellValue($model, $key, $index), [
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
                    'name' => $model->formName() . '[' . $key . '][' . $this->attribute . ']',
                ]);
                $input .= $this->getDataCellValue($model, $key, $index);
                $input .= Html::endTag($inputType);
            }

            return Html::tag('td', $span . $input, $options);
        }
        return Html::tag('td', $content, $options);
    }

}