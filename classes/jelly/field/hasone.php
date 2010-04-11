<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles has one relationships
 *
 * @package  Jelly
 */
abstract class Jelly_Field_HasOne extends Jelly_Field_HasMany implements Jelly_Field_Behavior_Joinable
{
	/**
	 * @param   mixed  $value
	 * @return  mixed
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
	 * @param   Jelly_Model  $model
	 * @param   mixed        $value
	 * @param   boolean      $loaded
	 * @return  mixed
	 */
	public function get($model, $value)
	{
		if ($model->changed($this->name))
		{
			// Return a real object
			return Jelly::select($this->foreign['model'])
					->where(':primary_key', '=', $value)
					->limit(1);
		}
		else
		{
			return Jelly::select($this->foreign['model'])
					->where($this->foreign['column'], '=', $model->id())
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
		Jelly::update($this->foreign['model'])
			->where($this->foreign['column'], '=', $model->id())
			->set(array($this->foreign['column'] => $this->default))
			->execute();

		// Set the new relations
		if ( ! empty($value))
		{
			// Update the ones in our list
			Jelly::update($this->foreign['model'])
				->where(':primary_key', '=', $value)
				->set(array($this->foreign['column'] => $model->id()))
				->execute();
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
