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
	 * @param	mixed  $cond
	 * @return	Jelly
	 */
	public static function factory($model, $cond = NULL)
	{	
		$class = Jelly::class_name($model);
		
		return new $class($id);
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
		return new Jelly_Builder($model, Database::SELECT);
	}
	
	/**
	 * Returns a query builder that can be used for inserting record(s).
	 *
	 * @param  string $model 
	 * @return Jelly_Builder
	 */
	public static function insert($model)
	{
		return new Jelly_Builder($model, Database::INSERT);
	}
	
	/**
	 * Returns a query builder that can be used for updating many records.
	 *
	 * @param  string $model 
	 * @return Jelly_Builder
	 */
	public static function update($model)
	{
		return new Jelly_Builder($model, Database::UPDATE);
	}
	
	/**
	 * Returns a query builder that can be used for deleting many records.
	 *
	 * @param  string $model 
	 * @return Jelly_Builder
	 */
	public static function delete($model)
	{
		return new Jelly_Builder($model, Database::DELETE);
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
		
		if (!isset(Jelly::$_models[$model]))
		{
			if (!Jelly::register($model))
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
		if (class_exists($class, FALSE) || Kohana::auto_load($class))
		{
			// Prevent accidentally trying to load ORM or Sprig models
			if (!is_subclass_of($class, "Jelly_Model"))
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
		
		// Initialize all of the fields with their column and the model name
		foreach($meta->fields as $column => $field)
		{
			// Allow aliasing fields
			if (is_string($field))
			{
				if (isset($meta->fields[$field]))
				{
					$meta->aliases[$column] = $field;
				}
									
				// Aliases shouldn't pollute fields
				unset($meta->fields[$column]);
				
				continue;
			}
			
			$field->initialize($model, $column);
			
			// Ensure a default primary key is set
			if ($field->primary && empty($meta->primary_key))
			{
				$meta->primary_key = $column;
			}
			
			// Set the defaults so they're actually persistent
			$meta->defaults[$column] = $field->default;
			
			// Set the columns, so that we can access reverse database results properly
			if (!array_key_exists($field->column, $meta->columns))
			{
				$meta->columns[$field->column] = array();
			}
			
			$meta->columns[$field->column][] = $column;
		}
			
		// Meta object is initialized and no longer writable
		$meta->initialized = TRUE;
		
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
	
}