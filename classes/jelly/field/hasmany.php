<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_HasMany extends Jelly_Field_ForeignKey
{	
	protected $value = array();
	
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
	
	/**
	 * Sets 
	 *
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		// Can be set in only one go
		$this->value = array();
		
		// Handle Database Results
		if (is_object($value))
		{
			foreach($value as $row)
			{
				$this->value[] = $row->id();
			}
		}
		else
		{
			$this->value = (array)$value;
		}
	}
	
	/**
	 * @param string $object 
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function get($object = TRUE)
	{
		// Only return the actual value
		if (!$object)
		{
			return $this->value;
		}
		
		// Return a real object
		return Jelly::factory($this->foreign_model)
				->where($this->foreign_column, '=', $this->model->id());
	}
	
	public function save($id)
	{
		// Empty relations
		if ($this->value === NULL)
		{
			$model = Jelly::factory($this->foreign_model);
			$alias = $model->alias($this->foreign_column);
			$query = Jelly::factory($this->foreign_model)
						->where($this->foreign_column, '=', $this->model->id())
						->build(Database::UPDATE);
				
			$query
				->set(array($alias => $this->default))
				->execute($model->db());
		}
	}
}
