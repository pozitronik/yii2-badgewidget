<?php
declare(strict_types = 1);

namespace pozitronik\widgets;

use pozitronik\helpers\Utils;
use pozitronik\helpers\ArrayHelper;
use pozitronik\helpers\ReflectionHelper;
use Throwable;
use yii\base\DynamicModel;
use yii\base\Model;
use yii\helpers\Html;

/**
 * Class BadgeWidget
 * @property-write string|array|object|callable $items Данные для обработки (строка, массив, модель, замыкание). Преобразуются в массив при обработке.
 * @property-write string $subItem Отображаемый ключ (строка => null, массив => key, модель => атрибут/свойство/переменная, замыкание => параметр). Виджет пытается просчитать его автоматически.
 * @property string $itemsSeparator Строка-разделитель между элементами
 * @property string|null $emptyText Текст иконки, подставляемой при отсутствии обрабатываемых данных
 * @property-write bool $iconize Содержимое бейджа сокращается до псевдоиконки.
 *  *
 * @property-write string|callable $innerPrefix Строка, добавляемая перед текстом внутри значка. Если передано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть строку для этого элемента.
 * @property-write string|callable $innerPostfix Строка, добавляемая после текста внутри значка. Если передано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть строку для этого элемента.
 * @property-write string|callable $outerPrefix Строка, добавляемая перед текстом снаружи значка. Если передано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть строку для этого элемента.
 * @property-write string|callable $outerPostfix Строка, добавляемая перед текстом внутри значка. Если передано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть строку для этого элемента.
 * @property-write string $allPrefix Строка, добавляемая перед всем массивом значков.
 *
 * @property null|string $mapAttribute Атрибут, значение которого будет использоваться как ключевое при сопоставлении элементов с массивами параметров. Если не задан, виджет попытается вычислить его самостоятельно, взяв ключевой атрибут для ActiveRecord или ключ для элемента массива.
 *
 * @property-write BadgeOptions|array|false $badgeOptions Настройки значков. false - не использовать значки.
 * @property-write UrlOptions|array|false $urlOptions Настройки сопоставления элементов со ссылками. false - элементы не превращаются в ссылки.
 * @property-write TooltipOptions|array|false $tooltipOptions Настройки для всплывающей подсказки. false - всплывающая подсказка не используется.
 *
 *
 */
class BadgeWidget extends CachedWidget {
	public $subItem;
	public $itemsSeparator = ', ';
	public $iconize = false;
	public $innerPrefix = '';
	public $innerPostfix = '';
	public $outerPrefix = '';
	public $outerPostfix = '';
	public $allPrefix = '';
	public $emptyResult;


	/**
	 * @var array
	 */
	private $_items = [];
	/**
	 * @var BadgeOptions|false
	 */
	private $_badgeOptions;

	/**
	 * @var UrlOptions|false
	 */
	private $_urlOptions;

	/**
	 * @var TooltipOptions|false
	 */
	private $_tooltipOptions;

	/* Необработанные значения атрибутов, нужны для вывода подсказки в тултип */
	private $_rawResultContents = [];

	/**
	 * Функция инициализации и нормализации свойств виджета
	 */
	public function init():void {
		parent::init();
		BadgeWidgetAssets::register($this->getView());
	}

	/**
	 * @return array
	 */
	public function getItems():array {
		return $this->_items;
	}

	/**
	 * @param array|callable|object|string $items
	 */
	public function setItems($items):void {
		$this->_items = $items;
		if (ReflectionHelper::is_closure($this->_items)) $this->_items = call_user_func($this->_items);
		if (!is_array($this->_items)) $this->_items = [$this->_items];
	}

	/**
	 * @return array|false|BadgeOptions
	 */
	public function getBadgeOptions() {
		return $this->_badgeOptions;
	}

	/**
	 * @param array|false|BadgeOptions $badgeOptions
	 */
	public function setBadgeOptions($badgeOptions):void {
		if (false === $badgeOptions) {
			$this->_badgeOptions = false;
		} elseif (is_array($badgeOptions)) {
			$this->_badgeOptions = new BadgeOptions($badgeOptions);
		} elseif (is_object($badgeOptions)) {
			$this->_badgeOptions = $badgeOptions;
		}
	}

