<?php

/**
 * Tests the Arr lib that's shipped with kohana
 *
 * @group jelly
 */
Class Jelly_BelongsTo extends PHPUnit_Framework_TestCase
{
	public function testLoad()
	{
		$model = Jelly::factory('post')->load(1);
		$this->assertEquals(1, $model->category->load()->id);
		$this->assertEquals(TRUE, $model->category->load()->loaded());
		
		// Changes in code should load a new model
		$model = Jelly::factory('post')->load(1);
		$model->category = Jelly::factory('category')->load(2);
		$this->assertEquals(2, $model->category->load()->id);
		$this->assertEquals(TRUE, $model->category->load()->loaded());
		
		// Loading a non-existent model should work transparently
		$model = Jelly::factory('post')->load(1);
		$model->category = Jelly::factory('category')->load(3);
		$this->assertEquals(NULL, $model->category->load()->id);
		$this->assertEquals(FALSE, $model->category->load()->loaded());
	}
	
	public function testCreate()
	{
		$model = Jelly::factory('post');
		$model->name = rand();
		$model->category = 1;
		$model->save();
				
		$this->assertEquals($model, Jelly::factory('post')->load($model->id));
	}
	
	public function testUpdate()
	{
		// Changes in code should load a new model
		$model = Jelly::factory('post')->load(1);
		$model->category = Jelly::factory('category')->load(2);
		$model->save();
		
		$this->assertEquals(2, $model->category->load()->id);
		$this->assertEquals(TRUE, $model->category->load()->loaded());
		
		// Loading a non-existent model should work transparently
		$model = Jelly::factory('post')->load(1);
		$model->category = Jelly::factory('category')->load(3);
		$model->save();
		
		$this->assertEquals(NULL, $model->category->load()->id);
		$this->assertEquals(FALSE, $model->category->load()->loaded());
		
		// Revert back to the old model
		$model = Jelly::factory('post')->load(1);
		$model->category = Jelly::factory('category')->load(1);
		$model->save();
		
		$this->assertEquals(1, $model->category->load()->id);
		$this->assertEquals(TRUE, $model->category->load()->loaded());
	}
}