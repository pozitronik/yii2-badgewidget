<?php
declare(strict_types = 1);

namespace pozitronik\widgets;

use pozitronik\helpers\ArrayHelper;
use yii\base\Model;

/**
 * Class TooltipOptions
 * Adds bootstrap tooltip options, if necessary
 *
 * @property string|callable $tooltip Значение всплывающей подсказки значка. Может быть задано строкой, или замыканием, в которое будет передан текущий элемент.
 * @property string $tooltipPlacement Расположение подсказки, см. константы TP_*
 */
class TooltipOptions extends Model {
	public const TP_TOP = 'top';
	public const TP_RIGHT = 'right';
	public const TP_BOTTOM = 'bottom';
	public const TP_LEFT = 'left';

	public $tooltip;
	public $tooltipPlacement = self::TP_TOP;

	public function prepareTooltip(Model $item, array $itemOptions, array $rawResultsContents/*todo*/):array {
		return ArrayHelper::mergeImplode(' ', $itemOptions, [
			'class' => 'add-tooltip',
			'data-toggle' => 'tooltip',
			'data-original-title' => (is_callable($this->tooltip))?call_user_func($this->tooltip, $item):$this->tooltip,
			'data-placement' => $this->tooltipPlacement
		]);
	}
}