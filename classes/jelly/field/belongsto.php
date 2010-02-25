<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles belongs to relationships
 *
 * @package Jelly
 */
abstract class Jelly_Field_BelongsTo extends Jelly_Field_Relationship implements Jelly_Field_Behavior_Joinable
{	
	/**
	 * @var boolean Defaults belongs_to's to in the database
	 */
	public $in_db = TRUE;
	
	/**
	 * This is expected to contain an assoc. array containing the key 
	 * 'model', and the key 'column'
	 * 
	 * If they do not exist, they will be filled in with sensible defaults 
	 * derived from the field's name. If 'model' is empty it is set to the 
	 * singularized name of the field. If 'column' is empty, it is set to 'id'.
	 * 
	 * `'model' => 'a model to use as the foreign association'`
	 * 
	 * `'column' => 'the column (or alias) that is the foreign model's primary key'`
	 *
	 * @var array
	 */
	public $foreign = array();
	
	/**
	 * Automatically sets foreign to sensible defaults
	 *
	 * @param  string $model 
	 * @param  string $column 
	 * @return void
	 */
	public function initialize($model, $column)
	{
		// Default to the name of the column
		if (empty($this->foreign['model']))
		{
			$this->foreign['model'] = $column;
		}
		
		// Default to foreign['model'] plus _id
		if (empty($this->column))
		{
			$this->column = $this->foreign['model'].'_id';
		}
		
		// Default to 'id'
		if (empty($this->foreign['column']))
		{
			$this->foreign['column'] = ':primary_key';
		}
		
		// Column is set and won't be overridden
		parent::initialize($model, $column);
	}
	
	/**
	 * Returns the primary key of the model passed. 
	 * 
	 * Straight primary keys are also accepted.
	 * 
	 * @param  mixed $value
	 * @return int|string
	 */
	public function set($value)
	{
		if (is_object($value))
		{
			$value = $value->id();
		}
		
		return (is_numeric($value)) ? (int) $value : (string) $value;
	}
	
	/**
	 * Returns the jelly model that this model belongs to
	 *
	 * @param  string $model 
	 * @param  string $value 
	 * @return Jelly
	 */
	public function get($model, $value)
	{
		// Return a real category object
		return Jelly::select($this->foreign['model'])
				->where($this->foreign['column'], '=', $value)
				->limit(1);
	}
	
	/**
	 * Implementation of Jelly_Field_Behavior_Joinable
	 *
	 * @param  Jelly  $model 
	 * @return void
	 */
	public function with($model)
	{
		$join_col1 = $this->model.'.'.$this->column;
		$join_col2 = $this->foreign['model'].'.'.$this->foreign['column'];
				
		$model
			->join($this->foreign['model'], 'LEFT')
			->on($join_col1, '=', $join_col2);
	}
}
