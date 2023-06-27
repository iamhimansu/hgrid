<?php

namespace app\hgrid\src;

use app\hgrid\src\helpers\Obfuscator;
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
    private array $_relation;

    private string $_pk_separator = '__';
    /**
     * @var string|Closure|null an anonymous function or a string that is used to determine the value to display in the current column.
     */
    public $formInput = self::DEFAULT_INPUT_TYPE;

    /**
     * @var string $formName
     * Holds the formName of the current model
     */
    private string $formName;

    private bool $_isRelational;

    /**
     * Renders a data cell.
     * @param mixed $model the data model being rendered
     * @param mixed $key the key associated with the data model
     * @param int $index the zero-based index of the data item among the item array returned by [[GridView::dataProvider]].
     * @return string the rendering result
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function renderDataCell($model, $key, $index): string
    {
        /* @var $model ActiveRecord */

        if ($this->contentOptions instanceof Closure) {
            $options = call_user_func($this->contentOptions, $model, $key, $index, $this);
        } else {
            $options = $this->contentOptions;
        }

        $content = $this->renderDataCellContent($model, $key, $index);

        $uniqueId = $this->getUniqueId($model, $index);
//        var_dump('Row index: '.$index.', unique id: '.$uniqueId.', formname:'. ($this->isRelational() ? $this->getRelation()['modelClass']->formName() : $model->formName()));
        //Will not be queried again and again
//        $t = $this->$this->getRelation()['modelClass']::getTableSchema()->getColumn($this->$this->getRelation()['attribute'])->type;

        if ($this->getRelation()['attribute'] !== null &&
            !in_array($this->getRelation()['attribute'], $this->getRelation()['primaryKey']) /*disable primary key update*/) {
            $formName = $this->getRelation()['formName'];
            $span = Html::tag('span', $content, [
                'class' => 'h-cell-data',
                'data' => [
                    'model-content' => $formName . '[' . $uniqueId . '][' . $this->getRelation()['attribute'] . ']'
                ]
            ]);
            return Html::tag('td', $span . $this->createFormInput($formName, $key, $index, $content, $uniqueId), $options);
        }
        return Html::tag('td', $content, $options);
    }

    protected function createFormInput($formName, $key, $index, $cellValue, $uniqueId)
    {
        $inputOptions = [
            'style' => 'display:none;',
            'class' => 'h-cell-data-input',
            'disabled' => 'disabled',
            'autofocus' => true,
            'tabindex' => 1,
            'name' => $formName . '[' . $uniqueId . '][' . $this->getRelation()['attribute'] . ']',
            'data' => [
                'attribute' => $this->getRelation()['attribute'],
                'model' => 'Models[' . $formName . '][' . $uniqueId . ']',
                'classToken' => $this->getModelToken(),
            ]
        ];

        $formInput = strtolower($this->formInput);

        if (!in_array(strtolower($formInput), ['textarea'], true)) {
            $input = Html::input(
                $formInput,
                $formName . '[' . $uniqueId . '][' . $this->getRelation()['attribute'] . ']',
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

    /**
     * @return mixed|null
     */
    public function getModelToken()
    {
        return $this->getRelation()['modelToken'] ?? null;
    }

    /**
     * @param string $modelToken
     */
    public function setModelToken(string $modelToken): void
    {
        $this->$this->getRelation()['modelToken'] = $modelToken;
    }

    /**
     * @return bool
     */
    public function isRelational(): bool
    {
        return $this->_isRelational;
    }

    /**
     * @param bool $isRelational
     */
    public function setIsRelational(bool $isRelational): void
    {
        $this->_isRelational = $isRelational;
    }

    /**
     * @param array $relation
     * @return HGridColumn
     */
    public function setRelation(array $relation): HGridColumn
    {
        $this->_relation = $relation;
        return $this;
    }

    /**
     * @return array
     */
    public function getRelation(): array
    {
        return $this->_relation;
    }

    /**
     * @throws Exception
     */
    private function getUniqueId($model, $index = null): string
    {
        /* @var ActiveRecord $model */
        $keyValues = [];
        if ($this->isRelational() && null !== ($relationalModel = $this->getRelation()['relation'])){
            $model = ArrayHelper::getValue($model, $relationalModel);
        }
        foreach ($this->getRelation()['primaryKey'] as $keyPart) {
            if (isset($model->$keyPart)) {
                $keyValues[] = $model[$keyPart];
            } else {
//                throw new Exception('Primary key does not exist for model "' . $model->formName().'"');
                return 'sss';
            }
        }

        return implode($this->_pk_separator, $keyValues);
    }
}