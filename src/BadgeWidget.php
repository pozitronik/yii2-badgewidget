<?php
declare(strict_types = 1);

namespace pozitronik\widgets;

use pozitronik\helpers\Utils;
use pozitronik\helpers\ArrayHelper;
use Throwable;
use yii\base\DynamicModel;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\Html;

/**
 * Class BadgeWidget
 * @property-write string|string[]|object|object[]|callable|callable[] $items Данные для генерации значков.
 *        string - будет отображён один значок, содержащий эту строку,
 *        string[] - будут отображены значки для каждого значения массива,
 *        object - будет отображён значок с содержимым атрибута, указанного в $subItem,
 *        object[] - будут отображены значки для каждого атрибута объекта, указанного в $subItem,
 *        callable - будет вызван коллбек вида:
 *            function():string|string[] <== данные для отображения, обрабатываемые согласно вышеуказанному,
 *        callable[] - для каждого значения будет вызван коллбек вида:
 *            function(
 *                int|string $key <== ключ/индекс значения,
 *            ):object|string[] <== данные для отображения, обрабатываемые согласно вышеуказанному.
 *
 * Если параметр задан массивом, то он может содержать значения любых поддерживаемых типов.
 * Пустые значения (null) пропускаются при обработке.
 *
 * @property-write string $subItem Ключ, используемый для сопоставления отображаемых данных. Игнорируется, если $items задан как строка. Для ассоциативных и индексированных массивов вычисляется автоматически, как ключ/индекс.
 * Для объектов должен указывать на свойство, атрибут или переменную, в замыкания передаётся, как параметр.
 *
 * @property-write null|string $keyAttribute Атрибут, значение которого будет использоваться как ключевое, при сопоставлении элементов с массивами параметров и при передаче данных в коллбеки.
 * Если параметр не задан, виджет попытается вычислить его самостоятельно для каждого элемента, в зависимости от его типа:
 *            массивы: ключ значения. Элементы массивов приводятся к виду Model(['id' => $key, 'value' => $value]), т.е. $mapAttribute будет установлен, как id.
 *            ActiveRecord: ключевой атрибут,
 *            объекты с атрибутом id: id.
 * todo: Если вычислить ключевой атрибут невозможно, то не будут работать все сопоставления и коллбеки, опирающиеся на него.
 *
 * @property-write bool $useBadges Включает/отключает генерацию значков.
 * @property-write string|null $itemsSeparator Строка-разделитель между элементами. null - не использовать разделитель.
 * @property-write string|string[]|null $emptyText Текст значка, подставляемой при отсутствии обрабатываемых данных. null - не подставлять текст.
 * Если передано массивом, отображается по одному значку для каждого элемента.
 *
 * @property-write bool $iconize Содержимое бейджа сокращается до псевдоиконки.
 *
 * @property-write string|callable $innerPrefix Строка, добавляемая перед текстом внутри значка.
 * @property-write string|callable $innerPostfix Строка, добавляемая после текста внутри значка.
 * @TODO @property-write string|callable $outerPrefix Строка, добавляемая перед текстом снаружи значка.
 * @TODO @property-write string|callable $outerPostfix Строка, добавляемая перед текстом внутри значка.
 *
 * В случае, если параметр задаётся замыканием, коллбек имеет вид:
 *            function(
 *                mixed $mapAttributeValue, <== значение элемента по ключевому атрибуту,
 *                Model $item <== текущий элемент
 *            ):string <== добавляемое значение
 *
 * @property-write bool|int|callable $visible Параметр, определяющий, какие элементы будут отображены.
 *        true - будут отображены все элементы,
 *        false - будут скрыты все элементы (предполагается взаимодействие через $addon)
 *        int - будет отображено указанное число первых элементов,
 *        callable - коллбек вида
 *            function(
 *                mixed $mapAttributeValue, <== значение элемента по ключевому атрибуту,
 *                Model $item <== текущий элемент
 *            ):bool <== true для отображения элемента
 *
 * @property-write array|callable $options HTML-опции для каждого значка по умолчанию. В случае, если параметр задаётся замыканием, коллбек имеет вид:
 *            function(
 *                mixed $mapAttributeValue, <== значение элемента по ключевому атрибуту,
 *                Model $item <== текущий элемент
 *            ):array <== массив HTML-опций для значка элемента
 *
 * @property-write array|false $urlScheme Схема подстановки значений атрибутов элемента в генерируемую ссылку, например:
 *        $item = {"key" => 1, "value" => 100, "property" => "propertyData", "arrayParameter" => ["a" => 10, "b" => 20, "c" => 30]]}
 *        UrlOptions->scheme = ['site/index', 'id' => 'value', 'param1' => 'property', 'param2' => 'non-property', 'param3' => 'arrayParameter']
 * Получим набор параметров ссылки для элемента:
 *        ['site/index', 'id' => 100, 'param1' => 'propertyData', 'param2' => 'non-property', 'param3[a]' => 10, 'param3[b]' => 20, 'param3[c]' => 30]
 * false - элементы не превращаются в ссылки.
 *
 * @property-write string[]|callable|false|string $tooltip Настройки для всплывающей подсказки.
 *        false - всплывающая подсказка не используется,
 *        string - одна подсказка на все элементы,
 *        string[] - массив подсказок, сопоставляемый с элементами по ключу,
 *        callable - коллбек вида
 *            function(
 *                mixed $mapAttributeValue, <== значение элемента по ключевому атрибуту,
 *                Model $item <== текущий элемент
 *            ):string <== текст подсказки
 *
 * @property-write bool $bootstrapTooltip Использование подсказок bootstrap, если false - то будут использованы нативные браузерные подсказки.
 * @property-write string $tooltipPlacement Позиция появления всплывающей подсказки, см. TP_*-константы. Применяется также и для всплывающей подсказке на аддоне.
 * @property-write string $tooltipTrigger Триггер появления подсказок, см. TT_*-константы. Применяется также и для всплывающей подсказке на аддоне.
 *
 * @property bool|string|callable $addon Элемент, используемый для отображения информации о скрытых значках и разворачивании всего списка. Значения:
 *        false - не будет показан ни в каких случаях,
 *        true - будет показан сгенерированный элемент, отображающий информацию о скрытых значках (при их наличии),
 *        string - будет отображена заданная строка,
 *        callable - коллбек вида
 *            function(
 *                int $visibleBadgesCount, <== количество отображаемых значков,
 *                int $hiddenBadgesCount <== количество скрытых значков
 *            ):string <== текст значка
 *
 * @property array|callable|null $addonOptions HTML-опции аддона. Формат совпадает с $options. Если null - копируется значение из $options.
 * @property callable|false|string $addonTooltip Настройки всплывающей подсказки на аддоне.
 *        false - всплывающая подсказка не используется,
 *        string - текстовая подсказка,
 *        callable - будет вызван коллбек:
 *            function(
 *                string[] $allItems, <== содержимое всех значков (без форматирования)
 *                string[] $visibleElements, <== содержимое видимых значков (без форматирования)
 *                string[] $hiddenRawContents <== содержимое скрытых значков (без форматирования)
 *            ):string <== текст подсказки
 *
 */
