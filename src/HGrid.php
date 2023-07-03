<?php

namespace app\hgrid\src;

use app\hgrid\src\validators\HGridActiveField;
use Closure;
use Yii;
use yii\base\InvalidConfigException;
use yii\bootstrap5\NavBar;
use yii\db\ActiveRecord;
use yii\grid\GridView;
use yii\grid\GridViewAsset;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\widgets\ActiveField;
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
    private array $_attributeMaps;

    private array $_dmodels;

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

        $appendClass = 'h-grid ' . $this->options['class'];
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

    public function afterRun($result)
    {
        $view = $this->getView();
        $attributes = Json::htmlEncode($this->attributes);
        $id = $this->options['id'];
        ActiveFormAsset::register($view);
        $view->registerJs("jQuery('#$id').yiiActiveForm($attributes, []);");
        return parent::afterRun($result); // TODO: Change the autogenerated stub
    }

    /**
     * Creates column objects and initializes them.
     * @throws InvalidConfigException
     */
    protected function initColumns()
    {
        $start_time = gettimeofday(true);

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

            if (!$column->visible) {
                unset($this->columns[$i]);
                continue;
            }
            $this->columns[$i] = $column;
        }

        $this->populateColumnsWithRelationalArray();

        $end_time = gettimeofday(true);

//        echo round($end_time - $start_time, 6) . 's';
//        die;
    }

    private function populateColumnsWithRelationalArray(): void
    {
        foreach ($this->columns as $i => &$column) {
            if ($column instanceof HGridColumn) {
                $column = $this->extractRelationArray($column);
            }
        }
    }

    private function initModels():void
    {
        $this->_dmodels = $this->dataProvider->getModels();
    }


    /**
     * Extracts the relational model if it is not null
     * @param HGridColumn $column
     * @return HGridColumn
     * @throws InvalidConfigException
     */
    protected function extractRelationArray(HGridColumn $column): HGridColumn
    {

        if (!empty($allModels = $this->dataProvider->getModels())) {
            $i = 0;
            /* @var ActiveRecord $relationalModel */
            $relationalModel = $allModels[0];
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


}