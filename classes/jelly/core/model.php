<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly is a Kohana 3.0 ORM. It itself is a conceptual fork of Kohana's ORM 
 * and Sprig. Some code and ideas are borrowed from both projects.
 * 
 * @package Jelly
 * 
 * @author Jonathan Geiger
 * @author Paul Banks
 * @author Woody Gilk
 * @author Kohana Team
 * 
 * @link http://github.com/shadowhand/sprig/
 * @link http://github.com/jheathco/kohana-orm
 * 
 * @license http://kohanaphp.com/license.html
 */
abstract class Jelly_Core_Model
{
	/**
	 * Factory for generating models. Fields are initialized only 
	 * on the first instantiation of the model, and never again.
	 * 
	 * Model's do not have to be instantiated through here; they 
	 * can be constructed directly.
	 *
	 * @param	mixed  $model  A model name or another Jelly to create
	 * @param	mixed  $id	   The id or where clause to load upon construction
	 * @return	Jelly
	 */
	public static function factory($model, $id = NULL)
	{	
		$class = Jelly_Meta::class_name($model);
		
		return new $class($id);
	}
	
	/**
	 * Returns a query builder that can be used for selecting many records.
	 *
	 * @param  string $model 
	 * @return Jelly_Builder
	 */
	public static function select($model)
	{
		return new Jelly_Builder($model, Database::SELECT);
	}
	
	/**
	 * Returns a query builder that can be used for inserting a record.
	 * 
	 * This method is only here for completeness. However it doesn't serve
	 * much purpose over instantiating a model directly.
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
		$this->_meta = Jelly_Meta::get($this);
		
		// Copy over the defaults into the original data. This also has 
		// the added benefit of registering the model's metadata, if it does not exist yet
		$this->_original = $this->_meta->defaults();
		
		// Add the values stored by mysql_set_object
		if (!empty($this->_preload_data) && is_array($this->_preload_data))
		{
			$this->load_values($this->_preload_data);
			$this->_loaded = $this->_saved = TRUE;
			$this->_preload_data = array();
		}
		
		// Have an id? Attempt to load it
		if ($cond && (is_int($cond) || is_string($cond) || is_array($cond)))
		{
			$this->load($cond);
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
		
		if (!array_key_exists($name, $this->_retrieved))
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
	 * Gets the internally represented value from a field or unmapped column.
	 * 
	 * * If an array or TRUE is passed for $name, an array of fields will be returned.
	 * * If $changed is FALSE, only original data for the field will be returned.
	 *
	 * @param	mixed    $name	   The field's name
	 * @param	boolean	 $changed
	 * @return	mixed
	 */
	public function get($name, $changed = TRUE)
	{	
		// Passing TRUE or an array of fields to get returns them as an array
		if (is_array($name) || $name === TRUE)	
		{
			$fields = ($name === TRUE) ? array_keys($this->_meta->fields()) : $name;
			$result = array();

			foreach($fields as $field)
			{
				if ($changed)
				{
					$result[$field] = $this->__get($field);
				}
				else
				{
					$result[$field] = $this->get($field, FALSE);
				}
			}

			return $result;
		}
		else 
		{
			if ($field = $this->_meta->fields($name))
			{	
				// Alias the name to its actual name
				$name = $field->name;
				
				// Changes trump with() and original values
				if ($changed && array_key_exists($name, $this->_changed))
				{	
					$value = $field->get($this, $this->_changed[$name]);
				}
				else if ($changed && array_key_exists($name, $this->_with))
				{
					$model = Jelly::factory($name)->load_values($this->_with[$name], FALSE);
					
					// Try and verify that it's actually loaded
					if ($model->id())
					{
						$model->_loaded = TRUE;
						$model->_saved = TRUE;
					}
					
					$value = $model;
				}
				else
				{
					$value = $field->get($this, $this->_original[$name]);
				}
				
				return $value;
			}
			// Return unmapped data from custom queries
			else if (isset($this->_unmapped[$name]))
			{
				return $this->_unmapped[$name];
			}
		}
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
		if (!is_array($values))
		{
			$values = array($values => $value);
		}

		// Why this way? Because it allows the model to have 
		// multiple fields that are based on the same column
		foreach($values as $key => $value)
		{
			$field = $this->_meta->field($key);
			
			$this->_changed[$field->name] = $field->set($value);
			
			// Invalidate the cache
			if (array_key_exists($field->name, $this->_retrieved))
			{
				unset($this->_retrieved[$field->name]);
			}
		}
		
		return $this;
	}
	