class BadgeWidget extends CachedWidget {
	/* Константы позиционирования подсказки */
	public const TP_TOP = 'top';
	public const TP_RIGHT = 'right';
	public const TP_BOTTOM = 'bottom';
	public const TP_LEFT = 'left';
	/* Константы триггеров подсказки */
	public const TT_HOVER = 'hover';
	public const TT_CLICK = 'click';
	public const TT_FOCUS = 'focus';

	/* Тег, используемый для генерации значков */
	private const BADGE_TAG = 'span';
	/* Классы значков (всегда добавляются, независимо от пользовательских классов) */
	private const BADGE_CLASS = ['class' => 'badge'];
	private const ADDON_BADGE_CLASS = ['class' => 'badge addon-badge'];

	public $subItem;
	public $useBadges = true;
	public $itemsSeparator;
	public $emptyText;
	public $iconize = false;
	public $innerPrefix = '';
	public $innerPostfix = '';
	public $outerPrefix = '';
	public $outerPostfix = '';
	public $keyAttribute;
	public $visible = 3;
	public $addon = true;

	public $urlScheme = false;
	public $options = self::BADGE_CLASS;

	public $addonOptions = self::ADDON_BADGE_CLASS;
	public $tooltip = false;
	public $bootstrapTooltip = true;
	public $tooltipPlacement = self::TP_TOP;
	public $tooltipTrigger = self::TT_HOVER;
	public $addonTooltip = false;

