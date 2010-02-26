<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles has one relationships
 *
 * @package Jelly
 */
abstract class Jelly_Field_HasOne extends Jelly_Field_HasMany implements Jelly_Field_Behavior_Joinable
{		
	/**
	 * @param  mixed  $value
	 * @return mixed
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
	 * Returns the record that the model has
	 * 
	 * @param  Jelly_Model  $model
	 * @param  mixed        $value
	 * @param  boolean      $loaded
	 * @return mixed
	 */
	public function get($model, $value)
	{
		return Jelly::select($this->foreign['model'])
				->where($this->foreign['column'], '=', $model->id())
				->limit(1);
	}
	
	/**
	 * Returns whether or not this field has another model
	 *
	 * @param string $model 
	 * @return void
	 */
	public function has($model, $id)
	{
		// Only accept the first record
		if (is_array($id) OR $id instanceof Iterator)
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
	 */
	public function input($prefix = 'jelly/field', $data = array())
	{
		$data['id'] = $this->get($data['model'], NULL)->load()->id();
		return parent::input($prefix, $data);
	}
	
	/**
	 * Implementation of Jelly_Field_Behavior_Joinable
	 *
	 * @param  Jelly_Model  $model
	 * @return void
	 */
	public function with($model)
	{
		$join_col1 = $this->foreign['model'].'.:primary_key';
		$join_col2 = $this->model.'.'.$this->foreign['column'];
				
		$model
			->join($this->foreign['model'], 'LEFT')
			->on($join_col1, '=', $join_col2);
	}
}
