<?php defined('SYSPATH') or die('No direct script access.');

/**
 * This core class is the main interface to all
 * models, builders, and meta data.
 * 
 * @package  Jelly
 */
abstract class Jelly_Core
{	
	const SELECT = 1;
	const INSERT = 2;
	const UPDATE = 3;
	const DELETE = 4;
	
	/**
	 * @var  string  The prefix to use for all model's class names
	 */
	public static $model_prefix = 'Model_';

	/**
	 * @var  string  The prefix to use for all model's manager class names
	 */
	public static $manager_prefix = 'Model_Manager_';

	/**
	 * @var  string  The prefix to use for all fields's class names
	 */
	public static $field_prefix = 'Jelly_Field_';
	
	/**
	 * @var  string  The prefix to use for all behaviors' class names
	 */
	public static $behavior_prefix = 'Jelly_Behavior_';
	
	/**
	 * Factory for instantiating models.
	 *
	 * @param   mixed  $model
	 * @param   mixed  $key
	 * @return  Jelly
	 */
	public static function factory($model, $key = NULL)
	{
		$meta = Jelly::meta($model);
		
		if ($meta->model)
		{
			$class = Jelly::$model_prefix.$meta->model;
			return new $class($key);
		}
		
		return NULL;
	}
	
	/**
	 * Returns a query builder that can be used for querying. 
	 *
	 * @param   string  $model
	 * @param   mixed   $key
	 * @return  Jelly_Manager
	 */
	public static function query($model, $key = NULL)
	{
		$class = Jelly::meta($model)->manager;
		return new $class($model, $key);
	}
	
	/**
	 * Gets a particular set of metadata about a model. If the model
	 * isn't registered, it will attempt to register it.
	 *
	 * A meta object is returned for every string passed to this
	 * even if it's not a valid model. This allows arbitrary tables
	 * to have meta objects.
	 *
	 * @param   string  $model
	 * @return  Jelly_Meta
	 */
	public static function meta($model)
	{
		return Jelly_Meta::instance($model);
	}
}
