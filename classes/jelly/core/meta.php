<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Meta objects act as a registry of information about a particular model.
 *
 * @package Jelly
 */
abstract class Jelly_Core_Meta
{
	/**
	 * @var  array  Cache of all meta instances that are attached to a real model
	 */
	protected static $_instances = array();
	
	/**
	 * Singleton for finding meta objects.
	 *
	 * @param   string       $model 
	 * @return  Jelly_Meta 
	 */
	public static function instance($model)
	{
		$class = strtolower(Jelly::$model_prefix.$model); 
		
		if ( ! isset(Jelly_Meta::$_instances[$class]))
		{
			$meta = new Jelly_Meta($model);
			
			if (class_exists($class) AND is_subclass_of($class, 'Jelly_Model'))
			{
				call_user_func(array($class, 'initialize'), $meta);
				
				// Only cache if this is a known class instance
				Jelly_Meta::$_instances[$class] = $meta;
			}
			
			$meta->finalize();
		}
		else
		{
			$meta = Jelly_Meta::$_instances[$class];
		}

		return $meta;
	}

	/**
	 * @var  string  The database key to use for connection
	 */
	public $db;
	
	/**
	 * @var  string  The model this meta object belongs to
	 */
	public $model;
	
	/**
	 * @var  string  The class name of the model
	 */
	public $class;

	/**
	 * @var  string  The table this model represents, defaults to the model name pluralized
	 */
	public $table;
	
	/**
	 * @var  array  A map to the models's fields and how to process each column.
	 */
	public $fields = array();
	
	/**
	 * @var  array  A map of aliases to fields
	 */
	public $aliases = array();
	
	/**
	 * @var  array  Default data for each field
	 */
	public $defaults = array();
	
	/**
	 * @var  string  The manager class the model is associated with.
	 */
	public $manager;

	/**
	 * @var  string  The primary key, defaults to the first primary field found.
	 */
	public $primary_key;

	/**
	 * @var  string  The name key. Defaults to the first unique string field found.
	 */
	public $name_key;

	/**
	 * @var  string  The foreign key for use in other tables. This can be referenced in query building as :foreign_key
	 */
	public $foreign_key;
	
	/**
	 * @var  string  The polymorphic key for the model tree.
	 */
	public $polymorphic_key;
	
	/**
	 * @var  string  The different validation contexts
	 */
	public $validate = array();
	
	/**
	 * @var  string  The different validation filters
	 */
	public $filters = array();
	
	/**
	 * @var  string  The different validation rules
	 */
	public $rules = array();
	
	/**
	 * @var  string  The different validation callbacks
	 */
	public $callbacks = array();
	
	/**
	 * @var  array  Events attached to this model
	 */
	public $events = array();
	
	/**
	 * @var  array  Behaviors attached to this model
	 */
	public $behaviors = array();
	
	/**
	 * @var  array  An array of this model's children
	 */
	public $children = array();
	
	/**
	 * @var  string  The parent model of this model
	 */
	public $parent = NULL;

	/**
	 * @var  array  A cache of retrieved fields, with aliases resolved
	 */
	protected $_field_cache = array();

	/**
	 * Constructor. 
	 *
	 * @param   string  $model 
	 */
	public function __construct($model)
	{
		$this->model = $model;
		$this->class = strtolower(Jelly::$model_prefix.$model);
	}
	
	/**
	 * This is called after initialization to
	 * finalize any changes to the meta object.
	 *
	 * @return  void
	 */
	public function finalize()
	{	
		$model = $this->model;
		
		if ( ! class_exists(Jelly::$model_prefix.$this->model))
		{
			$this->model = NULL;
		}
		
		if (empty($this->events))
		{
			$this->events = new Jelly_Event($this->model);
			
			foreach ($this->behaviors as $name => $behavior)
			{
				$behavior->initialize($this, $this->model, $name);
			}
		}

		if (empty($this->table))
		{
			$this->table = $this->model ? inflector::plural($model) : $this->model;
		}

		if (empty($this->manager))
		{
			if (class_exists(Jelly::$manager_prefix.$model))
			{
				$this->manager = Jelly::$manager_prefix.$model;
			}
			else
			{
				$this->manager = 'Jelly_Manager';
			}
		}

		if ($this->model AND empty($this->foreign_key))
		{
			$this->foreign_key = $this->model.'_id';
		}
		
		foreach ($this->fields as $name => $field)
		{
			if (is_string($field))
			{
				if (isset($this->fields[$field]))
				{
					$this->aliases[$name] = $field;
				}

				unset($this->fields[$name]);
				continue;
			}

			$field->initialize($model, $name);

			if (empty($this->primary_key) AND $field->primary)
			{
				$this->primary_key = $name;
			}
			
			if (empty($this->name_key) AND $field->unique AND $field instanceof Jelly_Field_String)
			{
				$this->name_key = $name;
			}
			
			if (empty($this->polymorphic_key) AND ! empty($field->polymorphic))
			{
				$this->polymorphic_key = $name;
				
				if ( ! in_array($model, $this->children))	
				{
					$this->children[] = $model;
				}
			}

			$this->defaults[$name] = $field->default;
		}
		
		$this->events->trigger('meta.after_finalize', $this);
	}
	
	/**
	 * Returns a string representation of the meta object.
	 *
	 * @return  string
	 */
	public function __toString()
	{
		return (string) get_class($this).': '.$this->model;
	}
	
	/**
	 * Gets a field object by its name or alias.
	 * 
	 * @param   $name     string
	 * @return  Jelly_Field
	 */
	public function field($name)
	{
		if ( ! isset($this->_field_cache[$name]))
		{
			if (isset($this->aliases[$name]))
			{
				$name = $this->aliases[$name];
			}

			if (isset($this->fields[$name]))
			{
				$this->_field_cache[$name] = $this->fields[$name];
			}
			else
			{
				return NULL;
			}
		}
		
		return $this->_field_cache[$name];
	}
}