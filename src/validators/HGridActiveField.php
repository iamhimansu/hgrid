<?php

namespace app\hgrid\src\validators;

use yii\helpers\Html;
use yii\validators\Validator;
use yii\web\JsExpression;
use yii\widgets\ActiveField;

class HGridActiveField extends ActiveField
{
    public $model = null;
    public $attribute = null;
    public $form = null;
    public $grid;
    private $_inputId;
    private $_name;
    private $_errorBlock;

    public function __construct($config = [])
    {
        $this->_errorBlock = Html::tag('div', null, [
            'class' => 'help-block'
        ]);
        parent::__construct($config);
    }

    /**
     * Returns the JS options for the field.
     * @return array the JS options.
     */
    public function getClientOptions()
    {
        $attribute = Html::getAttributeName($this->attribute);
        if (!in_array($attribute, $this->model->activeAttributes(), true)) {
            return [];
        }

        $clientValidation = $this->isClientValidationEnabled();
        $ajaxValidation = $this->isAjaxValidationEnabled();

        if ($clientValidation) {
            $validators = [];
            foreach ($this->model->getActiveValidators($attribute) as $validator) {
                /* @var $validator Validator */
                $js = $validator->clientValidateAttribute($this->model, $attribute, $this->grid->getView());
                if ($validator->enableClientValidation && $js != '') {
                    if ($validator->whenClient !== null) {
                        $js = "if (({$validator->whenClient})(attribute, value)) { $js }";
                    }
                    $validators[] = $js;
                }
            }
        }

        if (!$ajaxValidation && (!$clientValidation || empty($validators))) {
            return [];
        }

        $options = [];

        $inputID = $this->getInputId();
        $options['id'] = $inputID ?: Html::getInputId($this->model, $this->attribute);
        $options['name'] = $this->getInputName();

        $options['container'] = $this->selectors['container'] ?? ".field-$inputID";
        $options['input'] = $this->selectors['input'] ?? "#$inputID";
        if (isset($this->selectors['error'])) {
            $options['error'] = $this->selectors['error'];
        } elseif (isset($this->errorOptions['class'])) {
            $options['error'] = '.' . implode('.', preg_split('/\s+/', $this->errorOptions['class'], -1, PREG_SPLIT_NO_EMPTY));
        } else {
            $options['error'] = $this->errorOptions['tag'] ?? 'span';
        }

        $options['encodeError'] = !isset($this->errorOptions['encode']) || $this->errorOptions['encode'];
        if ($ajaxValidation) {
            $options['enableAjaxValidation'] = true;
        }
        foreach (['validateOnChange', 'validateOnBlur', 'validateOnType', 'validationDelay'] as $name) {
            //todo fix grid validators
            $options[$name] = $this->$name === null ?/* $this->grid->$name*/ : $this->$name;
        }
        if (!empty($validators)) {
            $options['validate'] = new JsExpression('function (attribute, value, messages, deferred, $form) {' . implode('', $validators) . '}');
        }

        if ($this->addAriaAttributes === false) {
            $options['updateAriaInvalid'] = false;
        }

        // only get the options that are different from the default ones (set in yii.activeForm.js)
        return array_diff_assoc($options, [
            'validateOnChange' => true,
            'validateOnBlur' => true,
            'validateOnType' => false,
            'validationDelay' => 500,
            'encodeError' => true,
            'error' => '.help-block',
            'updateAriaInvalid' => true,
        ]);
    }

    /**
     * Checks if client validation enabled for the field.
     * @return bool
     * @since 2.0.11
     */
    protected function isClientValidationEnabled(): bool
    {
        return $this->enableClientValidation || $this->enableClientValidation === null && $this->grid->enableClientValidation;
    }

    /**
     * Checks if ajax validation enabled for the field.
     * @return bool
     * @since 2.0.11
     */
    protected function isAjaxValidationEnabled(): bool
    {
        return $this->enableAjaxValidation || $this->enableAjaxValidation === null && $this->grid->enableAjaxValidation;
    }

    /**
     * Returns the HTML `id` of the input element of this form field.
     * @return string the input id.
     * @since 2.0.7
     */
    protected function getInputId(): string
    {
        return $this->_inputId ?: Html::getInputId($this->model, $this->attribute);
    }

    public function setInputId($id)
    {
        $this->_inputId = $id;
    }

    public function getInputName()
    {
        if (empty($this->_name)) {
            $this->_name = $this->attribute;
        }
        return $this->_name;
    }

    public function setInputName($name)
    {
        $this->_name = $name;
    }

    public function getErrorBlock()
    {
        return $this->_errorBlock;
    }
}