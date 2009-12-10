<?php

/**
 * Tests the Arr lib that's shipped with kohana
 *
 * @group jelly
 */
Class Jelly_BasicDataTypes extends PHPUnit_Framework_TestCase
{
	public function providerBasicTypes()
	{
		$boolean = new Jelly_Field_Boolean;
		$string = new Jelly_Field_String;
		$integer = new Jelly_Field_Integer;
		
		return array(
			array($boolean, 1, TRUE),
			array($boolean, '1', TRUE),
			array($boolean, 'string', TRUE),
			array($boolean, 0, FALSE),
			array($boolean, '0', FALSE),
			array($boolean, '', FALSE),
			array($boolean, FALSE, FALSE),
			
			array($string, 1, '1'),
			array($string, FALSE, ''),
			array($string, TRUE, '1'),
			array($string, NULL, ''),
			array($string, 'Hello, World', 'Hello, World'),
			
			array($integer, '1', 1),
			array($integer, FALSE, 0),
			array($integer, TRUE, 1),
			array($integer, 200.8, 200),
			array($integer, 'Hello, World', 0),
		);
	}

	/**
	 * @dataProvider providerBasicTypes
	 */
	public function testBasicTypes($field, $input, $expected)
	{
		$field->set($input);
		$this->assertEquals($expected, $field->get());
	}
}