<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Defines meta data for a particular model
 *
 * @package default
 * @author Jonathan Geiger
 */
class Jelly_Meta
{			
	/**
	 * @var array Contains all of the meta classes related to models
	 */
	protected static $_models = array();
	
	/**
	 * @var string The prefix to use for all model's class names
	 */
	protected static $_prefix = 'model_';

	/**
	 * Gets a particular set of metadata about a model
	 *
	 * @param string|Jelly $model The model to search for
	 * @param string       $property An optional property to get if the model exists
	 * @return void
	 * @author Jonathan Geiger
	 */
	public static function get($model, $property = NULL)
	{
		$model = Jelly_Meta::model_name($model);
		
		if (!isset(Jelly_Meta::$_models[$model]))
		{
			if (!Jelly_Meta::register($model))
			{
				return FALSE;
			}
		}
		
		if ($property)
		{
			if (isset(Jelly_Meta::$_models[$model]->$property))
			{
				return Jelly_Meta::$_models[$model]->$property;
			}
			
			return NULL;
		}
		
		return Jelly_Meta::$_models[$model];
	}
		
	/**
	 * Automatically loads a model, if it exists, into the meta table.
	 *
	 * @param string $model 
	 * @return boolean
	 * @author Jonathan Geiger
	 */
	protected static function register($model)
	{
		$class = Jelly_Meta::class_name($model);
				
		// Can we find the class?
		if (class_exists($class, FALSE) || Kohana::auto_load($class))
		{
			// Prevent accidentally trying to load ORM or Sprig models
			if (!is_subclass_of($class, "Jelly"))
			{
				return FALSE;
			}
		}
		else
		{
			return FALSE;
		}
		
		// Load it into the registry
		Jelly_Meta::$_models[$model] = $meta = new Jelly_Meta($model);

		// Let the intialize() method override defaults.
		call_user_func(array($class, 'initialize'), $meta);
		
		// Meta object can no longer have properties set on it
		$meta->initialized = TRUE;
		
		// Initialize all of the fields with their column and the model name
		foreach($meta->fields as $column => $field)
		{
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
		
		return TRUE;
	}
	
	/**
	 * Returns the class name of a model
	 *
	 * @param string|Jelly The model to find the class name of
	 * @package default
	 * @author Jonathan Geiger
	 */
	public static function class_name($model)
	{
		if ($model instanceof Jelly)
		{
			return strtolower(get_class($model));
		}
		else
		{
			return strtolower(Jelly_Meta::$_prefix.$model);
		}
	}
	
	/**
	 * Returns the model name of a class
	 *
	 * @param string|Jelly The model to find the model name of
	 * @return void
	 * @author Jonathan Geiger
	 */
	public static function model_name($model)
	{
		if ($model instanceof Jelly)
		{
			$model = get_class($model);
		}
		
		$prefix_length = strlen(Jelly_Meta::$_prefix);
		
		// Compare the first parts of the names and chomp if they're the same
		if (strtolower(substr($model, 0, $prefix_length)) === strtolower(Jelly_Meta::$_prefix))
		{
			$model = substr($model, $prefix_length);
		}
		
		return strtolower($model);
	}
	
	/**
	 * Returns the table name of the model passed. If the model 
	 * doesn't exist in the registry, the original value is returned.
	 *
	 * @param  mixed   $model  A model name or another Jelly
	 * @return string
	 * @author Jonathan Geiger
	 */
	public static function table($model)
	{
		if ($meta = Jelly_Meta::get($model))
		{
			return $meta->table;
		}
		
		return $model;
	}
	
	/**
	 * Returns the column name for a particular field. This 
	 * method can take arguments in three separate ways:
	 * 
	 * $model, $field [, $join = FALSE]
	 * $model_name, $field [, $join = FALSE]
	 * $model_plus_field [, $join = FALSE]
	 * 
	 * In the first case, $model is a Jelly model and $field is a string.
	 * In the second case, $model is a string, and $field is a string.
	 * In the third case, $model_plus_field is a string in the format of 'model.field'.
	 * 
	 * If the model cannot be found in the registry (or registered), the method will make
	 * every reasonable attempt to return something valid. This allows you to pass
	 * tables and fields and still expect something reasonable back.
	 *
	 * @param mixed $model
	 * @param mixed $field
	 * @param mixed $join
	 * @return string
	 * @author Jonathan Geiger
	 */
	public static function column($model, $field = FALSE, $join = FALSE)
	{
		// Accept either a jelly or a string in the format of model.field
		if ($model instanceof Jelly || (is_string($model) && is_string($field)))
		{			
			$model = Jelly_Meta::model_name($model);
			$column = $field;
		}
		else
		{
			// If the args are coming in without a Jelly, $model 
			// must be in the format of model.field and $field is $
			$join = $field;
			$field = $model;
			
			// Can't find anything if we don't have a model
			if (strpos($field, '.') === FALSE)
			{			
				return $field;
			}

			list($model, $column) = explode('.', $field);
		}
		
		
		if ($meta = Jelly_Meta::get($model))
		{
			if ($column != '*' && isset($meta->fields[$column]))
			{
				$column = $meta->fields[$column]->column;
			}
			
			// Ensure the model is aliased as well
			$model = $meta->table;
		}
		
		if ($join)
		{
			return $model.'.'.$column;
		}
		else
		{
			return $column;
		}
	}
	
	
	/**
	 * @var string If this is FALSE, properties can still be set on it
	 */
	private $initialized = FALSE;
	
	/**
	 * @var string The database key to use for connection
	 */
	protected $db = 'default';
	
	/**
	 * @var string The table this model represents
	 */
	protected $table = '';
	
	/**
	 * @var string The primary key
	 */
	protected $primary_key = '';
	
	/**
	 * @var string The title key
	 */
	protected $name_key = 'name';
	
	/**
	 * @var array An array of ordering options for selects
	 */
	protected $sorting = array();
	
	/**
	 * @var array An array of options to pass to with for every load()
	 */
	protected $load_with = array();
	
	/**
	 * @var boolean Whether or not to validate before save()ing
	 */
	protected $validate_on_save = TRUE;
	
	/**
	 * @var array A map to the resource's fields and how to process each column.
	 */
	protected $fields = array();
	
	/**
	 * @var array A list of columns and how they relate to fields
	 */
	private $columns = array();
	
	/**
	 * @var array Default data for each field
	 */
	private $defaults = array();
	
	/**
	 * Constructor. Meta fields cannot be instantiated directly.
	 *
	 * @param string $model 
	 * @author Jonathan Geiger
	 */
	protected function __construct($model)
	{
		// Table should be a sensible default
		if (empty($this->table))
		{
			$this->table = inflector::plural($model);
		}
	}
	
	/**
	 * Opens up access only when initializing. 
	 * After that the Meta object is read-only.
	 *
	 * @param string $name 
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function __set($name, $value)
	{
		if (!$this->initialized)
		{
			$this->$name = $value;
		}
	}
	
	/**
	 * Allow directly retrieving properties, which 
	 * is useful for things like array access.
	 *
	 * @param string $name 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function __get($name)
	{
		return $this->$name;
	}
}