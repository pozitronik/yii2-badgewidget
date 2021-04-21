<?php
declare(strict_types = 1);

namespace pozitronik\widgets;

use pozitronik\helpers\ArrayHelper;
use Throwable;
use yii\base\Model;
use yii\helpers\Html;

/**
 * Class UrlOptions
 * @property string|array $url Базовый адрес ссылки, без параметров. Если не задан, то используется первый элемент в $schema.
 * @property array $scheme Схема подстановки значений атрибутов элемента в генерируемую ссылку, например:
 *        $item = {"key" => 1, "value" => 100, "property" => "propertyData", "arrayParameter" => ["a" => 10, "b" => 20, "c" => 30]]}
 *        UrlOptions->scheme = ['id' => 'value', 'param1' => 'property', 'param2' => 'non-property', 'param3' => 'arrayParameter']
 * Получим набор параметров ссылки для элемента:
 *        ['id' => 100, 'param1' => 'propertyData', 'param2' => 'non-property', 'param3[a]' => 10, 'param3[b]' => 20, 'param3[c]' => 30]
 */
class UrlOptions extends Model {

	public $scheme = [];

	/**
	 * @param Model $item
	 * @param string $content
	 * @return string
	 * @throws Throwable
	 */
	public function prepareUrl(Model $item, string $content):string {
		$arrayedParameters = [];
		$currentLinkScheme = $this->scheme;
		array_walk($currentLinkScheme, static function(&$value, $key) use ($item, &$arrayedParameters) {//подстановка в схему значений из модели
			if (is_array($value)) {//value passed as SomeParameter => [a, b, c,...] => convert to SomeParameter[1] => a, SomeParameter[2] => b, SomeParameter[3] => c
				foreach ($value as $index => $item) {
					$arrayedParameters["{$key}[{$index}]"] = $item;
				}
			} elseif ($item->hasProperty($value) && false !== $attributeValue = ArrayHelper::getValue($item, $value, false)) $value = $attributeValue;

		});
		if ([] !== $arrayedParameters) $currentLinkScheme = array_merge(...$arrayedParameters);//если в схеме были переданы значения массивом, включаем их разбор в схему
		if (null === $this->url) array_unshift($currentLinkScheme, $this->url);
		return Html::a($content, $currentLinkScheme);
	}
}