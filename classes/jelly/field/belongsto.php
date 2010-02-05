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
		
		return $value;
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
}
