<?php defined('SYSPATH') or die('No direct script access.');

/**
 * This core class is the main interface
 */
abstract class Jelly_Core
{
	/**
	 * @var string The prefix to use for all model's class names
	 */
	protected static $_prefix = 'model_';

	/**
	 * @var array Contains all of the meta classes related to models
	 */
	protected static $_models = array();
	
	/**
	 * Factory for generating models. Fields are initialized only 
	 * on the first instantiation of the model, and never again.
	 * 
	 * Model's do not have to be instantiated through here; they 
	 * can be constructed directly.
	 *
	 * @param	mixed  $model
	 * @param	mixed  $key
	 * @return	Jelly
	 */
	public static function factory($model, $key = NULL)
	{	
		$class = Jelly::class_name($model);
		
		return new $class($key);
	}

	/**
	 * Returns a query builder that can be used for selecting records.
	 * 
	 * When $cond is passed, the query is automatically limited to 1.
	 * If $cond is passed and is an int or string, it will be used as the primary key.
	 * If it is an array, it will be used in constructing a where() clause.
	 *
	 * @param  string  $model 
	 * @param  mixed   $cond
	 * @return Jelly_Builder
	 */
	public static function select($model)
	{
		return Jelly::builder($model, Database::SELECT);
	}

	/**
	 * Returns a query builder that can be used for inserting record(s).
	 *
	 * @param  string $model 
	 * @return Jelly_Builder
	 */
	public static function insert($model)
	{
		return Jelly::builder($model, Database::INSERT);
	}

	/**
	 * Returns a query builder that can be used for updating many records.
	 *
	 * @param  string $model 
	 * @return Jelly_Builder
	 */
	public static function update($model)
	{
		return Jelly::builder($model, Database::UPDATE);
	}

	/**
	 * Returns a query builder that can be used for deleting many records.
	 *
	 * @param  string $model 
	 * @return Jelly_Builder
	 */
	public static function delete($model)
	{
		return Jelly::builder($model, Database::DELETE);
	}

	/**
	 * Gets a particular set of metadata about a model
	 *
	 * @param string|Jelly $model The model to search for
	 * @param string       $property An optional property to get if the model exists
	 * @return void
	 */
	public static function meta($model)
	{
		$model = Jelly::model_name($model);
		
		if ( ! isset(Jelly::$_models[$model]))
		{
			if ( ! Jelly::register($model))
			{
				return FALSE;
			}
		}
		
		return Jelly::$_models[$model];
	}

	/**
	 * Automatically loads a model, if it exists, into the meta table.
	 * 
	 * Models are not required to register themselves. It 
	 * happens automatically.
	 *
	 * @param string $model 
	 * @return boolean
	 */
	public static function register($model)
	{
		$class = Jelly::class_name($model);
		$model = Jelly::model_name($model);
		
		// Don't re-initialize!
		if (isset(Jelly::$_models[$model]))
		{
			return TRUE;
		}
				
		 // Can we find the class?
		if (class_exists($class))
		{
			// Prevent accidentally trying to load ORM or Sprig models
			if ( ! is_subclass_of($class, "Jelly_Model"))
			{
				return FALSE;
			}
		}
		else
		{
			return FALSE;
		}
		
		// Load it into the registry
		Jelly::$_models[$model] = $meta = new Jelly_Meta($model);

		// Let the intialize() method override defaults.
		call_user_func(array($class, 'initialize'), $meta);
		
		// Finalize the changes
		$meta->finalize($model);
		
		return TRUE;
	}

	/**
	 * Returns the class name of a model
	 *
	 * @param string|Jelly The model to find the class name of
	 * @package default
	 */
	public static function class_name($model)
	{
		if ($model instanceof Jelly_Model)
		{
			return strtolower(get_class($model));
		}
		else
		{
			return strtolower(Jelly::$_prefix.$model);
		}
	}

	/**
	 * Returns the model name of a class
	 *
	 * @param string|Jelly The model to find the model name of
	 * @return void
	 */
	public static function model_name($model)
	{
		if ($model instanceof Jelly_Model)
		{
			$model = get_class($model);
		}
		
		$prefix_length = strlen(Jelly::$_prefix);
		
		// Compare the first parts of the names and chomp if they're the same
		if (strtolower(substr($model, 0, $prefix_length)) === strtolower(Jelly::$_prefix))
		{
			$model = substr($model, $prefix_length);
		}
		
		return strtolower($model);
	}
	
	/**
	 * Returns the prefix to use for all models and builders.
	 * @return string
	 */
	public static function prefix()
	{
		return Jelly::$_prefix;
	}

	/**
	 * Returns the builder class to use for the specified model
	 *
	 * @param  string $model 
	 * @return string
	 */
 	protected static function builder($model, $type)
	{
		$builder = 'Jelly_Builder';
		
		if ($meta = Jelly::meta($model))
		{
			if ($meta->builder())
			{
				$builder = $meta->builder();
			}
		}
		
		return new $builder($model, $type);
	}
}