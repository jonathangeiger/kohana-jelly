<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_HasOne extends Jelly_Field
{	
	/**
	 * @var string The value of the column
	 */
	public $value = NULL;
	
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
	
	/**
	 * Returns whether or not this field has another model
	 *
	 * @param string $model 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function has(array $ids)
	{
		$model = Jelly::factory($this->foreign_model);
		return (bool) $model
			->select(array('COUNT("*")', 'records_found'))
			->where($this->foreign_column, '=', $this->model->id())
			->where($model->primary_key(), 'IN', $ids)
			->execute()
			->get('records_found');
	}
	
	/**
	 * Ads the id property to the outputted variables
	 *
	 * @param string $prefix 
	 * @param string $data 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function input($prefix = 'jelly/field', $data = array())
	{
		$data['id'] = $this->get()->load()->id();
		return parent::input($prefix, $data);
	}
}
