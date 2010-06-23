<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Meta objects act as a registry of information about a particular model.
 *
 * @package Jelly
 */
abstract class Jelly_Core_Meta
{
	/**
	 * @var  boolean  If this is FALSE, properties can still be set on the meta object
	 */
	protected $_initialized = FALSE;

	/**
	 * @var  string  The model this meta object belongs to
	 */
	protected $_model = NULL;

	/**
	 * @var  string  The database key to use for connection
	 */
	protected $_db = 'default';

	/**
	 * @var  string  The table this model represents, defaults to the model name pluralized
	 */
	protected $_table = '';

	/**
	 * @var  string  The primary key, defaults to the first Field_Primary found.
	 *               This can be referenced in query building as :primary_key
	 */
	protected $_primary_key = '';

	/**
	 * @var  string  The title key. This can be referenced in query building as :name_key
	 */
	protected $_name_key = 'name';

	/**
	 * @var  string  The foreign key for use in other tables. This can be referenced in query building as :foreign_key
	 */
	protected $_foreign_key = '';

	/**
	 * @var  array  An array of ordering options for SELECTs
	 */
	protected $_sorting = array();

	/**
	 * @var  array  An array of 1:1 relationships to pass to with() for every SELECT
	 */
	protected $_load_with = array();

	/**
	 * @var  array  A map to the models's fields and how to process each column.
	 */
	protected $_fields = array();

	/**
	 * @var  array  A map of aliases to fields
	 */
	protected $_aliases = array();

	/**
	 * @var  string  The builder class the model is associated with. This defaults to
	 *               Jelly_Builder_Modelname, if that particular class is found.
	 */
	protected $_builder = '';
	
	/**
	 * @var  string  The validator attached to the object
	 */
	protected $_validator = NULL;

	/**
	 * @var  array  A list of columns and how they relate to fields
	 */
	protected $_columns = array();

	/**
	 * @var  array  Default data for each field
	 */
	protected $_defaults = array();

	/**
	 * @var  array  A cache of retrieved fields, with aliases resolved
	 */
	protected $_field_cache = array();
	
	/**
	 * @var  array  Behaviors attached to this model
	 */
	protected $_behaviors = array();

	/**
	 * This is called after initialization to
	 * finalize any changes to the meta object.
	 *
	 * @return  void
	 */
	public function finalize($model)
	{
		if ($this->_initialized)
			return;
			
		// Hand over the behaviors to the collection manager
		$this->_behaviors = new Jelly_Behavior($this->_behaviors, $model);

		// Allow modification of this meta object by the behaviors
		$this->_behaviors->before_meta_finalize($this);
		
		// Ensure certain fields are not overridden
		$this->_model       = $model;
		$this->_columns     =
		$this->_defaults    =
		$this->_field_cache =
		$this->_aliases     = array();

		// Table should be a sensible default
		if (empty($this->_table))
		{
			$this->_table = inflector::plural($model);
		}

		// See if we have a special builder class to use
		if (empty($this->_builder))
		{
			$builder = Jelly::model_prefix().'builder_'.$model;

			if (class_exists($builder))
			{
				$this->_builder = $builder;
			}
			else
			{
				$this->_builder = 'Jelly_Builder';
			}
		}

		// Can we set a sensible foreign key?
		if (empty($this->_foreign_key))
		{
			$this->_foreign_key = $model.'_id';
		}
		
		// Initialize all of the fields with their column and the model name
		foreach($this->_fields as $column => $field)
		{
			// Allow aliasing fields
			if (is_string($field))
			{
				if (isset($this->_fields[$field]))
				{
					$this->_aliases[$column] = $field;
				}

				// Aliases shouldn't pollute fields
				unset($this->_fields[$column]);

				continue;
			}

			$field->initialize($model, $column);

			// Ensure a default primary key is set
			if ($field->primary AND empty($this->_primary_key))
			{
				$this->_primary_key = $column;
			}

			// Set the defaults so they're actually persistent
			$this->_defaults[$column] = $field->default;

			// Set the columns, so that we can access reverse database results properly
			if ( ! array_key_exists($field->column, $this->_columns))
			{
				$this->_columns[$field->column] = array();
			}

			$this->_columns[$field->column][] = $column;
		}

		// Meta object is initialized and no longer writable
		$this->_initialized = TRUE;
		
		// Final meta callback
		$this->_behaviors->after_meta_finalize($this);
	}
	
	/**
	 * Returns a string representation of the meta object.
	 *
	 * @return  string
	 */
	public function __toString()
	{
		return (string) get_class($this).': '.$this->_model;
	}

	/**
	 * Returns whether or not the meta object has finished initialization
	 *
	 * @return  boolean
	 */
	public function initialized()
	{
		return $this->_initialized;
	}

	/**
	 * Allows setting a variable only when initialized
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 * @return  $this
	 */
	protected function set($key, $value)
	{
		if ( ! $this->_initialized)
		{
			$this->{'_'.$key} = $value;
		}

		return $this;
	}

	/**
	 * Gets or sets the db group
	 * @param   string  $value
	 * @return  string|$this
	 */
	public function db($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('db', $value);
		}

