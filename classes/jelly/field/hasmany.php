<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_HasMany extends Jelly_Field_ForeignKey
{	
	/**
	 * Overrides the initialize to automatically provide the column name
	 *
	 * @param string $model 
	 * @param string $column 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function initialize($model, $column)
	{
		if (empty($this->foreign_model))
		{
			$this->foreign_model = inflector::singular($column);
		}
		
		if (empty($this->foreign_column))
		{
			$this->foreign_column = $model->model_name().'_id';
		}
		
		// Column is set and won't be overridden
		parent::initialize($model, $column);
	}
	
	public function get($object = TRUE)
	{
		// Only return the actual value
		if (!$object)
		{
			return NULL;
		}
		
		// Return a real object
		return Jelly::factory($this->foreign_model)
				->where($this->foreign_column, '=', $this->model->id());
	}
}
