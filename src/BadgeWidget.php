<?php
declare(strict_types = 1);

namespace pozitronik\widgets;

use pozitronik\helpers\Utils;
use pozitronik\helpers\ArrayHelper;
use pozitronik\helpers\ReflectionHelper;
use Throwable;
use yii\base\DynamicModel;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\Html;

/**
 * Class BadgeWidget
 * @property-write string|array|object|callable $items Данные для обработки (строка, массив, модель, замыкание). Преобразуются в массив при обработке.
 * @property-write string $subItem Отображаемый ключ (строка => null, массив => key, модель => атрибут/свойство/переменная, замыкание => параметр). Виджет пытается просчитать его автоматически.
 * @property-write bool $useBadges Включает/отключает генерацию значков.
 * @property-write string|null $itemsSeparator Строка-разделитель между элементами. null - не использовать разделитель.
 * @property-write string|null $emptyText Текст иконки, подставляемой при отсутствии обрабатываемых данных. null - не подставлять текст
 * @property-write bool $iconize Содержимое бейджа сокращается до псевдоиконки.
 *
 * @property-write string|callable $innerPrefix Строка, добавляемая перед текстом внутри значка. Если передано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть строку для этого элемента.
 * @property-write string|callable $innerPostfix Строка, добавляемая после текста внутри значка. Если передано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть строку для этого элемента.
 * @property-write string|callable $outerPrefix Строка, добавляемая перед текстом снаружи значка. Если передано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть строку для этого элемента.
 * @property-write string|callable $outerPostfix Строка, добавляемая перед текстом внутри значка. Если передано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть строку для этого элемента.
 * @property-write string $allPrefix Строка, добавляемая перед всем массивом значков.
 *
 * @property-write null|string $mapAttribute Атрибут, значение которого будет использоваться как ключевое при сопоставлении элементов с массивами параметров. Если не задан, виджет попытается вычислить его самостоятельно, взяв ключевой атрибут для ActiveRecord или ключ для элемента массива.
 *
 * @property-write bool|int|callable $visible Параметр, определяющий, какие элементы будут отображены.
 *        true - будут отображены все элементы,
 *        false - будут скрыты все элементы (предполагается взаимодействие через $addon)
 *        int - будет отображено указанное число первых элементов,
 *        callable - будет вызвана функция, в которой параметром будет передан ключ элемента (если есть). Логический результат выполнения этой функции определяет отображение элемента.
 *
 * @property-write array|callable $options HTML-опции для каждого значка по умолчанию. Если передано замыканием, то функция получает на вход ключ элемента (если есть), и должна вернуть массив опций для этого элемента.
 * @property-write array|false $urlScheme Схема подстановки значений атрибутов элемента в генерируемую ссылку, например:
 *        $item = {"key" => 1, "value" => 100, "property" => "propertyData", "arrayParameter" => ["a" => 10, "b" => 20, "c" => 30]]}
 *        UrlOptions->scheme = ['site/index', 'id' => 'value', 'param1' => 'property', 'param2' => 'non-property', 'param3' => 'arrayParameter']
 * Получим набор параметров ссылки для элемента:
 *        ['site/index', 'id' => 100, 'param1' => 'propertyData', 'param2' => 'non-property', 'param3[a]' => 10, 'param3[b]' => 20, 'param3[c]' => 30]
 * false - элементы не превращаются в ссылки.
 *
 * @property-write array|false $tooltip Настройки для всплывающей подсказки. false - всплывающая подсказка не используется.
 *
 * @property bool|string|callable $addon Элемент, используемый для отображения информации о скрытых значках и разворачивании всего списка. Значения:
 *        false - не будет показан ни в каких случаях,
 *        true - будет показан сгенерированный элемент, отображающий информацию о скрытых значках (при их наличии),
 *        string - будет отображена заданная строка,
 *        callable - будет вызвана функция, получающая на вход первым параметром количество видимых элементов, вторым параметром количество скрытых элементов, которая должна вернуть строку с содержимым.
 * @property array|callable|null $addonOptions HTML-опции аддона. Если null - копируется из $options
 * @property array|false $addonTooltipOptions Настройки всплывающей подсказки на элементе, см. BadgeWidget::$tooltipOptions
 */