	/** @var array */
	private $_items = [];

	/* Необработанные значения атрибутов, нужны для вывода подсказки в тултип на элементе аддона */
	private $_rawResultContents = [];
	/* Вычисленные параметры сопоставлений на каждую итерацию */
	private $_keyAttribute;
	private $_keyValue;

	/**
	 * Функция инициализации и нормализации свойств виджета
	 */
	public function init():void {
		parent::init();
		BadgeWidgetAssets::register($this->getView());
		if ($this->bootstrapTooltip) {
			$this->view->registerJs("$('[data-toggle=\"tooltip\"]').tooltip()");
		}
	}

	/**
	 * @return array
	 */
	public function getItems():array {
		return $this->_items;
	}

	/**
	 * @param mixed $items
	 */
	public function setItems($items):void {
		$this->_items = $items;
		if (is_callable($this->_items)) $this->_items = call_user_func($this->_items);
		if (!is_array($this->_items)) $this->_items = [$this->_items];
	}

	/**
	 * Преобразует каждый перечисляемый объект в модель для внутреннего использования
	 * @param mixed $index
	 * @param mixed $item
	 * @return Model
	 * @throws InvalidConfigException
	 */
	private function prepareItem($index, $item):Model {
		if (is_callable($item)) $item = $item($index);
		if (!is_object($item)) {
			if (!is_scalar($item)) throw new InvalidConfigException("Non-scalar values is unsupported.");
			return new DynamicModel([
				'id' => $index,
				$this->subItem => $item
			]);
		}
		return $item;
	}

	/**
	 * Вытаскивает из подготовленной модели значение для отображения
	 * @param Model $item
	 * @return string
	 * @throws Throwable
	 */
	private function prepareValue(Model $item):string {
		$itemValue = ArrayHelper::getValue($item, $this->subItem);/*Текстовое значение значка*/
		if (null === $this->_keyValue) {
			$this->_rawResultContents[] = $itemValue;
		} else {
			$this->_rawResultContents[$this->_keyValue] = $itemValue;
		}
		$prefix = (is_callable($this->innerPrefix))?call_user_func($this->innerPrefix, $this->_keyValue, $item):$this->innerPrefix;
		$postfix = (is_callable($this->innerPostfix))?call_user_func($this->innerPostfix, $this->_keyValue, $item):$this->innerPostfix;

		return $prefix.$itemValue.$postfix;
	}

	/**
	 * Генерирует единичный значок
	 * @param string $text Содержимое значка
	 * @param array $elementOptions
	 * @return string
	 */
	private function prepareBadge(string $text, array $elementOptions):string {
		if ($this->useBadges) {
			Html::addCssClass($elementOptions, self::BADGE_CLASS);
			return Html::tag(self::BADGE_TAG, $text, $elementOptions);
		}
		return $text;
	}

