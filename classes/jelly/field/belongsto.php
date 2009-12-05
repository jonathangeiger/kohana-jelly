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
		// Check to see if we need to dynamically generate the column name
		if (empty($this->column))
		{
			$this->column = $column.'_id';
		}
		
		if (empty($this->foreign_model))
		{
			$this->foreign_model = $column;
		}
		
		if (empty($this->foreign_column))
		{
			$this->foreign_column = 'id';
		}
		
		// Column is set and won't be overridden
		parent::initialize($model, $column);
	}
	
	/**
	 * Returns the actual value if $object is FALSE or a Jelly if TRUE
	 *
	 * @param string $object 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function get($object = TRUE)
	{
		// Only return the actual value
		if (!$object)
		{
			return (is_numeric($this->value)) ? (int)$this->value : $this->value;
		}
		
		// Return a real category object
		if (!empty($this->value))
		{
			return Jelly::factory($this->foreign_model)
					->limit(1, TRUE)
					->where($this->foreign_column, '=', $this->value);
		}
		else
		{
			return Jelly::factory($this->foreign_model);
		}
	}
	
	/**
	 * Returns the relation's id
	 *
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function create()
	{
		if (is_object($this->value))
		{
			return $this->value->id();
		}
		else
		{
			return $this->value;
		}
	}
}