class BadgeWidget extends CachedWidget {
	public const TP_TOP = 'top';
	public const TP_RIGHT = 'right';
	public const TP_BOTTOM = 'bottom';
	public const TP_LEFT = 'left';

	/* Тег, используемый для генерации значков */
	private const BADGE_TAG = 'span';

	public $subItem;
	public $useBadges = true;
	public $itemsSeparator = false;
	public $emptyText;
	public $iconize = false;
	public $innerPrefix = '';
	public $innerPostfix = '';
	public $outerPrefix = '';
	public $outerPostfix = '';
	public $allPrefix = '';
	public $mapAttribute;
	public $visible = 3;
	public $addon = true;

	public $options = ['class' => 'badge'];
	public $addonOptions = ['class' => 'badge addon-badge'];

	/*todo*/
	public $tooltip = false;
	public $tooltipPlacement = self::TP_TOP;
	public $urlScheme = false;

	/** @var array */
	private $_items = [];

	/* Необработанные значения атрибутов, нужны для вывода подсказки в тултип */
	private $_rawResultContents = [];

	/**
	 * Функция инициализации и нормализации свойств виджета
	 */
	public function init():void {
		parent::init();
		BadgeWidgetAssets::register($this->getView());
		//if (null === $this->subItem) throw new InvalidConfigException('"subItem" parameter required');
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
	 * Преобразует каждый перечисляемый объект в модель для внутреннего использования
	 * @param null|int $index
	 * @param $item
	 * @return Model
	 */
	private function prepareItem(?int $index, $item):Model {
		if (!is_object($item)) {
			if (is_array($item)) {
				return new DynamicModel($item);
			}
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
	 * @param string $mapAttribute
	 * @return string
	 * @throws Throwable
	 */
	private function prepareValue(Model $item, string $mapAttribute):string {
		$itemValue = ArrayHelper::getValue($item, $this->subItem);/*Текстовое значение значка*/
		$this->_rawResultContents[] = $itemValue;
		$prefix = (is_callable($this->innerPrefix))?call_user_func($this->innerPrefix, $mapAttribute):$this->innerPrefix;
		$postfix = (is_callable($this->innerPostfix))?call_user_func($this->innerPostfix, $mapAttribute):$this->innerPostfix;

		return $prefix.$itemValue.$postfix;
	}

	/**
	 * Возвращает набор параметров для конкретного элемента.
	 * @param Model $item
	 * @param string $mapAttribute
	 * @return array
	 * @throws Throwable
	 */
	public function prepareItemOption(Model $item, string $mapAttribute):array {
		return (is_callable($this->options))?call_user_func($this->options, $item->{$mapAttribute}):$this->options;
	}

	/**
	 * Генерирует единичный значок
	 * @param string $text Содержимое значка
	 * @param array $elementOptions
	 * @return string
	 */
	private function prepareBadge(string $text, array $elementOptions):string {
		return $this->useBadges?Html::tag(self::BADGE_TAG, $text, array_merge(['class' => 'badge'], $elementOptions)):$text;
	}

	/**
	 * Генерирует всё отображение значков, вычисляя видимые/скрытые элементы и добавляя, при необходимости, дополнительный значок.
	 * @param string[] $visibleBadges Массив с содержимым значков, на выходе - массив отображаемых элементов
	 * @param string[] $hiddenBadges Массив скрытых элементов
	 * @throws InvalidConfigException
	 */
	public function prepareBadges(array &$visibleBadges, array &$hiddenBadges = []):void {
		if (true === $this->visible) return;/*Если отображаются все значки, обработка не требуется*/
		if (false === $this->visible) {/*Если не отображается ни одного значка*/
			$hiddenBadges = $visibleBadges;
			$visibleBadges = [];
			return;
		}
		$itemsCount = count($visibleBadges);
		if (is_int($this->visible)) {
			if ($itemsCount > $this->visible) {
				$visibleArray = $visibleBadges;
				array_splice($visibleArray, $this->visible, $itemsCount);
				$hiddenBadges = array_diff_key($visibleBadges, $visibleArray);
				$visibleBadges = $visibleArray;
				return;
			}
			return;
		}
		if (is_callable($this->visible)) {
			$visibleArray = [];
			foreach ($visibleBadges as $itemKey => $itemValue) {
				if (true === call_user_func($this->visible, $itemKey)) {
					$visibleArray[$itemKey] = $itemValue;
				} else {
					$hiddenBadges[$itemKey] = $itemValue;
				}
			}
			$visibleBadges = $visibleArray;
			return;

		}
		throw new InvalidConfigException('Wrong type for "visible" parameter');
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
		} else {
			throw new InvalidConfigException('Wrong type for "addon" parameter');
		}
		if (null === $this->addonOptions) $this->addonOptions = $this->options;
		$addonOptions = $this->prepareItemOption($this->prepareItem(-1, $addonText), 'id');

		return Html::tag(self::BADGE_TAG, $addonText, array_merge(['class' => 'badge badge-addon'], $addonOptions));
	}

	/**
	 * Вычисляет атрибут сопоставления
	 * @param Model $item
	 * @return string
	 */
	public function prepareMapAttribute(Model $item):string {
		if (null === $this->mapAttribute) {
			if ($item->hasProperty('primaryKey')) {/*assume ActiveRecord*/
				return 'primaryKey';
			}
			if ($item->hasProperty('id')) {/*assume generated DynamicModel*/
				return 'id'; /*todo: запоминать преобразование в PrepareItem для ускорения проверки*/
			}
		}
		return $this->mapAttribute;
	}

	//todo
	public function prepareTooltip(Model $item, array $itemOptions, array $rawResultsContents/*todo*/):array {
		return ArrayHelper::mergeImplode(' ', $itemOptions, [
			'class' => 'add-tooltip',
			'data-toggle' => 'tooltip',
			'data-original-title' => (is_callable($this->tooltip))?call_user_func($this->tooltip, $item):$this->tooltip,
			'data-placement' => $this->tooltipPlacement
		]);
	}

	/**
	 * @param Model $item
	 * @param string $content
	 * @return string
	 * @throws Throwable
	 */
	public function prepareUrl(Model $item, string $content):string {
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
			$mapAttribute = $this->prepareMapAttribute($item);
			$itemValue = $this->prepareValue($item, $mapAttribute);



			if ($this->iconize) $itemValue = Utils::ShortifyString($itemValue);
			/*Добавление ссылки к элементу*/
//			$itemValue = $this->_urlOptions->prepareUrl($item, $itemValue);

			$itemOptions = $this->prepareItemOption($item, $mapAttribute);
			$badges[$item->{$mapAttribute}] = $this->prepareBadge($itemValue, $itemOptions);

//			$itemOptions = $this->_tooltipOptions->prepareTooltip($item, $itemOptions, $this->_rawResultContents);

		}
		/*Из переданных данных не удалось собрать массив, показываем выбранную заглушку*/
		if ([] === $badges && null !== $this->emptyText) {
			return self::widget([
				'items' => $this->emptyText,//todo: проверить, что будет при $emptyText массивом или замыканием
				'iconize' => $this->iconize,
				'innerPrefix' => $this->innerPrefix,
				'innerPostfix' => $this->innerPostfix,
				'outerPrefix' => $this->outerPrefix,
				'outerPostfix' => $this->outerPostfix,
				'allPrefix' => $this->allPrefix,
				'options' => $this->options,
//				'urlOptions' => $this->urlOptions,
//				'tooltipOptions' => $this->tooltipOptions
			]);
		}
		if ($this->useBadges) {
			$hiddenBadges = [];
			$this->prepareBadges($badges, $hiddenBadges);
			if ([] !== $hiddenBadges) {
				$badges[] = $this->prepareAddonBadge(count($badges), count($hiddenBadges));
			}
		}

		return $this->allPrefix.implode($this->itemsSeparator??'', $badges);

	}

}