	/**
	 * Генерирует всё отображение значков, вычисляя видимые/скрытые элементы и добавляя, при необходимости, дополнительный значок.
	 * @param string[] $visibleBadges Массив с содержимым значков, на выходе - массив отображаемых элементов
	 * @param string[] $hiddenBadges Массив скрытых элементов
	 * @throws InvalidConfigException
	 */
	private function prepareBadges(array &$visibleBadges, array &$hiddenBadges = []):void {
		if (true === $this->visible) return;/*Если отображаются все значки, обработка не требуется*/
		if (false === $this->visible) {/*Если не отображается ни одного значка*/
			$hiddenBadges = $visibleBadges;
			$visibleBadges = [];
			return;
		}
		if (is_int($this->visible)) {
			if (count($visibleBadges) > $this->visible) {
				$visibleArray = array_slice($visibleBadges, 0, $this->visible, true);
				$hiddenBadges = array_diff_key($visibleBadges, $visibleArray);
				$visibleBadges = $visibleArray;
				return;
			}
			return;
		}
		if (is_callable($this->visible)) {
			$visibleArray = [];
			foreach ($visibleBadges as $itemKey => $itemValue) {
				if (true === call_user_func($this->visible, $itemKey, $itemValue)) {
					$visibleArray[$itemKey] = $itemValue;
				} else {
					$hiddenBadges[$itemKey] = $itemValue;
				}
			}
			$visibleBadges = $visibleArray;
			return;

		}
		throw new InvalidConfigException('Wrong type for "visible" parameter.');
	}

	/**
	 * @param int $visibleElementsCount
	 * @param int $hiddenElementsCount
	 * @return string
	 * @throws InvalidConfigException
	 * @throws Throwable
	 */
	private function prepareAddonBadge(int $visibleElementsCount, int $hiddenElementsCount):string {
		if (true === $this->addon) {
			$addonText = "...ещё {$hiddenElementsCount}";
		} elseif (is_string($this->addon)) {
			$addonText = $this->addon;
		} elseif (is_callable($this->addon)) {
			$addonText = call_user_func($this->addon, $visibleElementsCount, $hiddenElementsCount);
		} elseif (false !== $this->addon) {
			throw new InvalidConfigException('Wrong type for "addon" parameter.');
		} else {
			return '';
		}
		$item = $this->prepareItem(-1, $addonText);
		$addonOptions = $this->addonOptions??$this->options;
		$addonOptions = is_callable($addonOptions)?$addonOptions($this->_keyValue, $item):$addonOptions;
		$addonOptions = $this->addTooltipToOptions($addonOptions, $this->prepareAddonTooltipText());
		Html::addCssClass($addonOptions, self::ADDON_BADGE_CLASS);
		return Html::tag(self::BADGE_TAG, $addonText, $addonOptions);
	}

	/**
	 * Вычисляет атрибут сопоставления
	 * @param Model $item
	 * @return null|string
	 */
	private function prepareKeyAttribute(Model $item):?string {
		if (null === $this->keyAttribute) {
			/*assume ActiveRecord*/
			if ($item->hasProperty('primaryKey')) return 'primaryKey';
			/*assume generated DynamicModel*/
			if ($item->hasProperty('id')) return 'id'; /*todo: запоминать преобразование в PrepareItem для ускорения проверки*/
//			throw new InvalidConfigException('"keyAttribute" parameter required.');
		}
		return $this->keyAttribute;
	}

	/**
	 * Добавляет в набор HTML-параметров данные для генерации подсказки
	 */
	private function addTooltipToOptions(array $itemOptions, ?string $tooltipText = null):array {
		if (null === $tooltipText) return $itemOptions;
		$tooltipOptions = $this->bootstrapTooltip?[
			'class' => 'add-tooltip',
			'data-toggle' => 'tooltip',
			'data-trigger' => $this->tooltipTrigger,
			'data-original-title' => $tooltipText,
			'title' => $tooltipText,
			'data-placement' => $this->tooltipPlacement
		]:[
			'title' => $tooltipText
		];

		return ArrayHelper::mergeImplode(' ', $itemOptions, $tooltipOptions);
	}

	/**
	 * @param Model $item
	 * @return string|null
	 * @throws Throwable
	 */
	private function prepareTooltipText(Model $item):?string {
		if (false === $this->tooltip) return null;
		$tooltip = $this->tooltip;
		if (is_callable($tooltip)) {
			$tooltip = $tooltip($this->_keyValue, $item);
		} elseif (is_array($tooltip)) {
			$tooltip = ArrayHelper::getValue($tooltip, $this->_keyValue);
		}
		return $tooltip;
	}

