<?php
namespace app\hgrid\src;

use yii\web\AssetBundle;

/**
 * Asset bundle for [[HGrid]] Widget.
 *
 * @author Himanshu Raj Aman
 * @since 1.0
 */
class HGridAssets extends AssetBundle
{
    public $css = [
    ];

    public $js = [

    ];

    public $depends = [
        'yii\web\YiiAsset'
    ];

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->sourcePath = __DIR__ . '/assets';
    }

}

