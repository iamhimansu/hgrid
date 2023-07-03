<?php
namespace app\hgrid\src;

use yii\web\AssetBundle;
use yii\web\JqueryAsset;

/**
 * Asset bundle for [[HGrid]] Widget.
 *
 * @author Himanshu Raj Aman
 * @since 1.0
 */
class HGridAssets extends AssetBundle
{
    public $css = ['css/style.css'];

    public $js = ['js/hgrid.js'];

    public $depends = [
        'yii\web\YiiAsset',
        'yii\web\JqueryAsset',
    ];

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->sourcePath = __DIR__ . '/assets';
    }

}

