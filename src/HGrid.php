<?php

namespace app\hgrid\src;

use app\hgrid\src\validators\HGridActiveField;
use Closure;
use Exception;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\db\ActiveRecord;
use yii\db\ActiveRecordInterface;
use yii\db\BaseActiveRecord;
use yii\grid\GridView;
use yii\grid\GridViewAsset;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\widgets\ActiveField;
use yii\widgets\ActiveForm;
use yii\widgets\ActiveFormAsset;

class HGrid extends GridView
{
    public $disablePrimaryKeysUpdate = [];
    public ?string $requestUrl = null;
    public array $attributes = [];
    public ActiveField $activeField;
    public $enableClientValidation = true;
    public $enableAjaxValidation = false;
    private array $primaryKeys = [];
    private ?string $_requestUrl = null;

    public function __construct($config = [])
    {
        parent::__construct($config);
//        $this->activeForm = new ActiveForm();
        $this->activeField = new HGridActiveField();
        $this->activeField->grid = $this;
    }

    /**
     * Runs the widget.
     */
    public function run()
    {
        $this->_requestUrl = $this->requestUrl;
        if (empty($this->requestUrl)) {
            $this->_requestUrl = Url::to(['hgrid/request/data']);
        }
        $view = $this->getView();
        GridViewAsset::register($view);
        HGridAssets::register($view);

        $appendClass = 'h-grid '.$this->options['class'];
        $this->options['class'] = $appendClass;
        //
        $id = $this->options['id'];
        $options = Json::htmlEncode(array_merge($this->getClientOptions(), ['filterOnFocusOut' => $this->filterOnFocusOut]));
        $view->registerJs("jQuery('#$id').yiiGridView($options);");
        $view->registerJs("hGrid('$this->_requestUrl');");
        parent::run();
    }

    /**
     * Renders the table body.
     * @return string the rendering result.
     * @throws InvalidConfigException
     */
    public function renderTableBody()
    {
        $models = array_values($this->dataProvider->getModels());

        $keys = $this->dataProvider->getKeys();

        $rows = [];
        foreach ($models as $index => $model) {
            /* @var ActiveRecord $model */
            $key = $keys[$index];
            if ($this->beforeRow !== null) {
                $row = call_user_func($this->beforeRow, $model, $key, $index, $this);
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }

            $rows[] = $this->renderTableRow($model, $key, $index);

            if ($this->afterRow !== null) {
                $row = call_user_func($this->afterRow, $model, $key, $index, $this);
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }
        }

        if (empty($rows) && $this->emptyText !== false) {
            $colspan = count($this->columns);

            return "<tbody>\n<tr><td colspan=\"$colspan\">" . $this->renderEmpty() . "</td></tr>\n</tbody>";
        }

        return "<tbody>\n" . implode("\n", $rows) . "\n</tbody>";
    }

    /**
     * Renders a table row with the given data model and key.
     * @param mixed $model the data model to be rendered
     * @param mixed $key the key associated with the data model
     * @param int $index the zero-based index of the data model among the model array returned by [[dataProvider]].
     * @return string the rendering result
     * @throws InvalidConfigException
     */
    public function renderTableRow($model, $key, $index): string
    {
        /* @var $model ActiveRecord */

        $cells = [];

        foreach ($this->columns as $columnIndex => $column) {

            /* @var $column HGridColumn */

            $column->contentOptions['data'] = [
                'toggle' => 'popover',
                'content' => 'lorem',
                'trigger' => 'hover'
            ];

            $cells[] = $column->renderDataCell($model, $key, $index, $columnIndex);

        }
        if ($this->rowOptions instanceof Closure) {
            $options = call_user_func($this->rowOptions, $model, $key, $index, $this);
        } else {
            $options = $this->rowOptions;
        }
        $options['class'] = 'h-grid-row';
//        $options['data-key'] = is_array($key) ? json_encode($key) : (string)$key;
//        $options['h-data']['keys'] = $model->getPrimaryKey(true);
        return Html::tag('tr', implode('', $cells), $options);
    }

    /**
     * Creates column objects and initializes them.
     * @throws InvalidConfigException
     */
    protected function initColumns()
    {
        $models = $this->dataProvider->getModels();

        if (empty($this->columns)) {
            $this->guessColumns();
        }

        foreach ($this->columns as $i => $column) {
            if (is_string($column)) {
                //
                $column = $this->createDataColumn($column);
            } else {
                $column = Yii::createObject(array_merge([
                    'class' => $this->dataColumnClass ?: HGridColumn::class,
                    'grid' => $this,
                ], $column));
            }
            if ($column instanceof HGridColumn) {
                $column = $this->extractRelationArray($column, $models);
            }
            if (!$column->visible) {
                unset($this->columns[$i]);
                continue;
            }
            $this->columns[$i] = $column;
        }
    }

    protected function createDataColumn($text)
    {
        if (!preg_match('/^([^:]+)(:(\w*))?(:(.*))?$/', $text, $matches)) {
            throw new InvalidConfigException('The column must be specified in the format of "attribute", "attribute:format" or "attribute:format:label"');
        }

        return Yii::createObject([
            'class' => $this->dataColumnClass ?: HGridColumn::class,
            'grid' => $this,
            'attribute' => $matches[1],
            'format' => $matches[3] ?? 'text',
            'label' => $matches[5] ?? null,
        ]);
    }