	/**
	 * @return false|UrlOptions
	 */
	public function getUrlOptions() {
		return $this->_urlOptions;
	}

	/**
	 * @param array|false|UrlOptions $urlOptions
	 */
	public function setUrlOptions($urlOptions):void {
		if (false === $urlOptions) {
			$this->_urlOptions = false;
		} elseif (is_array($urlOptions)) {
			$this->_urlOptions = new UrlOptions($urlOptions);
		} elseif (is_object($urlOptions)) {
			$this->_urlOptions = $urlOptions;
		}
		$this->_urlOptions = $urlOptions;
	}

	/**
	 * @return array|false|TooltipOptions
	 */
	public function getTooltipOptions() {
		return $this->_tooltipOptions;
	}

	/**
	 * @param array|false|TooltipOptions $tooltipOptions
	 */
	public function setTooltipOptions($tooltipOptions):void {
		if (false === $tooltipOptions) {
			$this->_tooltipOptions = false;
		} elseif (is_array($tooltipOptions)) {
			$this->_tooltipOptions = new TooltipOptions($tooltipOptions);
		} elseif (is_object($tooltipOptions)) {
			$this->_tooltipOptions = $tooltipOptions;
		}
		$this->_tooltipOptions = $tooltipOptions;
	}

	/**
	 * Преобразует каждый перечисляемый объект в модель для внутреннего использования
	 * @param int $index
	 * @param $item
	 * @param string $subItemName
	 * @return Model
	 */
	private static function PrepareItem(int $index, $item, $subItemName = 'value'):Model {
		if (!is_object($item)) {
			if (is_array($item)) {
				return new DynamicModel($item);
			}
			return new DynamicModel([
				'id' => $index,
				$subItemName => $item
			]);
		}
		return $item;
	}

	/**
	 * @param Model $item
	 * @return string
	 * @throws Throwable
	 */
	private function prepareValue(Model $item):string {
		$itemValue = ArrayHelper::getValue($item, $this->subItem);/*Текстовое значение значка*/
		$this->_rawResultContents[] = $itemValue;
		$prefix = (is_callable($this->innerPrefix))?call_user_func($this->innerPrefix, $item):$this->innerPrefix;
		$postfix = (is_callable($this->innerPostfix))?call_user_func($this->innerPostfix, $item):$this->innerPostfix;

		return $prefix.$itemValue.$postfix;
	}

	/**
	 * @param Model $item
	 * @return string
	 */
	public function prepareMapAttribute(Model $item):string {
		if (null === $this->mapAttribute) {
			if ($item->hasProperty('id')) {/*assume generated DynamicModel*/
				return 'id';
			}
			if ($item->hasProperty('primaryKey')) {/*assume ActiveRecord*/
				return 'primaryKey';
			}
		}
		return $this->mapAttribute;
	}

	/**
	 * Функция возврата результата рендеринга виджета
	 * @return string
	 * @throws Throwable
	 */
	public function run():string {
		$result = [];
		$moreBadge = '';

		foreach ($this->items as $index => $item) {
			if (null === $item) continue;

			$item = self::PrepareItem($index, $item, $this->subItem);
			$itemValue = $this->prepareValue($item);

			$mapAttribute = $this->prepareMapAttribute($item);

			if ($this->iconize) $itemValue = Utils::ShortifyString($itemValue);

			if ($this->urlOptions) {/*Превращает элемент в ссылку*/
				$itemValue = $this->urlOptions->prepareUrl($item, $itemValue);
			}

			if ($this->badgeOptions) {
				$itemOptions = $this->badgeOptions->prepareOptions($item, $mapAttribute);

				if ($this->tooltipOptions) {/*Добавляет к элементу всплывающую подсказку*/
					$itemOptions = $this->tooltipOptions->prepareTooltip($item, $itemOptions, $this->_rawResultContents);
				}
			} else {
				$itemOptions = false;
			}

			if ($itemOptions) {
				$result[$item->{$mapAttribute}] = Html::tag("span", $itemValue, array_merge(['class' => 'badge'], $itemOptions));
			} else {
				$result[$item->{$mapAttribute}] = $itemValue;
			}
		}

		if ([] === $result && null !== $this->emptyText) $result = [$this->emptyText];

		return $this->allPrefix.implode($this->itemsSeparator, $result).$moreBadge;

	}

}
