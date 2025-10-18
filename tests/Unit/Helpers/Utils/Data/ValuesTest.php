<?php

/**
 * This file is part of the Peku Framework.
 *
 * @author    Patricio Rossi <meduzapat@netscape.net>
 * @copyright Copyright (c) 2025 Patricio Rossi
 * @license   MIT License - see LICENSE file for details
 * @link      https://github.com/meduzapat/peku
 */

declare(strict_types=1);

namespace Peku\Tests\Unit\Helpers\Utils\Data;

use PHPUnit\Framework\TestCase;
use Peku\Helpers\Utils\Data\Values;

/**
 * Unit tests for Values utility class
 */
class ValuesTest extends TestCase {

	// ========================================================================
	// cast() Method Tests - Array Defaults
	// ========================================================================

	/**
	 * @dataProvider arrayCastProvider
	 */
	public function testCastWithArrayDefault(string $value, array $default, array $expected): void {
		$result = Values::cast($value, $default);
		$this->assertSame($expected, $result);
	}

	public static function arrayCastProvider(): array {
		return [
			'json object'        => ['{"name":"test","age":30}',    [],                   ['name' => 'test', 'age' => 30]],
			'json array'         => ['["one","two","three"]',       [],                   ['one', 'two', 'three']],
			'serialized data'    => [serialize(['key' => 'value']), [],                   ['key' => 'value']],
			'invalid json'       => ['{invalid json}',              ['fallback' => true], ['fallback' => true]],
			'invalid serialized' => ['not serialized data',         ['default' => 'val'], ['default' => 'val']],
		];
	}

	// ========================================================================
	// cast() Method Tests - Boolean Defaults
	// ========================================================================

	/**
	 * @dataProvider booleanStringProvider
	 */
	public function testCastWithBooleanDefault(string $value, bool $expected): void {
		$result = Values::cast($value, false);
		$this->assertSame($expected, $result);
	}

	public static function booleanStringProvider(): array {
		return [
			'true string'  => ['true',  true],
			'false string' => ['false', false],
			'1 string'     => ['1',     true],
			'0 string'     => ['0',     false],
			'yes string'   => ['yes',   true],
			'no string'    => ['no',    false],
			'on string'    => ['on',    true],
			'off string'   => ['off',   false],
			'empty string' => ['',      false],
		];
	}

	public function testCastWithBooleanDefaultInvalidValue(): void {
		$value   = 'invalid';
		$default = true;
		$result  = Values::cast($value, $default);

		$this->assertSame($default, $result);
	}

	// ========================================================================
	// cast() Method Tests - Integer Defaults
	// ========================================================================

	/**
	 * @dataProvider integerCastProvider
	 */
	public function testCastWithIntegerDefault(string $value, int $default, int $expected): void {
		$result = Values::cast($value, $default);
		$this->assertSame($expected, $result);
	}

	public static function integerCastProvider(): array {
		return [
			'positive integer'       => ['42',            0,   42],
			'negative integer'       => ['-100',          0,   -100],
			'zero'                   => ['0',             999, 0],
			'invalid string'         => ['not a number',  123, 123],
			'float string fails int' => ['3.14',          0,   0],
		];
	}

	// ========================================================================
	// cast() Method Tests - Float Defaults
	// ========================================================================

	/**
	 * @dataProvider floatCastProvider
	 */
	public function testCastWithFloatDefault(string $value, float $default, float $expected): void {
		$result = Values::cast($value, $default);
		$this->assertSame($expected, $result);
	}

	public static function floatCastProvider(): array {
		return [
			'positive float'      => ['3.14',       0.0,  3.14],
			'negative float'      => ['-2.5',       0.0,  -2.5],
			'integer to float'    => ['42',         0.0,  42.0],
			'scientific notation' => ['1.23e-4',   0.0,  0.000123],
			'invalid string'      => ['not a float', 9.99, 9.99],
		];
	}

	// ========================================================================
	// cast() Method Tests - String Defaults
	// ========================================================================

	/**
	 * @dataProvider stringCastProvider
	 */
	public function testCastWithStringDefault(string $value, string $default, string $expected): void {
		$result = Values::cast($value, $default);
		$this->assertSame($expected, $result);
	}

