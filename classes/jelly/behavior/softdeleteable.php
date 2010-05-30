<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Behavior_SoftDeletable
{
	protected $_column = 'deleted_at';
	
	public function initialize(Jelly_Meta $meta)
	{
		$meta->fields(array(
			$this->_column => new Field_Timestamp
		));
	}
	
	public function before_query(Jelly_Builder $query)
	{
		$query->where($this->_column, 'IS', NULL);
	}
	
	public function before_delete(Jelly_Model $model, $key = NULL)
	{
		$result = new Jelly_Behavior_Result;
		
		// Delete shouldn't continue
		$result->break = TRUE;
		
		// Return a value that's consistent with what delete would normally return
		$result->value = (bool) Jelly::update($model)
		                   ->where(':unique', '=', $key)
		                   ->set(array('deleted_at' => time()))
		                   ->execute();
	}
	
	public function model_restore(Jelly_Model $model, $key)
	{
		if ($key)
		{
			
		}
		Jelly::update($model)
			 ->where(':unique', '=', $key)
			 ->set(array('deleted_at' => time()))
			 ->execute();
	}
}