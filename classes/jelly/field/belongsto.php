<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_BelongsTo extends Jelly_Field 
{	
	/**
	 * @var boolean Defaults belongs_to's to in the database
	 */
	public $in_db = TRUE;
	
	/**
	 * @var string The name of the foreign model to use. 
	 */
	public $foreign_model;
	
	/**
	 * @var string The name of the foreign column to use. 
	 */
	public $foreign_column;
	
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
		// Default to the name of the column
		if (empty($this->foreign_model))
		{
			$this->foreign_model = $column;
		}
		
		// Default to foreign_model plus _id
		if (empty($this->column))
		{
			$this->column = $this->foreign_model.'_id';
		}
		
		// Default to 'id'
		if (empty($this->foreign_column))
		{
			$this->foreign_column = 'id';
		}
		
		// Column is set and won't be overridden
		parent::initialize($model, $column);
	}
	
	/**
	 * Accepts another Jelly Model or the value of a primary key.
	 * 
	 * @param mixed An integer or another model for this to belong to 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		if (is_object($value))
		{
			if ($value->loaded() && $value->id())
			{
				$value = $value->id();
			}
			else
			{
				$value = $this->default;
			}
		}
		
		return (is_numeric($value)) ? (int) $value : (string) $value;
	}
	
	/**
	 * @param boolean $object 
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function get($model, $value)
	{
		// Return a real category object
		return Jelly::factory($this->foreign_model)
				->limit(1, TRUE)
				->where($this->foreign_column, '=', $value);
	}
	
	public function with($model, $relation, $target_path, $parent_path)
	{
		$meta = Jelly_meta::get($this->foreign_model);

		// Fields have to be aliased since we don't necessarily know the model from the path
		$target_column = Jelly_Meta::column($this->foreign_model, $meta->primary_key, FALSE);
		$parent_column = Jelly_Meta::column($this->model, $this->foreign_column, FALSE);
		
		$join_col1 = $target_path.'.'.$target_column;
		$join_col2 = $parent_path.'.'.$parent_column;
				
		$model
			->join(array($meta->table, $target_path), 'LEFT')
			->on($join_col1, '=', $join_col2);
	}
}
