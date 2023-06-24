<?php

namespace app\hgrid\src;

use Closure;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\grid\GridView;
use yii\grid\GridViewAsset;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

class HGrid extends GridView
{
    public $disablePrimaryKeysUpdate = [];
    public ?string $requestUrl = null;
    private array $primaryKeys = [];
    private ?string $_requestUrl = null;

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
        $id = $this->options['id'];
        $options = Json::htmlEncode(array_merge($this->getClientOptions(), ['filterOnFocusOut' => $this->filterOnFocusOut]));
        $view->registerJs("jQuery('#$id').yiiGridView($options);");
        $view->registerJs(<<<JS
        $(document).on('dblclick', '.h-cell', function(e) {
          // console.log([e.currentTarget]||e.target.parent());
          let parent = $(e.currentTarget).find('.h-cell-data');
          let child = $(e.currentTarget).find('.h-cell-data-input');
          parent.css({
            display: 'none',
            width: '0',
            height: '0'
          });
          child.css({
            display: 'revert',
            width: '100%',
            height: '100%'
          });
          child.attr({
            disabled: null,
            readOnly: null
          });
          child.focus();
        });
        $(document).on('focusout','.h-cell', function(e) {
          
            let parent = $(e.currentTarget).find('.h-cell-data');
            let child = $(e.currentTarget).find('.h-cell-data-input');
            console.log('klop', child.val());
           
            child.css({
            display: 'none',
            width: '0',
            height: '0'
          });
          child.attr({
            disabled: 'disabled',
            readOnly: 'readonly'
          });
          parent.css({
            display: 'revert',
            width: '100%',
            height: '100%'
          });
          parent.attr({
            disabled: null,
            readOnly: null
          });
          
            const form = new FormData();
            
            form.append(child.data('model') + '[classToken]', child.data('classtoken'));
            form.append(child.data('model') + '['+child.data('attribute')+']', child.val());

          $.ajax({
            url: '$this->_requestUrl',
            method: 'post',
            data: form,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log(response);
              try{
                  if (response){
                        for (const parentKey in response) {
                            if (response.hasOwnProperty(parentKey)) {
                                const attributes = response[parentKey].attributes;
                                for (const key in attributes) {
                                    if (attributes.hasOwnProperty(key)) {
                                        const value = attributes[key];
                                        const nameSelector = parentKey+'['+key+']';
                                        const dataModelInput = $('[name=\''+nameSelector+'\']').text(value);
                                        const dataModelContent = $('[data-model-content=\''+nameSelector+'\']').text(value);
                                        dataModelInput.val(value);
                                        dataModelContent.text(value);
                                    }
                                }
                            }
                        }
                      if (response.status === 200){
                          if (response.data){
                              child.val(response.data);
                              parent.text(response.data);
                          }
                      }
                  }
              }catch (e) {
                console.log(e)
              }
            },
              error: function(xhr, status, error) {
                // Handle errors
                console.error(xhr, status, error);
              }
          });
        });
JS
            , View::POS_END);
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
        if (!empty($models)) {
            //by default, we are taking 1st models primary key
            $this->primaryKeys = array_keys($models[0]->getPrimaryKey(true));
        }
        $keys = $this->dataProvider->getKeys();

        $rows = [];
        foreach ($models as $index => $model) {
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

        foreach ($this->columns as $column) {

            /* @var $column HGridColumn */

            $column->contentOptions['class'] = 'h-cell';

            $cells[] = $column->renderDataCell($model, $key, $index);

        }
        if ($this->rowOptions instanceof Closure) {
            $options = call_user_func($this->rowOptions, $model, $key, $index, $this);
        } else {
            $options = $this->rowOptions;
        }
        $options['data-key'] = is_array($key) ? json_encode($key) : (string)$key;
        $options['h-data']['keys'] = $model->getPrimaryKey(true);
        return Html::tag('tr', implode('', $cells), $options);
    }

    /**
     * Creates column objects and initializes them.
     * @throws InvalidConfigException
     */
    protected function initColumns()
    {
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
}