<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_BelongsTo extends Jelly_Field_ForeignKey
{	
	protected $in_db = TRUE;
	
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
			$this->foreign_model = $column;
		}
		
		if (empty($this->column))
		{
			$this->column = $this->foreign_model.'_id';
		}
		
		if (empty($this->foreign_column))
		{
			$this->foreign_column = 'id';
		}
		
		// Column is set and won't be overridden
		parent::initialize($model, $column);
	}
	
	/**
	 * @param mixed An integer or another model for this to belong to 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		if (is_object($value))
		{
			if ($value->loaded())
			{
				$this->value = $value->id();
			}
			else
			{
				$this->value = $this->default;
			}
		}
		else
		{
			$this->value = $value;
		}
	}
	
	/**
	 * @param boolean $object 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function get($object = TRUE)
	{
		// Only return the actual value
		if (!$object)
		{
			return $this->value;
		}
		
		// Return a real category object
		return Jelly::factory($this->foreign_model)
				->limit(1, TRUE)
				->where($this->foreign_column, '=', $this->value);
	}
}