	public static function stringCastProvider(): array {
		return [
			'non-empty string' => ['test string', '',         'test string'],
			'empty value'      => ['',            'fallback', 'fallback'],
			'numeric string'   => ['123',         'default',  '123'],
		];
	}

	// ========================================================================
	// cast() Method Tests - Other Type Defaults
	// ========================================================================

	public function testCastWithNullDefault(): void {
		$result = Values::cast('any value', null);
		$this->assertNull($result);
	}

	public function testCastWithObjectDefault(): void {
		$default = new \stdClass();
		$result  = Values::cast('any value', $default);

		$this->assertSame($default, $result);
	}

	// ========================================================================
	// inferType() Method Tests - JSON Detection
	// ========================================================================

	/**
	 * @dataProvider jsonInferProvider
	 */
	public function testInferTypeFromJson(string $value, array $expected): void {
		$result = Values::inferType($value);
		$this->assertSame($expected, $result);
	}

	public static function jsonInferProvider(): array {
		return [
			'json object' => [
				'{"name":"test","age":30}',
				['name' => 'test', 'age' => 30]
			],
			'json array' => [
				'["one","two","three"]',
				['one', 'two', 'three']
			],
			'nested structure' => [
				'{"outer":{"inner":"value"},"list":[1,2,3]}',
				['outer' => ['inner' => 'value'], 'list' => [1, 2, 3]]
			],
		];
	}

	// ========================================================================
	// inferType() Method Tests - Serialized Detection
	// ========================================================================

	public function testInferTypeFromSerializedArray(): void {
		$value  = serialize(['key' => 'value', 'number' => 123]);
		$result = Values::inferType($value);

		$this->assertIsArray($result);
		$this->assertSame(['key' => 'value', 'number' => 123], $result);
	}

	// ========================================================================
	// inferType() Method Tests - Float Detection
	// ========================================================================

	/**
	 * @dataProvider floatInferProvider
	 */
	public function testInferTypeFromFloat(string $value, float $expected): void {
		$result = Values::inferType($value);
		$this->assertSame($expected, $result);
	}

	public static function floatInferProvider(): array {
		return [
			'positive float'      => ['3.14',     3.14],
			'negative float'      => ['-2.5',     -2.5],
			'scientific notation' => ['1.23e-4',  0.000123],
		];
	}

	// ========================================================================
	// inferType() Method Tests - Integer Detection
	// ========================================================================

	/**
	 * @dataProvider integerInferProvider
	 */
	public function testInferTypeFromInteger(string $value, int $expected): void {
		$result = Values::inferType($value);
		$this->assertSame($expected, $result);
	}

	public static function integerInferProvider(): array {
		return [
			'positive integer' => ['42',   42],
			'negative integer' => ['-100', -100],
			'zero'             => ['0',    0],
		];
	}

	// ========================================================================
	// inferType() Method Tests - Boolean Detection
	// ========================================================================

	/**
	 * @dataProvider booleanInferProvider
	 */
	public function testInferTypeFromBoolean(string $value, bool $expected): void {
		$result = Values::inferType($value);
		$this->assertSame($expected, $result);
	}

	public static function booleanInferProvider(): array {
		// Note: '0' and '1' are caught by integer detection, not boolean
		return [
			'true string'  => ['true',  true],
			'false string' => ['false', false],
			'yes string'   => ['yes',   true],
			'no string'    => ['no',    false],
			'on string'    => ['on',    true],
			'off string'   => ['off',   false],
		];
	}

	// ========================================================================
	// inferType() Method Tests - String Fallback
	// ========================================================================

	/**
	 * @dataProvider stringInferProvider
	 */
	public function testInferTypeFromString(string $value, string $expected): void {
		$result = Values::inferType($value);
		$this->assertSame($expected, $result);
	}

	public static function stringInferProvider(): array {
		return [
			'plain string' => ['just a string', 'just a string'],
			'empty string' => ['',              ''],
			'invalid json' => ['{invalid json}', '{invalid json}'],
		];
	}

