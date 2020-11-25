<?php
declare(strict_types = 1);

namespace pozitronik\widgets;

use yii\web\AssetBundle;

/**
 * Class BadgeWidgetAssets
 */
class BadgeWidgetAssets extends AssetBundle {

//	public $publishOptions = [
//		'forceCopy' => YII_ENV_DEV
//	];

	/**
	 * @inheritdoc
	 */
	public function init():void {
		$this->sourcePath = __DIR__.'/assets';
		$this->css = ['css/badge.css'];
		parent::init();
	}
}