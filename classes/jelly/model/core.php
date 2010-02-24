<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Model is the class all models must extend. It handles loading a single 
 * record based on a primary key or simple where clause and allows that record
 * to have various CRUD operations, and other more complex actions performed on it.
 * 
 * @package Jelly
 */
abstract class Jelly_Model_Core
{
	/**
	 * @var array The original data set on the object
	 */
	protected $_original = array();
	
	/**
	 * @var array Data that's changed since the object was loaded
	 */
	protected $_changed = array();
	
	/**
	 * @var array Data that's already been retrieved is cached
	 */
	protected $_retrieved = array();
	
	/**
	 * @var array Unmapped data that is still accessible
	 */
	protected $_unmapped = array();

	/**
	 * @var boolean Whether or not the model is loaded
	 */
	protected $_loaded = FALSE;
	
	/**
	 * @var boolean Whether or not the model is saved
	 */
	protected $_saved = FALSE;
	
	/**
	 * @var Jelly_Meta a copy of this object's meta object
	 */
	protected $_meta = NULL;
	
	/**
	 * @var array Data set by mysql_fetch_object. Daggers to ye who overloads this.
	 */
	protected $_preload_data = array();
	
	/**
	 * @var array With data
	 */
	protected $_with = array();

	/**
	 * An optional conditional can be passed. If it is an integer 
	 * or a string, it will be assumed to be a primary key and 
	 * the record will be loaded automatically into the model.
	 * If it is an associative array, it is used in constructing 
	 * a where clause and the record is loaded automatically.
	 *
	 * @param	mixed  $cond  A primary key or where clause to use for auto-loading a particular record
	 **/
	public function __construct($cond = NULL)
	{
		// Load the object's meta data for quick access
		$this->_meta = Jelly::meta($this);
		
		// Copy over the defaults into the original data. This also has 
		// the added benefit of registering the model's metadata, if it does not exist yet
		$this->_original = $this->_meta->defaults();
		
		// Add the values stored by mysql_set_object
		if ( ! empty($this->_preload_data) AND is_array($this->_preload_data))
		{
			$this->values($this->_preload_data, TRUE);
			$this->_preload_data = array();
		}
				
		// Have an id? Attempt to load it
		if ($cond)
		{
			// Arrays are loaded as values, but not load()ed
			if (is_array($cond))
			{
				$this->values($cond);
			}
			else
			{
				$this->load($cond);
			}
		}
	}
	
	/**
	 * Returns field values as members of the object. 
	 * 
	 * A few things to note:
	 * 
	 * * Values that are returned are cached (unlike get()) until they are changed
	 * * Relations are automatically load()ed
	 *
	 * @see	   get()
	 * @param  string $name 
	 * @return mixed
	 */
	public function __get($name)
	{	
		// Alias the field to its actual name. We must do this now
		// so that any aliases will be cached under the real fields
		// name, rather than under its alias name
		$name = $this->_meta->fields($name, TRUE);
		
		if ( ! array_key_exists($name, $this->_retrieved))
		{
			$value = $this->get($name);
			
			// Auto-load relations
			if ($value instanceof Jelly_Builder)
			{
				$value = $value->execute();
			}
			
			$this->_retrieved[$name] = $value;
		}
		
		return $this->_retrieved[$name];
	}
	
	/**
	 * Allows members to be set on the object.
	 * 
	 * Under the hood, this is just proxying to set()
	 *
	 * @see	   set()
	 * @param  string $name 
	 * @param  mixed $value 
	 * @return void
	 */
	public function __set($name, $value)
	{
		// Being set by mysql_fetch_object, store the values for the constructor
		if (empty($this->_original))
		{
			$this->_preload_data[$name] = $value;
			return;
		}
		
		$this->set($name, $value);
	}
	
	/**
	 * Returns true if $name is a field of the model or an unmapped column.
	 *
	 * @param  string	$name 
	 * @return boolean
	 */
	public function __isset($name)
	{
		return (bool)($this->_meta->fields($name) OR array_key_exists($name, $this->_unmapped));
	}
	
