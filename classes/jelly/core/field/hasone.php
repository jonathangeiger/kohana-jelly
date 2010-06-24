<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles has one relationships.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_HasOne extends Jelly_Field_HasMany implements Jelly_Field_Supports_With
{
	/**
	 * @var  boolean  Null values are not allowed
	 */
	public $allow_null = FALSE;
	
	/**
	 * @var  array  Default is an empty array
	 */
	public $default = 0;
	
	/**
	 * @param   mixed  $value
	 * @return  mixed
	 */
	public function set($value)
	{
		// Convert models to their id
		if (is_object($value))
		{
			$value = $value->id();
		}
		
		list($value, $return) = $this->_default($value);
		
		if ( ! $return)
		{
			$value = is_numeric($value) ? (int) $value : (string) $value;
		}
		
		return $value;
	}

	/**
	 * Returns the record that the model has
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $value
	 * @param   boolean      $loaded
	 * @return  mixed
	 */
	public function get($model, $value)
	{
		if ($model->changed($this->name))
		{
			return Jelly::query($this->foreign['model'])
			            ->where(':primary_key', '=', $value)
			            ->limit(1);
		}
		else
		{
			return Jelly::query($this->foreign['model'])
			            ->where($this->foreign['model'].'.'.$this->foreign['column'], '=', $model->id())
			            ->limit(1);
		}
	}

	/**
	 * Implementation of Jelly_Field_Behavior_Saveable
	 *
	 * @param   Jelly  $model
	 * @param   mixed  $value
	 * @return  void
	 */
	public function save($model, $value, $loaded)
	{
		// Empty relations to the default value
		Jelly::query($this->foreign['model'])
		     ->where($this->foreign['column'], '=', $model->id())
		     ->set(array($this->foreign['column'] => $this->default))
		     ->update();

		// Set the new relations
		if ( ! empty($value))
		{
			// Update the ones in our list
			Jelly::query($this->foreign['model'])
			     ->where(':primary_key', '=', $value)
			     ->set(array($this->foreign['column'] => $model->id()))
			     ->update();
		}
	}

	/**
	 * Returns whether or not this field has another model
	 *
	 * @param   string  $model
	 * @return  void
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
	 * Implementation of Jelly_Field_Behavior_Joinable
	 *
	 * @param   Jelly_Builder  $builder
	 * @return  void
	 */
	public function with($builder)
	{
		$join_col1 = $this->model.'.:primary_key';
		$join_col2 = $this->foreign['model'].'.'.$this->foreign['column'];

		$builder
			->join(array($this->foreign['model'], $this->name), 'LEFT')
			->on($join_col1, '=', $join_col2);
	}
}
