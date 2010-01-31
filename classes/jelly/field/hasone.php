<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_HasOne extends Jelly_Field
{	
	public $in_db = FALSE;
	
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
			$this->foreign_column = $model.'_id';
		}
		
		// Column is set and won't be overridden
		parent::initialize($model, $column);
	}
	
	/**
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		// Handle Database Results
		if (is_object($value))
		{
			return $value->id();
		}
		else
		{
			return $value;
		}
	}
	
	/**
	 * @param string $object 
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function get($model, $value, $object = TRUE)
	{
		// Only return the actual value
		if (!$object)
		{
			return $value;
		}
		
		// Return a real object
		return Jelly::factory($this->foreign_model)
				->where($this->foreign_column, '=', $model->id());
	}
	
	/**
	 * Saves has_many relations setting empty records to the default
	 *
	 * @param string $id 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function save($model, $value)
	{
		$foreign = Jelly::Factory($this->foreign_model);
		
		// Empty relations to the default value
		$foreign
			->where($this->foreign_column, '=', $model->id())
			->execute(Database::UPDATE, array(
				$this->foreign_column => $this->default
			));
						
		// Set the new relations
		if (!empty($value))
		{			
			// Update the ones in our list
			$foreign
				->where($foreign->primary_key(), '=', $value)
				->execute(Database::UPDATE, array(
					$this->foreign_column => $model->id()
				));
		}
		
		return $value;
	}
	
	/**
	 * Returns whether or not this field has another model
	 *
	 * @param string $model 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function has($model, $ids)
	{
		$model = Jelly::factory($this->foreign_model);
		return (bool) $model
			->select(array('COUNT("*")', 'records_found'))
			->where($this->foreign_column, '=', $model->id())
			->where($model->primary_key(), 'IN', $ids)
			->execute()
			->get('records_found');
	}
	
	/**
	 * Provides the input with the ids variable. An array of
	 * all the ID's in the foreign model that this record owns.
	 *
	 * @param string $prefix
	 * @param string $data
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function input($prefix = 'jelly/field', $data = array())
	{
		$data['id'] = $this->get($data['model'], NULL)->load()->id();
		return parent::input($prefix, $data);
	}
}