	/**
	 * This doesn't unset fields. Rather, it sets them to their default 
	 * value. Unmapped, changed, and retrieved values are unset.
	 * 
	 * In essence, unsetting a field sets it as if you never made any changes 
	 * to it, and clears the cache if the value has been retrieved with those changes.
	 *
	 * @param  string $name 
	 * @return void
	 */
	public function __unset($name)
	{
		if ($field = $this->_meta->fields($name, TRUE))
		{
			// We don't want to unset the keys, because 
			// they are assumed to exist. Just set them back to defaults
			$this->_original[$field] = $this->_meta->defaults($field);
			
			// Ensure changed and retrieved data is cleared
			// This effectively clears the cache and any changes
			if (array_key_exists($name, $this->_changed))
			{
				unset($this->_changed[$name]);
			}
			
			if (array_key_exists($name, $this->_retrieved))
			{
				unset($this->_retrieved[$name]);
			}
		}
		
		// This doesn't matter
		if (array_key_exists($name, $this->_unmapped))
		{
			unset($this->_unmapped[$name]);
		}
	}
	
	/**
	 * Gets the internally represented value from a field or unmapped column.
	 *
	 * @param	mixed    $name	   The field's name
	 * @param	boolean	 $changed
	 * @return	mixed
	 */
	public function get($name)
	{	
		if ($field = $this->_meta->fields($name))
		{	
			// Alias the name to its actual name
			$name = $field->name;
			
			if (array_key_exists($name, $this->_changed))
			{	
				$value = $field->get($this, $this->_changed[$name]);
			}
			elseif (array_key_exists($name, $this->_with))
			{
				$value = Jelly::factory($field->foreign['model'])->values($this->_with[$name]);
				
				// Try and verify that it's actually loaded
				if (!$value->id())
				{
					$value->_loaded = FALSE;
					$value->_saved = FALSE;
				}
			}
			else
			{
				$value = $field->get($this, $this->_original[$name]);
			}
			
			return $value;
		}
		// Return unmapped data from custom queries
		elseif (isset($this->_unmapped[$name]))
		{
			return $this->_unmapped[$name];
		}
	}
	
	/**
	 * Returns an array of values in the fields 
	 *
	 * @param  string $fields 
	 * @param  ...
	 * @return array
	 */
	public function as_array($fields = NULL)
	{
		$fields = func_num_args() ? func_get_args() : array_keys($this->_meta->fields());
		$result = array();
		
		foreach($fields as $field)
		{
			$result[$field] = $this->__get($field);
		}
		
		return $result;
	}
	
	/**
	 * Sets values in the fields. Everything passed to this 
	 * is converted to an internally represented value.
	 * 
	 * The conversion is done in the field and returned.
	 * 
	 * A few things to note:
	 * 
	 *	- If $values is a string, $alias will be used as the value 
	 *	  and $alias will be set to False.
	 *	- If $original is TRUE, the data will be set as original 
	 *	  (not changed) as if it came from the database.
	 *
	 * @param  string  $name 
	 * @param  string  $value 
	 * @return Jelly   Returns $this
	 */
	public function set($values, $value = NULL)
	{
		// Accept set('name', 'value');
		if ( ! is_array($values))
		{
			$values = array($values => $value);
		}
		
		foreach($values as $key => $value)
		{
			$field = $this->_meta->fields($key);
			$value = $field->set($value);
			
			// Ensure data is really changed
			if ($field->in_db AND $this->_original[$field->name] == $value)
			{
				continue;
			}
			
			// Data has changed
			$this->_changed[$field->name] = $field->set($value);
			
			// Invalidate the cache
			if (array_key_exists($field->name, $this->_retrieved))
			{
				unset($this->_retrieved[$field->name]);
			}
			
			// Model is no longer saved
			$this->_saved = FALSE;
		}
		
		return $this;
	}
	
