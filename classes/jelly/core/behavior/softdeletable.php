<?php defined('SYSPATH') or die('No direct script access.');

abstract class Jelly_Core_Behavior_SoftDeletable implements Jelly_Behavior_Interface
{
	/**
	 * @var  string  The model this is attached to
	 */
	protected $_model;
	
	/**
	 * @var  string  The name of this behavior
	 */
	protected $_name;
	
	/**
	 * @var  string  The name to use for the field that keeps track of a model's deleted status
	 */
	protected $_column = 'deleted_at';
	
	/**
	 * @var  boolean  Whether or not the behavior is disabled on this model.
	 */
	protected $_disabled = FALSE;
	
	/**
	 * Constructor.
	 *
	 * @param   array   $params 
	 */
	public function __construct($params = array())
	{
		if (is_string($params))
		{
			$params = array('column' => $params);
		}
		
		foreach ($params as $key => $param)
		{
			$this->{'_'.$key} = $param;
		}
	}
	
	/**
	 * Initialize.
	 *
	 * @param   string   $model 
	 * @param   string   $name 
	 */
	public function initialize($model, $name)
	{
		$this->_model = $model;
		$this->_name  = $name;
	}
	
	/**
	 * Adds the deleted_at column to the meta object, which is
	 * used for determining whether or not a field has been
	 * soft-deleted.
	 *
	 * @param  Jelly_Meta $meta 
	 * @return void
	 */
	public function before_meta_finalize(Jelly_Meta $meta)
	{
		$meta->fields(array(
			$this->_column => new Field_Timestamp,
		));
	}
	
	/**
	 * Callback.
	 *
	 * @param   Jelly_Builder  $query 
	 * @return  void
	 */
	public function before_builder_select(Jelly_Builder $query)
	{
		if ($this->_disabled) return;
		
		$query->where($this->_column, 'IS', NULL);
	}
	
	/**
	 * Callback
	 *
	 * @param   Jelly_Builder  $query 
	 * @return  void
	 */
	public function before_builder_delete(Jelly_Builder $query)
	{
		if ($this->_disabled) return;
		
		// Return a value that's consistent with what delete would normally return
		$result = (bool) $query->set(array('deleted_at' => time()))->update();
		
		// Delete shouldn't continue
		return new Jelly_Behavior_Result($result, TRUE);
	}
	
	/**
	 * Callback.
	 *
	 * @param  Jelly_Model $model 
	 * @param  mixed       $key 
	 * @return void
	 */
	public function before_model_delete(Jelly_Model $model, $key)
	{		
		if ($this->_disabled) return;
		
		// Return a value that's consistent with what delete would normally return
		$result = (bool) $query->set(array('deleted_at' => time()))->update();
		
		// Delete shouldn't continue
		return new Jelly_Behavior_Result($result, TRUE);
	}
	
	/**
	 * Custom builder method that restores the records the 
	 * query finds so they are not longer deleted.
	 *
	 * @param   Jelly_Builder  $query 
	 * @return  boolean
	 */
	public function builder_restore(Jelly_Builder $query)
	{
		// Update the record so it's no longer deleted
		return (bool) $query->set(array('deleted_at' => NULL))->update();
	}
	
	/**
	 * Custom builder method to totally destroy records.
	 * 
	 * @param   Jelly_Builder  $query
	 * @return  boolean
	 */
	public function builder_destroy(Jelly_Builder $query)
	{
		$this->_disable();
		$result = $query->delete();
		$this->_enable();
		
		return $result;
	}
	
	/**
	 * Custom model method to totally destroy a row.
	 * 
	 * @param   Jelly_Model  $model
	 * @return  boolean
	 */
	public function model_destroy(Jelly_Model $model, $key = NULL)
	{
		$this->_disable();
		$result = $model->delete($key);
		$this->enable();
		
		return $result;
	}
	
	/**
	 * Disables the functionality of this behavior.
	 *
	 * @param   Jelly_Model  $model
	 * @return  Jelly_Model
	 */
	public function model_disable(Jelly_Model $model)
	{
		$this->_disable();
		return $model;
	}
	
	/**
	 * Enables the functionality of this behavior.
	 *
	 * @param   Jelly_Model  $model
	 * @return  Jelly_Model
	 */
	public function model_enable(Jelly_Model $model)
	{
		$this->_enable();
		return $model;
	}
	
	/**
	 * Disables the functionality of this behavior.
	 *
	 * @param   Jelly_Builder  $model
	 * @return  Jelly_Builder
	 */
	public function builder_disable(Jelly_Builder $builder)
	{
		$this->_disable();
		return $builder;
	}
	
	/**
	 * Enables the functionality of this behavior.
	 *
	 * @param   Jelly_Builder  $model
	 * @return  Jelly_Builder
	 */
	public function builder_enable(Jelly_Builder $builder)
	{
		$this->_enable();
		return $builder;
	}
	
	/**
	 * Disables the behavior.
	 *
	 * @return void
	 */
	protected function _disable()
	{
		$this->_disabled = TRUE;
	}
	
	/**
	 * Enables the behavior.
	 *
	 * @return void
	 */
	protected function _enable()
	{
		$this->_disabled = FALSE;
	}
}