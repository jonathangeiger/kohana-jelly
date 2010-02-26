<?php defined('SYSPATH') or die('No direct script access.');

/**
 * This core class is the main interface to all 
 * models, builders, and meta data.
 */
abstract class Jelly_Core
{
	/**
	 * @var string The prefix to use for all model's class names
	 *             This can be overridden to allow you to place 
	 *             models and builders in a different location.
	 */
	protected static $_prefix = 'model_';

	/**
	 * @var array Contains all of the meta classes related to models
	 */
	protected static $_models = array();
	
	/**
	 * Factory for instantiating models.
	 * 
	 * If $values is passed and it is an array, it will be 
	 * applied to the model as if it were a database result.
	 * The model is then considered to be loaded.
	 *
	 * @param	mixed  $model
	 * @param	mixed  $key
	 * @return	Jelly
	 */
	public static function factory($model, $values = NULL)
	{	
		$class = Jelly::class_name($model);
		
		return new $class($values);
	}

	/**
	 * Returns a query builder that can be used for selecting records.
	 * 
	 * If $key is passed, the key will be passed to unique_key(), the result
	 * will be limited to 1, and the record will be returned directly.
	 * 
	 * In essence, passing a $key is analogous to:
	 * 
	 *     Model::select($model)->load($key);
	 * 
	 * Which itself is a shortcut for:
	 * 
	 *     Model::select($model)
	 *          ->where(':unique_key', '=', $key)
	 *          ->limit(1)
	 *          ->execute();
	 *
	 * @param  string  $model 
	 * @param  mixed   $cond
	 * @return Jelly_Builder
	 */
	public static function select($model, $key = NULL)
	{
		$builder = Jelly::builder($model, Database::SELECT);
		
		if ($key)
		{
			return $builder->load($key);
		}
		
		return $builder;
	}

	/**
	 * Returns a query builder that can be used for inserting record(s).
	 * 
	 * Generally, you will only want to use Models directly for creating
	 * new records, since this method doesn't support validation or 
	 * relations, but it is still here to complete the API.
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
	 * Similar to Jelly::insert(), you will generally want to use Models for
	 * updating an individual record, but this method is useful for updating
	 * columns on multiple rows all at once.
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
	 * While you will generally want to use models for deleting single records,
	 * this method remains useful for deleting multiple rows all at once.
	 *
	 * @param  string $model 
	 * @return Jelly_Builder
	 */
	public static function delete($model)
	{
		return Jelly::builder($model, Database::DELETE);
	}

	/**
	 * Gets a particular set of metadata about a model. If the model
	 * isn't registered, it will attempt to register it.
	 * 
	 * FALSE is returned on failure.
	 *
	 * @param   string|Jelly_Model  $model
	 * @return  Jelly_Meta
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
	 * Automatically loads a model, if it exists, 
	 * into the meta table.
	 * 
	 * Models are not required to register 
	 * themselves; it happens automatically.
	 *
	 * @param  string  $model 
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
	 * @param   string|Jelly_Model  $model
	 * @return  string
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
	 * @param   string|Jelly_Model  $model
	 * @return  string
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
	 * 
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
	 * @param  int    $type
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