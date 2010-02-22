<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Defines meta data for a particular model
 *
 * @package Jelly
 * @author Jonathan Geiger
 */
abstract class Jelly_Core_Meta
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
	public static function get($model)
	{
		$model = Jelly_Meta::model_name($model);
		
		if (!isset(Jelly_Meta::$_models[$model]))
		{
			if (!Jelly_Meta::register($model))
			{
				return FALSE;
			}
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
				
		// Meta object is initialized and no longer writable
		$meta->initialized = TRUE;
		
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
	 * @var string If this is FALSE, properties can still be set on it
	 */
	protected $initialized = FALSE;
	
	/**
	 * @var string The model this meta object belongs to
	 */
	protected $model = NULL;
	
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
	 * @var string Prefix to apply to input generation
	 */
	protected $input_prefix = 'jelly/field';
	
	/**
	 * @var array A map to the resource's fields and how to process each column.
	 */
	protected $fields = array();
	
	/**
	 * @var array A map of aliases to fields
	 */
	protected $aliases = array();
	
	/**
	 * @var array A list of columns and how they relate to fields
	 */
	protected $columns = array();
	
	/**
	 * @var array Default data for each field
	 */
	protected $defaults = array();
	
	/**
	 * @var array A cache of retrieved fields, with aliases resolved
	 */
	protected $field_cache = array();
	
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
		
		$this->model = $model;
	}
	
	/**
	 * Allows dynamic retrieval of members when initializing
	 *
	 * @param string $key 
	 * @return void
	 * @author Expressway Video
	 */
	public function __get($key)
	{
		if (!$this->initialized)
		{
			return $this->$key;
		}
	}
	
	/**
	 * Allows dynamic setting of members when initializing
	 *
	 * @param string $key 
	 * @return void
	 * @author Expressway Video
	 */
	public function __set($key, $value)
	{
		$this->set($key, $value);
	}
	
	/**
	 * Allows setting a variable only when initalized
	 *
	 * @param  string $key 
	 * @param  string $value 
	 * @return $this
	 * @author Expressway Video
	 */
	protected function set($key, $value)
	{
		if (!$this->initialized)
		{
			$this->$key = $value;
		}
		
		return $this;
	}
	
	/**
	 * Returns the meta object's model
	 * @return string
	 */
	public function model()
	{
		return $this->model;
	}
	
	/**
	 * Returns the meta object db group
	 * @return string
	 */
	public function db($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('db', $db);
		}
		
		return $this->db;
	}
	
	/**
	 * Returns the meta object's table
	 * @return string
	 */
	public function table($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('table', $value);
		}
		
		return $this->table;
	}
	
	/**
	 * Returns the fields for this object.
	 * 
	 * If $field is specified, only the particular field is returned.
	 * If $name is TRUE, the name of the field specified is returned.
	 * @return array
	 */
	public function fields($field = NULL, $name = FALSE)
	{
		if (func_num_args() == 0)
		{
			return $this->fields;
		}
		
		if (is_array($field))
		{
			return $this->set('fields', $field);
		}
		
		if (!isset($this->field_cache[$field]))
		{
			$resolved_name = $field;
			
			if (isset($this->aliases[$field]))
			{
				$resolved_name = $this->aliases[$field];
			}
			
			if (isset($this->fields[$resolved_name]))
			{
				$this->field_cache[$field] = $this->fields[$resolved_name];
			}
			else
			{
				return NULL;
			}
		}
		
		if ($name)
		{
			return $this->field_cache[$field]->name;
		}
		else
		{
			return $this->field_cache[$field];
		}
	}
	
	/**
	 * Returns all of the columns for this meta object.
	 * 
	 * Each key in the array is a column's name, while the value
	 * is an array of fields the column maps to.
	 * 
	 * If $name is specified, only the particular column is returned.
	 * @return array
	 */
	public function columns($name = NULL)
	{
		if (func_get_args() == 0)
		{
			return $this->columns;
		}
		
		if (isset($this->columns[$name]))
		{
			return $this->columns[$name];
		}
	}
	
	/**
	 * Returns the defaults for the object.
	 * 
	 * If $name is specified, then 
	 *
	 * @param string $field 
	 * @return void
	 * @author Expressway Video
	 */
	public function defaults($field = NULL)
	{
		if ($field === NULL)
		{
			return $this->defaults;
		}
		
		return $this->field($field)->default;
	}
	
	/**
	 * Returns the object's primary key
	 * @return mixed
	 */
	public function primary_key($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('primary_key', $value);
		}
		
		return $this->primary_key;
	}
	
	/**
	 * Returns the object's name key
	 * @return string
	 */
	public function name_key($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('name_key', $value);
		}
		
		return $this->name_key;
	}
	
	/**
	 * Returns the object's sorting properties
	 * @return array
	 */
	public function sorting($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('sorting', $value);
		}
			
		return $this->sorting;
	}
	
	/**
	 * Returns the object's load_with properties
	 * @return array
	 */
	public function load_with($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('load_with', $value);
		}
		
		return $this->load_with;
	}
	
	/**
	 * Returns the object's input prefix
	 * @return string
	 */
	public function input_prefix($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('input_prefix', $value);
		}
		
		return $this->input_prefix;
	}
}