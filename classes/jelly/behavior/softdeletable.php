<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Behavior_SoftDeletable extends Jelly_Behavior
{
	/**
	 * @var  string  The name to use for the field that keeps track of a model's deleted status
	 */
	protected $_column = 'deleted_at';
	
	/**
	 * Adds the deleted_at column to the meta object, which is
	 * used for determining whether or not a field has been
	 * soft-deleted.
	 *
	 * @param  Jelly_Meta $meta 
	 * @return void
	 */
	public function after_initialize(Jelly_Meta $meta)
	{
		$meta->fields(array(
			$this->_column => new Field_Timestamp
		));
	}
	
	/**
	 * Callback.
	 *
	 * @param Jelly_Builder $query 
	 * @return void
	 */
	public function before_query(Jelly_Builder $query, $type)
	{
		if ($type === Database::SELECT)
		{
			$query->where($this->_column, 'IS', NULL);
		}
	}
	
	/**
	 * Callback.
	 *
	 * @param  Jelly_Model $model 
	 * @param  mixed       $key 
	 * @return void
	 */
	public function before_delete(Jelly_Model $model, $key)
	{		
		$result = new Jelly_Behavior_Result;
		
		// Delete shouldn't continue
		$result->break = TRUE;
		
		// Return a value that's consistent with what delete would normally return
		$result->value = (bool) Jelly::query($model, $key)
		                   ->set(array('deleted_at' => time()))
		                   ->update();
		
		return $result;
	}
	
	/**
	 * Custom model method that restores a soft-deleted record.
	 * 
	 * Set $load to FALSE to forgo loading the newly restored
	 * record into the model.
	 *
	 * @param   Jelly_Model $model 
	 * @param   mixed       $key 
	 * @param   boolean     $load
	 * @return  void
	 */
	public function model_restore(Jelly_Model $model, $key = NULL, $load = TRUE)
	{
		if (func_num_args() === 1)
		{
			$key = $model->id();
		}
		
		// Update the record so it's no longer deleted
		Jelly::query($model, $key)
			 ->set(array('deleted_at' => NULL))
			 ->update();
			
		// Load the same record into this object
		if ($load)
		{
			return Jelly::query($model, $key)->select();
		}
			
		return $model;
	}
	
	
}