<?php defined('SYSPATH') or die('No direct script access.');

/**
 * This core class is the main interface to all
 * models, builders, and meta data.
 * 
 * @package  Jelly
 */
abstract class Jelly_Core
{	
	/**
	 * @var  string  The prefix to use for all model's class names
	 *               This can be overridden to allow you to place
	 *               models and builders in a different location.
	 */
	protected static $_model_prefix = 'model_';
	
	/**
	 * @var  string  This prefix to use for all model's field classes
	 *               This can be overridden to allow you to place
	 *               field classes in a different location.
	 */
	protected static $_field_prefix = 'Jelly_Field_';
	
	/**
	 * @var  string  This prefix to use for all behavior classes
	 *               This can be overridden to allow you to place
	 *               behavior classes in a different location.
	 */
	protected static $_behavior_prefix = 'Jelly_Behavior_';
        
	/**
	 * @var  array  Contains all of the meta classes related to models
	 */
	protected static $_models = array();

	/**
	 * Factory for instantiating models.
	 *
	 * A key can be passed to automatically load a model by its
	 * unique key.
	 *
	 * @param   mixed  $model
	 * @param   mixed  $key
	 * @return  Jelly
	 */
	public static function factory($model, $key = NULL)
	{
		$class = Jelly::class_name($model);

		return new $class($key);
	}
	
	/**
	 * Returns a query builder that can be used for querying.
	 *
	 * If $key is passed, the key will be passed to unique_key(), the result
	 * will be limited to 1, and the record will be returned directly.
	 *
	 * In essence, passing a $key is analogous to:
	 *
	 *     Jelly::query($model)->where(':unique_key', '=' $key)->limit(1);
	 *
	 * @param   string  $model
	 * @param   mixed   $key
	 * @return  Jelly_Builder
	 */
	public static function query($model, $key = NULL)
	{
		$builder = 'Jelly_Builder';
		
		if ($meta = Jelly::meta($model))
		{
			if ($meta->builder())
			{
				$builder = $meta->builder();
			}
		}

		return new $builder($model, $key);
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
	 * Factory for instantiating fields.
	 *
	 * @param   string $type
	 * @param   mixed  $options
	 * @return  Jelly_Field
	 */
	public static function field($type, $options = NULL)
	{
		$field = Jelly::$_field_prefix.$type;
                
		return new $field($options);	
	}
	
	/**
	 * Factoring for instantiating behaviors.
	 *
	 * @param   string $type
	 * @param   mixed  $options
	 * @return  Jelly_Behavior
	 */
	public static function behavior($type, $options = NULL)
	{
		$behavior = Jelly::$_behavior_prefix.$type;
		
		return new $behavior($options);
	}
	
	/**
	 * Automatically loads a model, if it exists,
	 * into the meta table.
	 *
	 * Models are not required to register
	 * themselves; it happens automatically.
	 *
	 * @param   string  $model
	 * @return  boolean
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
			return strtolower(Jelly::$_model_prefix.$model);
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

		$prefix_length = strlen(Jelly::$_model_prefix);

		// Compare the first parts of the names and chomp if they're the same
		if (strtolower(substr($model, 0, $prefix_length)) === strtolower(Jelly::$_model_prefix))
		{
			$model = substr($model, $prefix_length);
		}

		return strtolower($model);
	}

	/**
	 * Returns the prefix to use for all models and builders.
	 *
	 * @return  string
	 */
	public static function model_prefix()
	{
		return Jelly::$_model_prefix;
	}
	
	/**
	 * Returns the prefix to use for all fields.
	 *
	 * @return  string
	 */
	public static function field_prefix()
	{
		return Jelly::$_field_prefix;
	}
	
	/**
	 * Returns the prefix to use for all behaviors.
	 *
	 * @return  string
	 */
	public static function behavior_prefix()
	{
		return Jelly::$_behavior_prefix;
	}
}
