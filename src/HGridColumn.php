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
use yii\widgets\ActiveField;

class HGridColumn extends DataColumn
{
    const DEFAULT_INPUT_TYPE = 'text';
    /**
     * @var string|Closure|null an anonymous function or a string that is used to determine the value to display in the current column.
     */
    public $formInput = self::DEFAULT_INPUT_TYPE;
    /**
     * @var array|Closure|null this will contain the customized input
     */
    public $inputOptions;
    /**
     * @var string
     */
    public $input = null;
    /**
     * @var array Takes the configurations for displaying or wrapping the data if no relation is found.
     *  [
     *      'tag' => 'a',
     *      'options' => [
     *          'href' => '',
     *      ]
     *  ]
     */
    public $noRelationOptions = [];

    /**
     * @var string $_noRelationHtml
     */
    private $_noRelationHtml;
    /**
     * @var $relation array
     * holds the relation for the given column if it is a related record
     */
    private array $_relation;
    private bool $_isRelational;

    public function __construct($config = [])
    {
        parent::__construct($config);
        if (empty($this->noRelationOptions)) {
            //TODO: Create no relational html once per column
        }
    }

    /**
     * Renders a data cell.
     * @param mixed $model the data model being rendered
     * @param mixed $key the key associated with the data model
     * @param int $index the zero-based index of the data item among the item array returned by [[GridView::dataProvider]].
     * @return string the rendering result
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function renderDataCell($model, $key, $index, $columnIndex = null): string
    {

        /* @var $model ActiveRecord */

        if ($this->contentOptions instanceof Closure) {
            $options = call_user_func($this->contentOptions, $model, $key, $index, $this);
        } else {
            $options = $this->contentOptions;
        }

        $content = $this->renderDataCellContent($model, $key, $index);
        $contentRaw = $this->getDataCellValue($model, $key, $index);
        $uniqueId = $this->getUniqueId($model, $index);
        $inputId = Html::getInputId($this->getRelation()['modelClass'], $this->getRelation()['attribute']) . "$key$columnIndex";
        $inputName = $this->getRelation()['formName'] . '[' . $uniqueId . '][' . $this->getRelation()['attribute'] . ']';

        $inputOptions = [
            'style' => 'display:none;',
            'id' => $inputId,
            'class' => 'h-cell-data-input',
            'disabled' => 'disabled',
            'readonly' => 'readonly',
            'value' => $contentRaw,
            'autofocus' => true,
            'tabindex' => 1,
            'name' => $inputName,
            'aria' => [
                'required' => $this->getRelation()['modelClass']->isAttributeRequired($this->getRelation()['attribute']) ? 'true': 'false'
            ],
            'data' => [
                'attribute' => $this->getRelation()['attribute'],
                'model' => 'Models[' . $this->getRelation()['formName'] . '][' . $uniqueId . ']',
                'classToken' => $this->getModelToken(),
            ]
        ];
        $formInput = null;
        if (!empty($uniqueId)) {
            if (!empty($this->input)) {
                if ($this->input instanceof Closure) {
                    $formInput = call_user_func($this->input, $model, $this->getRelation(), $key, $index, $this);
                } else if (strtolower($this->format) === 'boolean' || strtolower($this->input) === 'boolean' || strtolower($this->input) === 'bool') {
                    $formInput = Html::dropDownList($this->getRelation()['formName'], null, [
                        null => 'Select',
                        1 => $this->grid->formatter->booleanFormat[1],
                        0 => $this->grid->formatter->booleanFormat[0],
                    ], $inputOptions);
                } else if (is_string($this->input)) {
                    $formInput = $this->input;
                }
            }
        }

        $grid = $this->grid;
        /* @var HGrid $grid */

        $grid->activeField->model = $this->getRelation()['modelClass'];
        $grid->activeField->attribute = $this->getRelation()['attribute'];
        $grid->activeField->setInputId($inputId);
        $grid->activeField->setInputName($inputName);
        $this->contentOptions['class'] = "h-cell h-field-$inputId";
        if ($this->getRelation()['modelClass']->isAttributeRequired($this->getRelation()['attribute'])) {
            $this->contentOptions['class'] = "h-cell h-field-$inputId required";
        }
        $grid->activeField->selectors['container'] = "h-field-$inputId";
        $clientOptions = $grid->activeField->getClientOptions();
        $options = $this->contentOptions;