	// ========================================================================
	// inferType() Method Tests - Priority Order
	// ========================================================================

	public function testInferTypePrioritizesJsonOverOtherTypes(): void {
		// String "123" should be detected as JSON array first
		$value  = '["123"]';
		$result = Values::inferType($value);

		$this->assertIsArray($result);
		$this->assertSame(['123'], $result);
	}

	public function testInferTypePrioritizesFloatOverInt(): void {
		// Value that looks like both float and int
		$result = Values::inferType('3.0');
		$this->assertSame(3.0, $result);
		$this->assertIsFloat($result);
	}

	// ========================================================================
	// toArray() Method Tests - Success Cases
	// ========================================================================

	public function testToArrayFromJson(): void {
		$value  = '{"key":"value","num":42}';
		$result = Values::toArray($value);

		$this->assertIsArray($result);
		$this->assertSame(['key' => 'value', 'num' => 42], $result);
	}

	public function testToArrayFromJsonArray(): void {
		$value  = '["a","b","c"]';
		$result = Values::toArray($value);

		$this->assertIsArray($result);
		$this->assertSame(['a', 'b', 'c'], $result);
	}

	public function testToArrayFromSerializedData(): void {
		$value  = serialize(['test' => 'data', 'number' => 123]);
		$result = Values::toArray($value);

		$this->assertIsArray($result);
		$this->assertSame(['test' => 'data', 'number' => 123], $result);
	}

	public function testToArrayFromEmptyJson(): void {
		$value  = '{}';
		$result = Values::toArray($value);

		$this->assertSame([], $result);
	}

	// ========================================================================
	// toArray() Method Tests - Fallback Cases
	// ========================================================================

	public function testToArrayReturnsDefaultOnInvalidJson(): void {
		$value   = '{invalid json}';
		$default = ['fallback' => true];
		$result  = Values::toArray($value, $default);

		$this->assertSame($default, $result);
	}

	public function testToArrayReturnsDefaultOnInvalidSerialize(): void {
		$value   = 'not serialized';
		$default = ['default' => 'array'];
		$result  = Values::toArray($value, $default);

		$this->assertSame($default, $result);
	}

	public function testToArrayReturnsEmptyArrayByDefault(): void {
		$value  = 'random string';
		$result = Values::toArray($value);

		$this->assertSame([], $result);
	}

	public function testToArrayWithEmptyStringReturnsDefault(): void {
		$value   = '';
		$default = ['empty' => 'fallback'];
		$result  = Values::toArray($value, $default);

		$this->assertSame($default, $result);
	}

	// ========================================================================
	// toArray() Method Tests - JSON vs Serialize Priority
	// ========================================================================

	public function testToArrayPrioritizesJsonOverSerialize(): void {
		// Create a value that's valid JSON and would also unserialize
		$value  = '["json","array"]';
		$result = Values::toArray($value);

		$this->assertSame(['json', 'array'], $result);
	}

	public function testToArrayFallsBackToSerializeWhenJsonInvalid(): void {
		$original = ['serialized' => 'data'];
		$value    = serialize($original);
		$result   = Values::toArray($value);

		$this->assertSame($original, $result);
	}

	// ========================================================================
	// Edge Cases & Special Values
	// ========================================================================

	public function testCastWithNumericStringZero(): void {
		// Test that "0" is properly handled (not treated as empty)
		$result = Values::cast('0', 'default');
		$this->assertSame('0', $result);
	}

	public function testInferTypeWithWhitespace(): void {
		$result = Values::inferType('  42  ');
		// Should still detect as integer
		$this->assertSame(42, $result);
	}

	public function testToArrayWithJsonNull(): void {
		// JSON null should not be converted to array
		$value  = 'null';
		$result = Values::toArray($value);

		$this->assertSame([], $result);
	}

	public function testCastWithComplexNestedArray(): void {
		$json   = '{"level1":{"level2":{"level3":["deep","array"]}}}';
		$result = Values::cast($json, []);

		$this->assertIsArray($result);
		$this->assertSame([
			'level1' => [
				'level2' => [
					'level3' => ['deep', 'array']
				]
			]
		], $result);
	}
}