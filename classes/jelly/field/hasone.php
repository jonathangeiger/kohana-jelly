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
	public function get($model, $value)
	{
		// Return a real object
		return Jelly::factory($this->foreign_model)
				->limit(1, TRUE)
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
				->end()
				->where(Jelly_Meta::get($foreign, 'primary_key'), '=', $value)
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
			->where(Jelly_Meta::get($model, 'primary_key'), 'IN', $ids)
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
	
	public function with($model, $relation, $target_path, $parent_path)
	{
		$meta = Jelly_meta::get($this->foreign_model);
		
		// Fields have to be aliased since we don't necessarily know the model from the path
		$parent_column = Jelly_Meta::column($this->foreign_model, $meta->primary_key, FALSE);
		$target_column = Jelly_Meta::column($this->model, $this->foreign_column, FALSE);
		
		$join_col1 = $parent_path.'.'.$parent_column;
		$join_col2 = $target_path.'.'.$target_column;
				
		$model
			->join(array($relation, $target_path), 'LEFT')
			->on($join_col1, '=', $join_col2);
	}
}