	/**
	 * @return string|null
	 * @throws InvalidConfigException
	 */
	private function prepareAddonTooltipText():?string {
		if (false === $this->addonTooltip) return null;
		if (is_callable($this->addonTooltip)) {
			$visibleRawContents = $this->_rawResultContents;
			$hiddenRawContents = [];
			$this->prepareBadges($visibleRawContents, $hiddenRawContents);
			return call_user_func($this->addonTooltip, $this->_rawResultContents, $visibleRawContents, $hiddenRawContents);
		}
		return $this->addonTooltip;
	}

	/**
	 * @param Model $item
	 * @param string $content
	 * @return string
	 * @throws Throwable
	 */
	private function prepareUrl(Model $item, string $content):string {
		if (false === $this->urlScheme) return $content;
		$arrayedParameters = [];
		$currentLinkScheme = $this->urlScheme;
		array_walk($currentLinkScheme, static function(&$value, $key) use ($item, &$arrayedParameters) {//подстановка в схему значений из модели
			if (is_array($value)) {//value passed as SomeParameter => [a, b, c,...] => convert to SomeParameter[1] => a, SomeParameter[2] => b, SomeParameter[3] => c
				foreach ($value as $index => $item) {
					$arrayedParameters["{$key}[{$index}]"] = $item;
				}
			} elseif ($item->hasProperty($value) && false !== $attributeValue = ArrayHelper::getValue($item, $value, false)) $value = $attributeValue;

		});
		if ([] !== $arrayedParameters) $currentLinkScheme = array_merge(...$arrayedParameters);//если в схеме были переданы значения массивом, включаем их разбор в схему
		return Html::a($content, $currentLinkScheme);
	}

	/**
	 * Функция возврата результата рендеринга виджета
	 * @return string
	 * @throws Throwable
	 */
	public function run():string {
		$badges = [];

		/**
		 * Из переданных данных собираем весь массив отображаемых значков, с полным форматированием.
		 * Это нужно потому, что:
		 *    1) отображение может быть свёрнуто на лету без подгрузок.
		 *    2) невидимые значения могут быть видны в подсказках
		 */
		foreach ($this->items as $index => $item) {
			if (null === $item) continue;

			$item = $this->prepareItem($index, $item);
			$this->_keyAttribute = $this->prepareKeyAttribute($item);
			$this->_keyValue = null === $this->_keyAttribute?null:$item->{$this->_keyAttribute};
			$itemValue = $this->prepareValue($item);

			if ($this->iconize) $itemValue = Utils::ShortifyString($itemValue);
			/*Добавление ссылки к элементу*/
			$itemValue = $this->prepareUrl($item, $itemValue);
			$itemOptions = is_callable($this->options)?call_user_func($this->options, $this->_keyValue, $item):$this->options;

			$itemOptions = $this->addTooltipToOptions($itemOptions, $this->prepareTooltipText($item));
			$badges[$item->{$mapAttribute}] = $this->prepareBadge($itemValue, $itemOptions);
		}
		/*Из переданных данных не удалось собрать массив, показываем выбранную заглушку*/
		if ([] === $badges && null !== $this->emptyText) {
			return self::widget([
				'items' => $this->emptyText,
				'iconize' => $this->iconize,
				'innerPrefix' => $this->innerPrefix,
				'innerPostfix' => $this->innerPostfix,
				'outerPrefix' => $this->outerPrefix,
				'outerPostfix' => $this->outerPostfix,
				'options' => $this->options,
				'urlScheme' => $this->urlScheme,
				'tooltip' => $this->tooltip
			]);
		}
		if ($this->useBadges) {
			$hiddenBadges = [];
			$this->prepareBadges($badges, $hiddenBadges);
			if ([] !== $hiddenBadges) {
				$badges[] = $this->prepareAddonBadge(count($badges), count($hiddenBadges));
			}
		}

		return implode($this->itemsSeparator??'', $badges);

	}

}