		return $this->_db;
	}

	/**
	 * Returns the model name this object is attached to
	 * @return  string
	 */
	public function model()
	{
		return $this->_model;
	}

	/**
	 * Gets or sets the table
	 * @param   string  $value
	 * @return  string|$this
	 */
	public function table($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('table', $value);
		}

		return $this->_table;
	}

	/**
	 * Gets or sets the builder attached to this object
	 * @param   string  $value
	 * @return  string|$this
	 */
	public function builder($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('builder', $value);
		}

		return $this->_builder;
	}
	
	/**
	 * Getter/setter for individual fields
	 * 
	 * @param   $name     string
	 * @param   $type     string
	 * @param   $options  mixed
	 */
	public function field($name, $type = FALSE, $options = array())
	{
		// If $type is boolean, we're searching for a field
		if (is_bool($type))
		{
			if ( ! isset($this->_field_cache[$name]))
			{
				$resolved_name = $name;

				if (isset($this->_aliases[$name]))
				{
					$resolved_name = $this->_aliases[$name];
				}

				if (isset($this->_fields[$resolved_name]))
				{
					$this->_field_cache[$name] = $this->_fields[$resolved_name];
				}
				else
				{
					return NULL;
				}
			}

			if ($type)
			{
				return $this->_field_cache[$name]->name;
			}
			else
			{
				return $this->_field_cache[$name];
			}
			
			return NULL;
		}
		
		// If we've made it here it's a standard setter
		if ( ! $this->_initialized)
		{
			// Allows fields to be appended
			$this->_fields[$name] = Jelly::field($type, $options);
			
			return $this;
		}
	}

	/**
	 * Returns the fields for this object.
	 *
	 * If $field is specified, only the particular field is returned.
	 * If $name is TRUE, the name of the field specified is returned.
	 *
	 * You can pass an array for $field to set more fields. Calling
	 * this multiple times while setting will append fields, not
	 * overwrite fields.
	 *
	 * @param   $field  string
	 * @param   $name   boolean
	 * @return  array
	 */
	public function fields($field = NULL, $name = FALSE)
	{
		if (func_num_args() == 0)
		{
			return $this->_fields;
		}

		if (is_array($field))
		{
			if ( ! $this->_initialized)
			{
				// Allows fields to be appended
				$this->_fields += $field;
				return $this;
			}
		}
	}

	/**
	 * Returns all of the columns for this meta object.
	 *
	 * Each key in the array is a column's name, while the value
	 * is an array of fields the column maps to.
	 *
	 * If $name is specified, only the particular column is returned.
	 *
	 * @param   string  $name
	 * @return  array
	 */
	public function columns($name = NULL)
	{
		if (func_num_args() == 0)
		{
			return $this->_columns;
		}

		if (isset($this->_columns[$name]))
		{
			return $this->_columns[$name];
		}
	}

	/**
	 * Returns the defaults for the object.
	 *
	 * If $name is specified, then the defaults
	 * for that field are returned.
	 *
	 * @param   string  $name
	 * @return  mixed
	 */
	public function defaults($name = NULL)
	{
		if ($name === NULL)
		{
			return $this->_defaults;
		}

		return $this->field($name)->default;
	}
	
	/**
	 * Gets the validator attached to the model.
	 * 
	 * @param   Jelly_Model $model 
	 * @param   array       $data 
	 * @param   boolean     $new 
	 * @return  Jelly_Validator
	 */
	public function validator(array $data, $new = FALSE)
	{
		// Allow returning an empty validator
		if ($new) 
		{
			return new Jelly_Validator($data);
		}
		
		// Create a default validator so we don't have to go through
		// recreating all of the filters and such, which is an expensive process.
		if ( ! $this->_validator)
		{
			// Create our default validator, which we will clone from
			$this->_validator = new Jelly_Validator($data);
			
			// Add our filters, rules, and callbacks
			foreach ($this->_fields as $name => $field)
			{
				$this->_validator->label($name, $field->label);
				$this->_validator->filters($name, $field->filters);
				$this->_validator->rules($name, $field->rules);
				$this->_validator->callbacks($name, $field->callbacks);
			}
		}
		
		// Return a copy to prevent mucking with the original validator
		return $this->_validator->copy($data);
	}
	
	/**
	 * Gets or sets the behaviors attached to the object.
	 * 
	 * @param   array  $value
	 * @return  Jelly_Behavior|$this
	 */
	public function behaviors($behaviors = NULL)
	{
		if (func_num_args() == 0 OR $this->_initialized)
		{
			return $this->_behaviors;
		}

		if (is_array($behaviors))
		{
			// Allows behaviors to be appended
			$this->_behaviors += $behaviors;
		}
		
		return $this;
	}

	/**
	 * Gets or sets the model's primary key.
	 * @param   string  $value
	 * @return  mixed
	 */
	public function primary_key($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('primary_key', $value);
		}

		return $this->_primary_key;
	}

	/**
	 * Gets or sets the model's name key
	 * @param   string  $value
	 * @return  string
	 */
	public function name_key($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('name_key', $value);
		}

		return $this->_name_key;
	}

	/**
	 * Gets or sets the model's foreign key
	 * @param   string  $value
	 * @return  string
	 */
	public function foreign_key($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('foreign_key', $value);
		}

		return $this->_foreign_key;
	}

	/**
	 * Gets or sets the object's sorting properties
	 * @param   array  $value
	 * @return  array
	 */
	public function sorting($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('sorting', $value);
		}

		return $this->_sorting;
	}

	/**
	 * Gets or sets the object's load_with properties
	 * @param   array  $value
	 * @return  array
	 */
	public function load_with($value = NULL)
	{
		if (func_num_args() !== 0)
		{
			return $this->set('load_with', $value);
		}

		return $this->_load_with;
	}
}
