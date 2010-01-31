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
	 * Gets a particular set of metadata about a model
	 *
	 * @return void
	 * @author Jonathan Geiger
	 */
	public static function get($model)
	{
		$model = strtolower($model);
		
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
		$class = 'model_'.$model;
				
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
		
		// Initialize all of the fields with their column and the model name
		foreach($meta->fields as $column => $field)
		{
			$field->initialize($model, $column);
			
			// Ensure a default primary key is set
			if ($field->primary)
			{
				$meta->primary_key = $column;
			}
			
			// Set the defaults so they're actually persistent
			$meta->defaults[$column] = $field->default;
		}
		
		return TRUE;
	}
	
	/**
	 * @var string The database key to use for connection
	 */
	public $db = 'default';
	
	/**
	 * @var string The table this model represents
	 */
	public $table = '';
	
	/**
	 * @var string The name of the model
	 */
	public $model = '';
	
	/**
	 * @var string The primary key
	 */
	public $primary_key = '';
	
	/**
	 * @var string The title key
	 */
	public $name_key = 'name';
	
	/**
	 * @var array An array of ordering options for selects
	 */
	public $sorting = array();
	
	/**
	 * @var array A map to the resource's data and how to process each column.
	 */
	public $fields = array();
	
	/**
	 * Constructor. Meta fields cannot be instantiated directly.
	 *
	 * @param string $model 
	 * @author Jonathan Geiger
	 */
	protected function __construct($model)
	{
		$this->model = $model;
		
		// Table should be a sensible default
		if (empty($this->table))
		{
			$this->table = inflector::plural($model);
		}
	}	
}