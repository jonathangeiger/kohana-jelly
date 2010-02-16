<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_HasOne extends Jelly_Field_HasMany implements Jelly_Field_Interface_Joinable
{		
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
		return Jelly::factory($this->foreign['model'])
				->limit(1, TRUE)
				->where($this->foreign['column'], '=', $model->id());
	}
	
	/**
	 * Returns whether or not this field has another model
	 *
	 * @param string $model 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function has($model, $id)
	{
		// Only accept the first record
		if (is_array($id) || $id instanceof Iterator)
		{
			$id = array(current($id));
		}
		
		return parent::has($model, $id);
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
	
	/**
	 * Implementation of Jelly_Field_Interface_Joinable
	 *
	 * @param  Jelly  $model 
	 * @param  string $relation 
	 * @param  string $target_path 
	 * @param  string $parent_path 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function with($model, $relation, $target_path, $parent_path)
	{
		$meta = Jelly_meta::get($this->foreign['model']);
		
		// Fields have to be aliased since we don't necessarily know the model from the path
		$parent_column = Jelly_Meta::column($this->foreign['model'], $meta->primary_key, FALSE);
		$target_column = Jelly_Meta::column($this->model, $this->foreign['column'], FALSE);
		
		$join_col1 = $parent_path.'.'.$parent_column;
		$join_col2 = $target_path.'.'.$target_column;
				
		$model
			->join(array($relation, $target_path), 'LEFT')
			->on($join_col1, '=', $join_col2);
	}
}
