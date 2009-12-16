<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_HasOne extends Jelly_Field
{	
	/**
	 * An array of IDs that have been set.
	 * 
	 * This is empty unless the value has been set manually
	 *
	 * @var string
	 */
	public $value = array();
	
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
			$this->value = $value->id();
		}
		else
		{
			$this->value = $value;
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
				->limit(1, TRUE)
				->where($this->foreign_column, '=', $this->model->id());
	}
	
	/**
	 * Saves has_many relations setting empty records to the default
	 *
	 * @param string $id 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function save($id)
	{
		// Empty relations to the default value
		$model = Jelly::factory($this->foreign_model);
		$alias = $model->alias($this->foreign_column);
		$query = $model->where($this->foreign_column, '=', $this->model->id())
					   ->build(Database::UPDATE);
		
		// NULL them out
		$query->set(array($alias => $this->default))
			  ->execute($model->db());
			
		// Set the new relations
		if (!empty($this->value))
		{			
			// Update the ones in our list
			$query = $model->end()->where($model->primary_key(), '=', $this->value)
						   ->build(Database::UPDATE);
						
			// Set them to this object
			$query->set(array($alias => $id))
				  ->execute($model->db());
		}
	}
}
