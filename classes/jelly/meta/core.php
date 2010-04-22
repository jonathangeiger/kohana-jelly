<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Meta objects act as a registry of information about a particular model.
 *
 * @package Jelly
 */
abstract class Jelly_Meta_Core
{
	/**
	 * @var  boolean  If this is FALSE, properties can still be set on the meta object
	 */
	protected $initialized = FALSE;

	/**
	 * @var  string  The model this meta object belongs to
	 */
	protected $model = NULL;

	/**
	 * @var  string  The database key to use for connection
	 */
	protected $db = 'default';

	/**
	 * @var  string  The table this model represents, defaults to the model name pluralized
	 */
	protected $table = '';

	/**
	 * @var  string  The primary key, defaults to the first Field_Primary found.
	 *               This can be referenced in query building as :primary_key
	 */
	protected $primary_key = '';

	/**
	 * @var  string  The title key. This can be referenced in query building as :name_key
	 */
	protected $name_key = 'name';

	/**
	 * @var  string  The foreign key for use in other tables. This can be referenced in query building as :foreign_key
	 */
	protected $foreign_key = '';

	/**
	 * @var  array  An array of ordering options for SELECTs
	 */
	protected $sorting = array();

	/**
	 * @var  array  An array of 1:1 relationships to pass to with() for every SELECT
	 */
	protected $load_with = array();

	/**
	 * @var  string  Prefix to apply to input view generation
	 */
	protected $input_prefix = 'jelly/field';

	/**
	 * @var  array  A map to the models's fields and how to process each column.
	 */
	protected $fields = array();

	/**
	 * @var  array  A map of aliases to fields
	 */
	protected $aliases = array();

	/**
	 * @var  string  The builder class the model is associated with. This defaults to
	 *               Jelly_Builder_Modelname, if that particular class is found.
	 */
	protected $builder = '';

	/**
	 * @var  array  A list of columns and how they relate to fields
	 */
	protected $columns = array();

	/**
	 * @var  array  Default data for each field
	 */
	protected $defaults = array();

	/**
	 * @var  array  A cache of retrieved fields, with aliases resolved
	 */
	protected $field_cache = array();

	/**
	 * This is called after initialization to
	 * finalize any changes to the meta object.
	 *
	 * @return  void
	 */
	public function finalize($model)
	{
		if ($this->initialized)
			return;

		// Ensure certain fields are not overridden
		$this->model = $model;
		$this->columns     =
		$this->defaults    =
		$this->field_cache =
		$this->aliases     = array();

		// Table should be a sensible default
		if (empty($this->table))
		{
			$this->table = inflector::plural($model);
		}

		// See if we have a special builder class to use
		if (empty($this->builder))
		{
			$builder = Jelly::model_prefix().'builder_'.$model;

			if (class_exists($builder))
			{
				$this->builder = $builder;
			}
			else
			{
				$this->builder = 'Jelly_Builder';
			}
		}

		// Can we set a sensible foreign key?
		if (empty($this->foreign_key))
		{
			$this->foreign_key = $model.'_id';
		}

		// Initialize all of the fields with their column and the model name
		foreach($this->fields as $column => $field)
		{
			// Allow aliasing fields
			if (is_string($field))
			{
				if (isset($this->fields[$field]))
				{
					$this->aliases[$column] = $field;
				}

				// Aliases shouldn't pollute fields
				unset($this->fields[$column]);

				continue;
			}

			$field->initialize($model, $column);

			// Ensure a default primary key is set
			if ($field->primary AND empty($this->primary_key))
			{
				$this->primary_key = $column;
			}

			// Set the defaults so they're actually persistent
			$this->defaults[$column] = $field->default;

			// Set the columns, so that we can access reverse database results properly
			if ( ! array_key_exists($field->column, $this->columns))
			{
				$this->columns[$field->column] = array();
			}

			$this->columns[$field->column][] = $column;
		}

		// Meta object is initialized and no longer writable
		$this->initialized = TRUE;
	}

	/**
	 * Allows dynamic retrieval of members when initializing
	 *
	 * @param   string  $key
	 * @return  void
	 */
	public function __get($key)
	{
		if ( ! $this->initialized)
		{
			return $this->$key;
		}
	}

	/**
	 * Allows dynamic setting of members when initializing
	 *
	 * @param   string  $key
	 * @return  void
	 */
	public function __set($key, $value)
	{
		$this->set($key, $value);
	}

	/**
	 * Returns whether or not the meta object has finished initialization
	 *
	 * @return  boolean
	 */
	public function initialized()
	{
		return $this->initialized;
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
		if ( ! $this->initialized)
		{
			$this->$key = $value;
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

		return $this->db;
	}

	/**
	 * Returns the model name this object is attached to
	 * @return  string
	 */
	public function model()
	{
		return $this->model;
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

		return $this->table;
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

		return $this->builder;
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
			return $this->fields;
		}

		if (is_array($field))
		{
			if ( ! $this->initialized)
			{
				// Allows fields to be appended
				$this->fields += $field;
				return $this;
			}
		}

		if ( ! isset($this->field_cache[$field]))
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
	 *
	 * @param   string  $name
	 * @return  array
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
			return $this->defaults;
		}

		return $this->fields($name)->default;
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

		return $this->primary_key;
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

		return $this->name_key;
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

		return $this->foreign_key;
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

		return $this->sorting;
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

		return $this->load_with;
	}

	/**
	 * Gets or sets the object's input prefix
	 * @param   string  $value
	 * @return  string
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
