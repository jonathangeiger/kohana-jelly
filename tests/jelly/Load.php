<?php

/**
 * Tests the Arr lib that's shipped with kohana
 *
 * @group jelly
 */
Class Jelly_Load extends PHPUnit_Framework_TestCase
{
	public function providerLoad()
	{
		return array(
			array(jelly::factory('load')->load(1), 1, 'One', 'alias'), // Normal syntax
			array(jelly::factory('load')->where('id', '=', 2)->limit(1, TRUE)->load(), 2, 'Two', 'aliased'), // Alternate Load Syntax
		);
	}

	/**
	 * @dataProvider providerLoad
	 */
	public function testLoad($model, $id, $name, $alias)
	{
		$this->assertEquals(TRUE, $model->loaded());
		$this->assertEquals($id, $model->id);
		$this->assertEquals($name, $model->name);
		$this->assertEquals($alias, $model->alias);
	}
	
	public function providerMany()
	{
		return array(
			array(jelly::factory('load')->limit(3)->load(), 3, 'model_load'), // Normal syntax
		);
	}

	/**
	 * @dataProvider providerMany
	 */
	public function testMany($model, $rows, $class)
	{
		$this->assertEquals($rows, count($model));
		
		foreach($model as $row)
		{
			$this->assertEquals($class, strtolower(get_class($row)));
			$this->assertEquals(TRUE, $row->loaded());
		}
	}
	
	public function providerInvalidLoad()
	{
		return array(
			array(jelly::factory('load')->load(9000)), // Invalid record
			array(jelly::factory('load')->where('id', '=', 9000)->limit(1, TRUE)->load()), // Invalid record
		);
	}
	
	/**
	 * @dataProvider providerLoad
	 */
	public function testInvalidLoad($model)
	{
		$this->assertEquals(FALSE, $model->loaded);
	}
}