	/**
	 * Clears the object and loads an array of values into the object.
	 * 
	 * This should only be used for setting from database results 
	 * since the model declares itself as saved and loaded after.
	 *
	 * @param  array   $values 
	 * @param  boolean $alias 
	 * @return void
	 */
	public function values(array $values, $alias = FALSE)
	{
		// Clear the object
		$this->clear();
		
		foreach($values as $key => $value)
		{
			// Key is coming from a with statement
			if (substr($key, 0, 1) === ':')
			{
				$targets = explode(':', ltrim($key, ':'), 2);

				// Alias as it comes back in, which allows people to use with()
				// with alaised field names
				$relationship = $this->_meta->fields(array_shift($targets), TRUE);

				if ( ! array_key_exists($relationship, $this->_with))
				{
					$this->_with[$relationship] = array();
				}
				
				$target = implode(':', $targets);
				
				// If there is no ":" in the target, it is a column, otherwise it's another with()
				if (FALSE !== strpos($target, ':'))
				{
					$target = ':'.$target;
				}

				$this->_with[$relationship][$target] = $value;
			}
			// Key is coming from a database result
			elseif ($alias === TRUE AND $columns = $this->_meta->columns($key))
			{
				// Contains an array of fields that the column is mapped to
				// This allows multiple fields to get data from the same column
				foreach ($columns as $field)
				{
					$this->_original[$field] = $this->_meta->fields($field)->set($value);
				}
			}
			// Standard setting of a field 
			elseif ($alias === FALSE AND $field = $this->_meta->fields($key))
			{
				$this->_original[$field->name] = $field->set($value);
			}
		}
		
		// Model is now saved and loaded
		$this->_saved = $this->_loaded = TRUE;
		
		return $this;
	}
	
	/**
	 * Loads a single row into the current object. 
	 *
	 * @param  mixed  $where  an array or id to load 
	 * @return $this
	 */
	public function load($key = NULL)
	{
		$query = Jelly::select($this);
		
		// Apply the limit
		if ($key)
		{
			$query->where($this->unique_key($key), '=', $key);
		}
		else
		{
			// Construct the query from original values
			foreach ($this->_original as $field => $value)
			{
				$field = $this->_meta->fields($field);
				
				// Only use in_db values
				if ($field->in_db)
				{
					if ($value != $field->default)
					{
						$query->where($field->name, '=', $value);
					}
				}
			}
		}
		
		// All good
		$result = $query->execute();
		
		// Ensure we have something
		if ($result->count())
		{			
			// Insert the original values
			$this->values($result->current(FALSE), TRUE);
		}
		else
		{
			// Clear the object so it appears empty since nothing was found
			$this->clear();
		}
		
		return $this;
	}
	
	/**
	 * Creates or updates the current record. 
	 * 
	 * If $primary_key is passed, the record will be assumed to exist
	 * and an update will be executed, even if the model isn't loaded().
	 *
	 * @param  mixed  $key
	 * @return Jelly  Returns $this
	 **/
	public function save($key = NULL)
	{
		// Determine whether or not we're updating
		$data = ($this->_loaded OR $key) ? $this->_changed : $this->_changed + $this->_original;
		
		// Set the key to our id if it isn't set
		if ($this->_loaded)
		{
			$key = $this->id();
		}
		
		// Run validation
		$data = $this->validate($data);
		
		// These will be processed later
		$values = $relations = array();
		
		// Run through the main table data
		foreach ($data as $column => $value)
		{
			$field = $this->_meta->fields($column);
			
			// Only save in_db values
			if ($field->in_db)
			{
				$values[$field->column] = $field->save($this, $value, (bool) $key);
			}
			elseif ($field instanceof Jelly_Field_Behavior_Saveable)
			{
				$relations[$column] = $value;
			}
		}
		
		// If we have a key, we're updating
		if ($key)
		{
			// Do we even have to update anything in the row?
			if ($values)
			{
				Jelly::update($this)
					->where($this->unique_key($key), '=', $key)
					->set($values)
					->execute();
			}
		}
		else
		{
			list($id) = Jelly::insert($this)
							->columns(array_keys($values))
							->values(array_values($values))
							->execute();
							
			// Gotta make sure to set this
			$values[$this->_meta->primary_key()] = $id;
		}
		
		// Set the changed data back as original
		// @TODO: Fix this. It's wrong.
		$this->_original = array_merge($this->_original, $this->_changed, $data);
		
		// We're good!
		$this->_loaded = $this->_saved = TRUE;
		$this->_retrieved = $this->_changed = array();
		
		// Save the relations
		foreach($relations as $column => $value)
		{	
			$this->_meta->fields($column)->save($this, $value, (bool) $key);
		}
		
		return $this;
	}
	