    /**
     * @throws InvalidConfigException
     */
    protected function extractRelationArray(HGridColumn $column, array $allModels): HGridColumn
    {
        if (!empty($allModels)) {
            $i = 0;
            /* @var ActiveRecord $relationalModel */
            $relationalModel = $allModels[$i];
            if (false !== ($index = strrpos($column->attribute, '.'))) {
                $relation = substr($column->attribute, 0, $index);
                $_attribute = substr($column->attribute, $index + 1);

                if (!empty($relation) && !empty($_attribute)) {
                    /* @var ActiveRecord $relationalModel */
                    $relationalModel = null;
                    $count = count($allModels);
                    while ($i < $count) {
                        $relationalModel = ArrayHelper::getValue($allModels[$i], $relation);
                        if (!empty($relationalModel)) {
                            break;
                        } else {
                            $i++;
                        }
                    }
                    $relationalModel = $relationalModel::instance();
                    $column->setIsRelational(true);
                    return $column->setRelation([
                        'modelClass' => $relationalModel,
                        'formName' => $relationalModel->formName(),
                        'relation' => $relation,
                        'modelToken' => Yii::$app->getSecurity()->maskToken(get_class($relationalModel)),
                        'attribute' => $_attribute,
                        'primaryKey' => array_keys($relationalModel->getPrimaryKey(true)),
                    ]);
                }
            }

            $column->setIsRelational(false);
            return $column->setRelation([
                'modelClass' => $relationalModel,
                'formName' => $relationalModel->formName(),
                'relation' => null,
                'modelToken' => Yii::$app->getSecurity()->maskToken(get_class($relationalModel)),
                'attribute' => $column->attribute,
                'primaryKey' => array_keys($relationalModel->getPrimaryKey(true)),
            ]);
        }
        $column->setIsRelational(false);
        return $column->setRelation([
            'modelClass' => null,
            'formName' => null,
            'relation' => null,
            'modelToken' => null,
            'attribute' => null,
            'primaryKey' => null,
        ]);
    }

    /**
     * Extracts the relational model if it is not null
     * @param HGridColumn $column
     * @param array $allModels
     * @return HGridColumn
     * @throws InvalidConfigException
     */
    private function createColumnWithNestedRelation(HGridColumn $column, array $allModels): HGridColumn
    {
        if (!empty($allModels)) {
            $i = 0;
            /* @var ActiveRecord $relationalModel */
            $relationalModel = $allModels[$i];
            if (false !== strpos($column->attribute, '.')) {
                $attributeParts = explode('.', $column->attribute);
                $neededAttribute = array_pop($attributeParts);
                if (!empty($attributeParts) && !empty($neededAttribute)) {
                    $count = count($allModels);
                    reset($attributeParts);
                    $relation = current($attributeParts);
                    $modelClass = $relationalModel;
                    while ($i < $count) {
                        if ($relationalModel->isRelationPopulated($relation) && $relationalModel->$relation instanceof BaseActiveRecord) {
                            $relationalModel = $relationalModel->$relation;
                            $relation = next($attributeParts);
                            if ($relation === false) {
                                try {
                                    $relationalModel = $relationalModel->getRelation(prev($attributeParts));
                                } catch (Exception $e) {
                                }
                                /* @var $modelClass ActiveRecordInterface */
                                $modelClass = $relationalModel::instance();
                                break;
                            }
                        } else {
                            $relation = prev($attributeParts);
                            $relationalModel = $allModels[++$i];
                        }
                    }

                    $column->setIsRelational(true);
                    return $column->setRelation([
                        'modelClass' => $modelClass,
                        'formName' => $modelClass->formName(),
                        'relation' => implode('.', $attributeParts),
                        'modelToken' => Yii::$app->getSecurity()->maskToken(get_class($modelClass)),
                        'attribute' => $neededAttribute,
                        'primaryKey' => array_keys($relationalModel->getPrimaryKey(true)),
                    ]);
                }
            } else {
                $relationalModel = $relationalModel::instance();
                $column->setIsRelational(false);
                return $column->setRelation([
                    'modelClass' => $relationalModel,
                    'formName' => $relationalModel->formName(),
                    'relation' => null,
                    'modelToken' => Yii::$app->getSecurity()->maskToken(get_class($relationalModel)),
                    'attribute' => $column->attribute,
                    'primaryKey' => array_keys($relationalModel->getPrimaryKey(true)),
                ]);
            }
        }
        $column->setIsRelational(false);
        return $column->setRelation([
            'modelClass' => null,
            'formName' => null,
            'relation' => null,
            'modelToken' => null,
            'attribute' => null,
            'primaryKey' => null,
        ]);
    }

    public function afterRun($result)
    {
        $view = $this->getView();
        $attributes = Json::htmlEncode($this->attributes);
        $id = $this->options['id'];
        ActiveFormAsset::register($view);
        $view->registerJs("jQuery('#$id').yiiActiveForm($attributes, []);");

        return parent::afterRun($result); // TODO: Change the autogenerated stub
    }
}