<?php
declare(strict_types = 1);

namespace pozitronik\widgets;

use pozitronik\helpers\ArrayHelper;
use Throwable;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\Html;

/**
 * Class BadgeOptions
 * @property array|callable $options HTML-опции для каждого значка по умолчанию. Если передано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть массив опций для этого элемента.
 * @property bool|int|callable $visible Параметр, определяющий, какие элементы будут отображены.
 *        true - будут отображены все элементы,
 *        false - будут скрыты все элементы (предполагается взаимодействие через $addon)
 *        int - будет отображено указанное число первых элементов,
 *        callable - будет вызвана функция, в которой параметром будет передан ключ элемента (если есть). Логический результат выполнения этой функции определяет отображение элемента.
 * @property bool|string|callable $addon Элемент, используемый для отображения информации о скрытых значках и разворачивании всего списка. Значения:
 *        false - не будет показан ни в каких случаях,
 *        true - будет показан сгенерированный элемент, отображающий информацию о скрытых значках,
 *        string - будет отображена заданная строка,
 *        callable - будет вызвана функция, получающая на вход первым параметром количество видимых элементов, вторым параметром количество скрытых элементов, которая должна вернуть строку с содержимым.
 * @property null|array $addonOptions HTML-опции элемента. null - взять из массива $options (если он задан массивом).
 * @property TooltipOptions|false|array $addonTooltipOptions Настройки всплывающей подсказки на элементе, см. BadgeWidget::$tooltipOptions
 */
class BadgeOptions extends Model {
	public $options;
	public $visible;
	public $addon;
	public $addonTooltip;

	private $_visibleElementsCount;
	private $_hiddenElementsCount;

	/**
	 * @param Model $item
	 * @param string $mapAttribute
	 * @return array
	 * @throws Throwable
	 */
	public function prepareOptions(Model $item, string $mapAttribute):array {
		return ArrayHelper::getValue($this->options, $item->{$mapAttribute}, $this->options);
	}

	/**
	 * @param string[] $items
	 * @return string[]
	 * @throws InvalidConfigException
	 */
	public function prepareResult(array $items):array {
		$mainArray = [];
		$itemsCount = count($items);
		if (true === $this->visible) {
			$mainArray = $items;
		} elseif (false === $this->visible) {
			$mainArray = [];//todo delete branch

		} elseif (is_int($this->visible)) {
			if ($itemsCount > $this->visible) {
				$mainArray = $items;
				array_splice($mainArray, $this->visible, $itemsCount);
			} else {
				//todo
			}
		} elseif (is_callable($this->visible)) {
			$mainArray = [];
			foreach ($items as $itemKey => $itemValue) {
				if (true === call_user_func($this->visible, $itemKey)) $mainArray[$itemKey] = $itemValue;
			}

		} else {
			throw new InvalidConfigException('Wrong type for "visible" parameter');
		}
		$this->_visibleElementsCount = count($mainArray);
		$this->_hiddenElementsCount = $itemsCount - $this->_visibleElementsCount;
		if (false !== $this->addon) {
			$mainArray[] = $this->prepareAddon();
		}

		return $mainArray;

	}

	/**
	 * @return string
	 * @throws InvalidConfigException
	 */
	private function prepareAddon():string {
		if (true === $this->addon) {
			$addonText = "...ещё {$this->_hiddenElementsCount}";
		} elseif (is_string($this->addon)) {
			$addonText = $this->addon;
		} elseif (is_callable($this->addon)) {
			$addonText = call_user_func($this->addon, $this->_visibleElementsCount, $this->_hiddenElementsCount);
		} else {
			throw new InvalidConfigException('Wrong type for "addon" parameter');
		}
		if (null === $this->addonOptions && is_array($this->options)) {
			$addonOptions = $this->options;
		} else {
			$addonOptions = $this->addonOptions;
		}

		return Html::tag("span", $addonText, array_merge(['class' => 'badge badge-addon'], $addonOptions));
	}

}