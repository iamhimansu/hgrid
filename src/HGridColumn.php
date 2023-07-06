<?php

namespace app\hgrid\src;

use app\hgrid\src\helpers\Obfuscator;
use Closure;
use Exception;
use stdClass;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\grid\DataColumn;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * @property HGrid $grid
 */
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
     * Contains the input checkbox for setting null value
     *
     * @var null
     */
    public $nullInput = null;
    /**
     * @var array $noRelationOptions
     * Takes the configurations for displaying or wrapping the data if no relation is found.
     *  [
     *      'tag' => 'a',
     *      'options' => [
     *          'href' => '',
     *      ]
     *  ]
     */
    public $noRelationOptions = [];
    private $_nullInput = [];
    /**
     * @var string $_noRelationHtml
     */
    private $_noRelationHtml;
    /**
     * @var stdClass $relation
     * holds the relation for the given column if it is a related record
     */
    private stdClass $_relation;

    private bool $_isRelational;

    public function __construct($config = [])
    {
        if (empty($this->noRelationOptions)) {
            //TODO: Create no relational html once per column
        }

        /**
         * Holds default array for creating NULL inputs
         */
        if (empty($this->_nullInput) && $this->nullInput !== false) {
            $this->_nullInput = [
                Html::beginTag('div', [
                    'style' => 'display:none',
                    'class' => 'hgrid-checkbox-parent'
                ]),
                null,
                Html::endTag('div')
            ];
        }
        parent::__construct($config);
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

        $relationalData = $this->getRelation();

        $uniqueId = $this->getUniqueRecordId($model);

        $isRequired = $relationalData->modelClass->isAttributeRequired($relationalData->attribute);

        $inputId = Html::getInputId(
                $relationalData->modelClass,
                $relationalData->attribute
            ) . "$key$columnIndex";

        $inputName = "$relationalData->formName[$uniqueId][$relationalData->attribute]";

        $this->_relation->name = $inputName;
        $this->_relation->id = $inputId;
        $this->_relation->data->attribute = $relationalData->attribute;
        $this->_relation->data->model = "Models[$relationalData->formName][$uniqueId]";
        $this->_relation->data->classToken = $this->getModelToken();

        $inputOptions = [
            'name' => $inputName,
            'id' => $inputId,
            'value' => $this->getDataCellValue($model, $key, $index)
        ];

        $input = $this->renderInput(
            $model,
            $relationalData,
            $key,
            $index,
            $columnIndex,
            $uniqueId,
            $isRequired,
            $inputOptions);

        $data = [Html::tag('span', $content, [
            'class' => 'h-cell-data',
            'data' => [
                'model-content' => "$relationalData->formName[$uniqueId][$relationalData->attribute]"
            ]
        ])];

        $this->grid->activeField->model = $relationalData->modelClass;
        $this->grid->activeField->attribute = $relationalData->attribute;
        $this->grid->activeField->setInputId($inputId);
        $this->grid->activeField->setInputName($inputName);

        $options['class'] = "h-cell h-field-$inputId" . ($options['class'] ?? '');
        if ($isRequired) {
            $options['class'] = "h-cell h-field-$inputId required {$options['class']}";
        }
        $this->grid->activeField->selectors['container'] = ".h-field-$inputId";
        $clientOptions = $this->grid->activeField->getClientOptions();

        if (!empty($clientOptions)) {
            $this->grid->attributes[] = $clientOptions;
        }

        if (!empty($this->getRelation()->modelClass) &&
            !empty($this->getUniqueRecordId($model)) &&
            null !== $relationalData->attribute
        ) {
            if (!in_array($relationalData->attribute, $relationalData->primaryKey)) {
                $data[] = $input;
                return Html::tag('td', implode('', $data), $options);
            }
        } else {
            if ($this->isRelational()) {
                $noRelation = Html::tag('a', $content, [
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
                $options = [];
                return Html::tag('td', $noRelation, $options);
            }
        }
        return Html::tag('td', $content, $options);
    }

    /**
     * @return object
     */
    public function getRelation(): stdClass
    {
        return $this->_relation;
    }

    /**
     * @param stdClass $relation
     * @return HGridColumn
     */
    public function setRelation(stdClass $relation): HGridColumn
    {
        $this->_relation = $relation;
        return $this;
    }

    /**
     * @throws Exception
     */
    private function getUniqueRecordId($model): ?string
    {
        $relationData = $this->getRelation();
        /* @var ActiveRecord $model */
        $keyValues = [];
        if ($this->isRelational() && null !== ($relationalModel = $relationData->relation)) {
            $model = ArrayHelper::getValue($model, $relationalModel);
        }
        foreach ($relationData->primaryKey as $keyPart) {
            if (isset($model->$keyPart)) {
                $keyValues[] = $model->$keyPart;
            } else {
//                throw new Exception('Primary key does not exist for model "' . $model->formName().'"');
                return null;
            }
        }

        return implode('___', $keyValues);
    }

    /**
     * @return bool
     */
    public function isRelational(): bool
    {
        return $this->_isRelational;
    }

    /**
     * @return mixed|null
     */
    public function getModelToken()
    {
        return $this->_relation->modelToken ?? null;
    }

    private function renderInput($model, $relationalData, $key, $index, $columnIndex, $uniqueId, $isRequired, $options = [])
    {
        $errorBlock = $this->grid->activeField->getErrorBlock();
        $nullInput = '';
        $_options = [
            'autofocus' => true,
            'class' => 'h-cell-data-input',
            'data' => [
                'attribute' => $relationalData->attribute,
                'model' => "Models[$relationalData->formName][$uniqueId]",
                'classToken' => $this->getModelToken(),
            ],
            'disabled' => 'disabled',
            'id' => null,
            'name' => null,
            'readonly' => 'readonly',
            'style' => 'display:none;',
            'tabindex' => 1,
            'value' => null,
        ];

        $_options = array_replace_recursive($_options, $options);
        //
        if (!$isRequired) {
            $nullInput = $this->createNullInput(
                $relationalData->formName,
                $uniqueId,
                $relationalData->attribute
            );
        }

        if ($this->input instanceof Closure) {
            return call_user_func($this->input, $model, $relationalData, $key, $index, $this) . $errorBlock;
        }
        if (strtolower($this->input) === 'boolean' || strtolower($this->input) === 'bool') {
            return Html::dropDownList($relationalData->formName, null, [
                    null => 'Select',
                    1 => $this->grid->formatter->booleanFormat[1],
                    0 => $this->grid->formatter->booleanFormat[0],
                ], $_options) . $errorBlock . $nullInput;
        }

        return Html::textarea(
                $relationalData->formName,
                $_options['value'],
                $_options
            ) . $errorBlock . $nullInput;

    }

    /**
     * @param $formName
     * @param $recordId
     * @param $attribute
     * @return string
     */
    private function createNullInput($formName, $recordId, $attribute)
    {
        $this->_nullInput[1] = Html::checkbox($formName . '[' . $recordId . '][' . $attribute . ']',
            false,
            [
                'label' => 'Set NULL',
                'value' => 1, // The value to be submitted when the checkbox is checked
                'class' => 'hgrid-checkbox', // CSS class for styling
                'disabled' => 'disabled',
                'readonly' => 'true',
            ]);
        return implode('', $this->_nullInput);
    }

    /**
     * @param string $modelToken
     */
    public function setModelToken(string $modelToken): void
    {
        $this->_relation->modelToken = $modelToken;
    }

    /**
     * @param bool $isRelational
     */
    public function setIsRelational(bool $isRelational): void
    {
        $this->_isRelational = $isRelational;
    }

}