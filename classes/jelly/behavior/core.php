<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Behavior allows mixin-style plugins to be written for models.
 * 
 * 
 *
 * @package  Jelly
 */
abstract class Jelly_Behavior_Core
{
	/**
	 * @var  string  The name given to the behavior
	 */
	protected $name = NULL;
	
	/**
	 * Constructor.
	 *
	 * @param  string  $params 
	 */
	public function __construct($params = NULL)
	{
		// Just throw them into the class
		foreach ($options as $key => $value)
		{
			$this->$key = $value;
		}
	}
	
	/**
	 * Called when the model is first initialized, so 
	 * that the behavior can modify the metadata of 
	 * the model.
	 * 
	 * The name or alias of the behavior is also provided. 
	 *
	 * @param  Jelly_Meta  $meta 
	 * @param  string      $name
	 * @return  void
	 */
	public function initialize(Jelly_Meta $meta, $name)
	{
		$this->name = $name;
	}
	
	/**
	 * Called before setting value on a model so that behaviors
	 * can modify incoming values as they're set.
	 *
	 * @param   Jelly_Model  $model
	 * @param   string       $field
	 * @param   mixed        $value
	 * @return  void
	 */
	public function set(Jelly_Model $model, $field, $value) 
	{
		
	}
	
	/**
	 * Called whenever a model is validated.
	 *
	 * @param   Jelly_Model $model 
	 * @return  void
	 */
	public function validate(Jelly_Model $model)
	{
		
	}
	
	/**
	 * Called whenever a model is saved.
	 *
	 * @param   Jelly_Model $model 
	 * @return  void
	 */
	public function save(Jelly_Model $model) 
	{
		
	}
	
	/**
	 * Called whenever a model is deleted.
	 *
	 * @param   Jelly_Model $model 
	 * @return  void
	 */
	public function delete(Jelly_Model $model) 
	{
		
	}
}