        if (!empty($clientOptions)) {
            $grid->attributes[] = $clientOptions;
        }

        if (empty($formInput)) {
            $formInput = Html::activeTextarea(
                $this->getRelation()['modelClass'],
                $this->getRelation()['attribute'],
                $inputOptions
            );
        }

        $setNull = Html::beginTag('div', [
            'style' => 'display:none',
            'class' => 'hgrid-checkbox-parent'
        ]);
        $setNull .= Html::checkbox($this->getRelation()['formName'] . '[' . $uniqueId . '][' . $this->getRelation()['attribute'] . ']',
            false,
            [
                'label' => 'NULL',
                'value' => 1, // The value to be submitted when the checkbox is checked
                'class' => 'hgrid-checkbox', // CSS class for styling
                'disabled' => 'disabled',
                'readonly' => 'true'
            ]);
        $setNull .= Html::endTag('div');

        $errorBlock = '<div class="help-block"></div>';

        if (!empty($uniqueId = $this->getUniqueId($model, $index))) {
            if ($this->getRelation()['attribute'] !== null &&
                !in_array($this->getRelation()['attribute'], $this->getRelation()['primaryKey']) /*disable primary key update*/) {
                $formName = $this->getRelation()['formName'];
                $span = Html::tag('span', $content, [
                    'class' => 'h-cell-data',
                    'data' => [
                        'model-content' => $formName . '[' . $uniqueId . '][' . $this->getRelation()['attribute'] . ']'
                    ]
                ]);
                return Html::tag('td', $span . $formInput . $errorBlock . $setNull, $options);
            }
        } else {
            if ($this->isRelational()) {
                $info = Html::beginTag('a', [
                    'href' => 'javascript:void(0)',
                    'style' => 'white-space: nowrap',
                    'class' => 'link-body-emphasis',
                    'title' => 'No relation found.',
                    'data' => [
                        'toggle' => 'tooltip',
                        'placement' => 'top',
                        'title' => 'No relation found.'
                    ]
                ]);
                $info .= $content;
                $info .= Html::endTag('a');
                return Html::tag('td', $info, $options);
            }
        }
        return Html::tag('td', $content, $options);
    }

    /**
     * @throws Exception
     */
    private function getUniqueId($model, $index = null): ?string
    {
        /* @var ActiveRecord $model */
        $keyValues = [];
        if ($this->isRelational() && null !== ($relationalModel = $this->getRelation()['relation'])) {
            $model = ArrayHelper::getValue($model, $relationalModel);
        }
        foreach ($this->getRelation()['primaryKey'] as $keyPart) {
            if (isset($model->$keyPart)) {
                $keyValues[] = $model->$keyPart;
            } else {
//                throw new Exception('Primary key does not exist for model "' . $model->formName().'"');
                return null;
            }
        }

        return implode('', $keyValues);
    }

    /**
     * @return bool
     */
    public function isRelational(): bool
    {
        return $this->_isRelational;
    }

    /**
     * @return array
     */
    public function getRelation(): array
    {
        return $this->_relation;
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
     * @param bool $isRelational
     */
    public function setIsRelational(bool $isRelational): void
    {
        $this->_isRelational = $isRelational;
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
        $setNull = Html::beginTag('div', [
            'style' => 'display:none',
            'class' => 'hgrid-checkbox-parent'
        ]);
        $setNull .= Html::checkbox($formName . '[' . $uniqueId . '][' . $this->getRelation()['attribute'] . ']',
            false,
            [
                'label' => 'NULL',
                'value' => 1, // The value to be submitted when the checkbox is checked
                'class' => 'hgrid-checkbox', // CSS class for styling
                'disabled' => 'disabled',
                'readonly' => 'true'
            ]);
        $setNull .= Html::endTag('div');
        return $input . $setNull;
    }
}