	/**
	 * Deletes a single record.
	 *
	 * @param  $key    A key to use for non-loaded records
	 * @return Jelly   Returns $this
	 **/
	public function delete($key = NULL)
	{
		// Are we loaded? Then we're just deleting this record
		if ($this->_loaded OR $key)
		{
			if ($this->_loaded)
			{
				$key = $this->id();
			}
				
			Jelly::delete($this)
				->where($this->unique_key($key), '=', $key)
				->execute();
		}
		
		return $this->clear();
	}
	
	/**
	 * Returns a boolean as to whether or not the particular field has changed
	 *
	 * @param  string $field 
	 * @return boolean
	 */
	public function changed($field)
	{
		return array_key_exists($this->_meta->fields($field, TRUE), $this->_changed);
	}
	
	/**
	 * Sets a model to its original state, as if freshly instantiated
	 *
	 * @return $this
	 */
	public function clear()
	{
		// Reset back to the initial state
		$this->_loaded = $this->_saved = FALSE;
		$this->_with = $this->_changed = 
		$this->_retrieved = $this->_unmapped = array();
		$this->_original = $this->_meta->defaults();
		return $this;
	}
	
	/**
	 * Returns whether or not that model is related to the 
	 * $model specified. This only works with relationships
	 * where the model "has" another model or models:
	 * 
	 * has_many, has_one, many_to_many
	 *
	 * @param  string	$name 
	 * @param  mixed	$models
	 * @return boolean
	 */
	public function has($name, $models)
	{
		$field = $this->_meta->fields($name);
		
		// Don't continue without knowing we have something to work with
		if ($field instanceof Jelly_Field_Behavior_Haveable)
		{
			return $field->has($this, $this->_ids($models));
		}
		
		return FALSE;
	}
	
	/**
	 * Adds a specific model(s) to the relationship.
	 * 
	 * $models can be one of the following:
	 * 
	 * - A primary key
	 * - Another Jelly model
	 * - An iterable collection of primary keys or 
	 *	 Jelly models, such as an array or Database_Result
	 * 
	 * Even though semantically odd, this method can be used for 
	 * changing 1:1 relationships like hasOne and belongsTo.
	 * 
	 * If you set more than one for these types of relationships,
	 * however, only the first will be used.
	 *
	 * @param  string  $name 
	 * @param  string  $models 
	 * @return Jelly   Returns $this
	 */
	public function add($name, $models)
	{
		return $this->_change($name, $models, TRUE);
	}
	
	/**
	 * Removes a specific model(s) from the relationship.
	 * 
	 * $models can be one of the following:
	 * 
	 * - A primary key
	 * - Another Jelly model
	 * - An iterable collection of primary keys or 
	 *	 Jelly models, such as an array or Database_Result
	 * 
	 * Even though semantically odd, this method can be used for 
	 * changing 1:1 relationships like hasOne and belongsTo.
	 * 
	 * If you set more than one for these types of relationships,
	 * however, only the first will be used.
	 *
	 * @param  string  $name 
	 * @param  string  $models 
	 * @return Jelly   Returns $this
	 */
	public function remove($name, $models)
	{
		return $this->_change($name, $models, FALSE);
	}
	
	/**
	 * Validates and filters the data
	 *
	 * @throws Validate_Exception
	 * @return array
	 */
	public function validate($data = NULL)
	{
		if ($data === NULL)
		{
			$data = $this->_changed;
		}
		
		if (empty($data))
		{
			return $data;
		}
		
		// Create the validation object
		$data = Validate::factory($data);
		
		// Loop through all columns, adding rules where data exists
		foreach ($this->_meta->fields() as $column => $field)
		{
			// Do not add any rules for this field
			if ( ! $data->offsetExists($column))
			{
				continue;
			}

			$data->label($column, $field->label);
			$data->filters($column, $field->filters);
			$data->rules($column, $field->rules);
			$data->callbacks($column, $field->callbacks);
		}

		if ( ! $data->check())
		{
			throw new Validate_Exception($data);
		}
		
		return $data->as_array();
	}