	/**
	 * Clears the object and loads an array of values into the object.
	 * 
	 * This should only be used for setting database results.
	 *
	 * @param  array   $values 
	 * @param  boolean $alias 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function load_values(array $values, $alias = TRUE)
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

				if (!array_key_exists($relationship, $this->_with))
				{
					$this->_with[$relationship] = array();
				}

				$this->_with[$relationship][implode(':', $targets)] = $value;
			}
			// Key is coming from a database result
			else if ($alias === TRUE && $columns = $this->_meta->columns($key))
			{
				// Contains an array of fields that the column is mapped to
				// This allows multiple fields to get data from the same column
				foreach ($columns as $field)
				{
					$this->_original[$field] = $this->_meta->fields($field)->set($value);
				}
			}
			// Standard setting of a field 
			else if ($alias === FALSE && $field = $this->_meta->fields($key))
			{
				$this->_original[$field->name] = $field->set($value);
			}
		}
	}
	
	/**
	 * Returns true if $name is a field of the model or an unmapped column.
	 *
	 * @param  string	$name 
	 * @return boolean
	 */
	public function __isset($name)
	{
		return (bool)($this->_meta->fields($name) || array_key_exists($name, $this->_unmapped));
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
	 * Allows serialization of a model and all of its retrieved and related properties.
	 * 
	 * This fixes a bug with retrieved MySQL results.
	 *
	 * @return array
	 * @author Paul Banks
	 */
	public function __sleep()
	{
		foreach ($this->_retrieved as $field => $object)
		{
			if ($object instanceof Database_MySQL_Result)
			{
				// Database_MySQL_Results handle results differenly, so they must be converted
				// Otherwise they are invalide when they wake up.
				$this->_retrieved[$field] = new Database_Result_Cached($object->as_array(), '');				
			}
		}		

		// Return array of all properties to get them serialised
		return array_keys(get_object_vars($this));
	}
	
	/**
	 * Loads a single row or multiple rows. If $where is a string or integer
	 * it is assumed that you are searching for the model's primary key. In
	 * which case, $limit will be automatically set to 1 and the result
	 * will be loaded into $this.
	 * 
	 * If $limit is 1 the result is always loaded into $this. Otherwise,
	 * a database_result is returned.
	 *
	 * @param  mixed  $where  an array or id to load 
	 * @return mixed
	 */
	public function load($where = NULL)
	{
		$query = Jelly::select($this)->as_object(FALSE);
		
		// Apply the limit
		if (is_int($where) || is_string($where))
		{
			$query->where($this->_meta->primary_key(), '=', $where);
		}
		// Simple where clause
		else if (is_array($where))
		{
			foreach($where as $column => $value)
			{
				$query->where($column, '=', $value);
			}
		}
		
		// All good
		$result = $query->execute();
		
		// Ensure we have something
		if ($result->count())
		{			
			// Insert the original values
			$this->load_values($result->current(FALSE));
			
			// We're good!
			$this->_loaded = $this->_saved = TRUE;
			$this->_changed = $this->_retrieved = array();
		}
		
		return $this;
	}
	
	/**
	 * Creates a new record based on the current model. If save related is TRUE
	 * any changes made to relations will be updated as well.
	 * 
	 * Keep in mind, however, that only the relation to the other model will be saved,
	 * and not the actual model that it is related to.
	 * 
	 * If the model's meta data is set to validate on save, then 
	 * this could potentially throw a Validate_Exception.
	 *
	 * @param  bool	  Whether or not to save related changes
	 * @return Jelly  Returns $this
	 **/
	public function save()
	{
		// Determine whether or not we're updating
		$data = ($this->_loaded) ? $this->_changed : $this->_changed + $this->_original;
		
		// Run validation
		$data = $this->validate($data);
		
		// These will be processed later
		$relations = array();
		
		// Run through the main table data
		foreach ($data as $column => $value)
		{
			$field = $this->_meta->fields($column);
			
			// Only save in_db values
			if ($field->in_db)
			{
				$values[$field->column] = $field->save($value);
			}
			else if ($field instanceof Jelly_Behavior_Field_Saveable)
			{
				$relations[$column] = $value;
			}
		}
		
		if ($this->_loaded)
		{
			// Do we even have to update anything in the row?
			if ($values)
			{
				Jelly::update($this)
					->where($this->_meta->primary_key(), '=', $this->_original[$this->_meta->primary_key()])
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
		$this->load_values($values, FALSE);
		
		// We're good!
		$this->_loaded = $this->_saved = TRUE;
		$this->_retrieved = $this->_changed = array();
		
		// Save the relations
		foreach($relations as $column => $value)
		{	
			$this->_meta->fields($column)->save($this, $value);
		}
		
		// Enter relations back in
		$this->load_values($relations, FALSE);
		
		return $this;
	}
	
	/**
	 * Deletes a single or multiple records
	 * 
	 * If we're loaded(), it just deletes this object, otherwise it deletes 
	 * whatever the query matches. 
	 *
	 * @param  $where  A simple where statement
	 * @return Jelly   Returns $this
	 **/
	public function destroy()
	{
		// Are we loaded? Then we're just deleting this record
		if ($this->_loaded)
		{
			$result = Jelly::delete($this)
				->where($this->_meta->primary_key(), '=', $this->id())
				->execute();
		}
		
		return $this->clear();
	}
	
	/**
	 * Sets a model to its original state, as if freshly instantiated
	 *
	 * @return $this
	 * @author Jonathan Geiger
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
		if ($field instanceof Jelly_Behavior_Field_Haveable)
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
			if (!$data->offsetExists($column))
			{
				continue;
			}

			$data->label($column, $field->label);
			$data->filters($column, $field->filters);
			$data->rules($column, $field->rules);
			$data->callbacks($column, $field->callbacks);
		}

		if (!$data->check())
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
		
		if ($field instanceof Jelly_Behavior_Field_Changeable)
		{
			$name = $field->name;
		}
		else
		{
			return $this;
		}
		
		// If this is set, we don't need to re-retrieve the values
		if (!array_key_exists($name, $this->_changed))
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
		if ($models instanceof Iterator || is_array($models))
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
		else if (is_object($models))
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