	/**
	 * Returns a view object the represents the field. If $prefix is an array,
	 * it will be used for the data and $prefix will be set to the default.
	 *
	 * @param  string		 $name	  The field to render
	 * @param  string|array	 $prefix 
	 * @param  string		 $data 
	 * @return View
	 */
	public function input($name, $prefix = NULL, $data = array())
	{
		$field = $this->_meta->fields($name);
		
		// More data munging. But it makes the API so much more intuitive
		if (is_array($prefix))
		{
			$data = $prefix;
			$prefix = NULL;
		}
		
		// Set a default prefix if it's NULL
		if ($prefix === NULL)
		{
			$prefix = $this->_meta->input_prefix();
		}
		
		// Ensure there is a default value. Some fields overridde this
		$data['value'] = $this->__get($name);
		$data['model'] = $this;
		
		return $field->input($prefix, $data);
	}
	
	/**
	 * Returns the unique key for a specific value. This method is expected 
	 * to be overloaded in models if the model has other unique columns.
	 *
	 * @param  mixed  $value 
	 * @return string
	 */
	public function unique_key($value)
	{
		return $this->_meta->primary_key();
	}

	/**
	 * Returns whether or not the model is loaded
	 *
	 * @return boolean
	 */
	public function loaded()
	{	
		return $this->_loaded;
	}

	/**
	 * Whether or not the model is saved
	 *
	 * @return boolean
	 */
	public function saved()
	{	
		return $this->_saved;
	}
	
	/**
	 * Returns the value of the primary key for the row
	 *
	 * @return mixed
	 */
	public function id()
	{
		return $this->get($this->_meta->primary_key());
	}
	
	/**
	 * Returns the value of the model's primary value
	 *
	 * @return mixed
	 */
	public function name()
	{
		return $this->get($this->_meta->name_key());
	}

	/**
	 * Changes a relation by adding or removing specific records from the relation.
	 *
	 * @param  string  $name	The name of the field
	 * @param  mixed   $models	Models or primary keys to add or remove
	 * @param  string  $add		True to add, False to remove
	 * @return Jelly   Returns $this
	 */
	protected function _change($name, $models, $add)
	{
		$field = $this->fields($name);
		
		if ($field instanceof Jelly_Field_Behavior_Changeable)
		{
			$name = $field->name;
		}
		else
		{
			return $this;
		}
		
		// If this is set, we don't need to re-retrieve the values
		if ( ! array_key_exists($name, $this->_changed))
		{
			$current = array();
			$value = $this->_ids($this->__get($name));
		}
		else
		{
			$current = $this->_changed[$name];
		}
		
		$changes = $this->_ids($models);
		
		// Are we adding or removing?
		if ($add)
		{
			$changes = array_unique(array_merge($current, $changes));
		}
		else
		{
			$changes = array_diff($current, $changes);
		}
		
		// Set it 
		$this->set($name, $changes);
		
		// Chainable
		return $this;
	}
	
	/**
	 * Converts different model types to an array of primary keys
	 *
	 * @param  mixed $models 
	 * @return array
	 */
	protected function _ids($models)
	{	
		$ids = array();
				
		// Handle Database Results
		if ($models instanceof Iterator OR is_array($models))
		{
			foreach($models as $row)
			{
				if (is_object($row))
				{
					// Ignore unloaded relations
					if ($row->loaded())
					{
						$ids[] = $row->id();
					}
				}
				else
				{
					$ids[] = $row;
				}
			}
		}
		// And individual models
		elseif (is_object($models))
		{
			// Ignore unloaded relations
			if ($models->loaded())
			{
				$ids[] = $models->id();
			}
		}
		// And everything else
		else
		{
			$ids[] = $models;
		}
		
		return $ids;
	}
}