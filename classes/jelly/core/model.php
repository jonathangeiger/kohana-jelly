<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Model is the class all models must extend. It handles
 * various CRUD operations and relationships to other models.
 *
 * @package Jelly
 */
abstract class Jelly_Core_Model
{
	/**
	 * @var  array  Data to load at construction
	 */
	protected $_load_data = array();
	
	/**
	 * @var  array  The initial data set on the object
	 */
	protected $_original = array();

	/**
	 * @var  array  The current state of the data on the object
	 */
	protected $_data = array();

	/**
	 * @var  array  Unmapped data that is still accessible
	 */
	protected $_unmapped = array();

	/**
	 * @var  array  With data
	 */
	protected $_with = array();
	
	/**
	 * @var  array  An array that keeps track of changed fields
	 */
	protected $_changed = array();
	
	/**
	 * @var  boolean  Whether or not the model is loading
	 */
	protected $_loading = TRUE;
	
	/**
	 * @var  Boolean  A flag that keeps track of whether or not the model is valid
	 */
	 protected $_valid = FALSE;
	
	/**
	 * @var  string  The current meta instance this model uses
	 */
	protected $_meta = NULL;
	
	/**
	 * Constructs a new Jelly_Model instance.
	 *
	 * @param  mixed  The key to use to load the record with
	 */
	public function __construct($key = NULL)
	{
		$this->reset();
		
		if ( ! empty($this->_load_data))
		{
			// Data to load from a query result?
			$this->load_values($this->_load_data);
		}
		
		if ($key !== NULL)
		{
			$result = Jelly::query($this->meta()->model, $key)
			     ->as_object(FALSE)
			     ->select();
			
			if ($result)
			{
				$this->load_values($result);
			}
		}
	}
	
	/**
	 * @see     Jelly_Model::set()
	 */
	public function __set($name, $value)
	{
		// Being set by *_fetch_object, store the values for the constructor
		if (is_array($this->_load_data))
		{
			return $this->_load_data[$name] = $value;
		}

		$this->set(array($name => $value));
	}
	
	/**
	 * @see  Jelly_Model::get()
	 */
	public function __get($name)
	{
		return $this->get($name);
	}
	
	/**
	 * Passes unknown methods along to the behaviors.
	 *
	 * @param   string  $method
	 * @param   array   $args
	 * @return  mixed
	 **/
	public function __call($method, $args)
	{
		return $this->_trigger('model.call_'.$method, $this, $args);
	}
	
	/**
	 * Returns true if $name is a field of the model or an unmapped column.
	 * 
	 * This does not conform to the standard of returning FALSE if the
	 * property is set but the value is NULL. Rather this acts more like
	 * property_exists.
	 *
	 * @param   string  $name
	 * @return  boolean
	 */
	public function __isset($name)
	{
		return (bool)($this->meta()->field($name) OR array_key_exists($name, $this->_unmapped));
	}
	
	/**
	 * This doesn't unset fields. Rather, it sets them to their original
	 * value. Unmapped, changed, and retrieved values are unset.
	 *
	 * In essence, unsetting a field sets it as if you never made any changes
	 * to it, and clears the cache if the value has been retrieved with those changes.
	 *
	 * @param   string  $name
	 * @return  void
	 */
	public function __unset($name)
	{
		if ($field = $this->meta()->field($name))
		{
			$this->_data[$field->name] = $field->default;
		}

		unset($this->_unmapped[$name]);
	}
	
	/**
	 * Returns a string representation of the model in the
	 * form of `Model_Name (id)` or `Model_Name (NULL)` if
	 * the model is not loaded.
	 * 
	 * This is designed to be useful for debugging.
	 *
	 * @return  string
	 */
	public function __tostring()
	{
		return (string) get_class($this).'('.($this->id() ? $this->id() : 'NULL').')';
	}
	
	/**
	 * Sets the value of a field.
	 * 
	 * @param   array   $data 
	 * @return  $this
	 */
	public function set(array $data)
	{
		foreach ($data as $key => $value)
		{
			if ($field = $this->meta()->field($key))
			{
				$this->_data[$field->name] = $value;
			}
			else
			{
				$this->_unmapped[$key] = $value;
			}
		}
		
		return $this;
	}
	
	/**
	 * Gets the current value of a field or unmapped column.
	 *
	 * @param   string  The name of the field
	 * @return  mixed
	 */
	public function get($name)
	{
		$value = NULL;
		
		if ($field = $this->meta()->field($name))
		{
			$value = $this->_data[$field->name] = $field->value($this, $this->_data[$field->name]);
		}
		else if (isset($this->_unmapped[$name]))
		{
			$value = $this->_unmapped[$name];
		}
		
		return $value;
	}
	
	/**
	 * Returns the original value of a field, before it was changed.
	 * 
	 * This method—combined with __get(), which first searches for changed 
	 * values—is useful for comparing changes that occurred on a model. 
	 *
	 * @param   string  The field's or alias name
	 * @return  mixed
	 */
	public function original($name)
	{
		if ($field = $this->meta()->field($field))
		{
			return $this->_original[$field->name];
		}
	}
	
	/**
	 * Clears the object and loads an array of values into the object.
	 *
	 * This should only be used for setting from database results
	 * since the model declares itself as saved and loaded after.
	 *
	 * @param   array    $values
	 * @return  void
	 */
	public function load_values($values)
	{
		// Clear the object
		$this->reset();
	
		foreach ($values as $key => $value)
		{
			if ($field = $this->meta()->field($key))
			{
				$this->_data[$field->name] = $value;
			}
			else
			{
				$this->_unmapped[$key] = $value;
			}
		}
		
		// Changed data should now be in sync with original data
		$this->_original = $this->_data;
		
		return $this;
	}
	
	/**
	 * Validates the current state of the model.
	 * 
	 * @param   mixed  $context
	 * @return  boolean
	 */
	public function validate($context = NULL)
	{
		$meta = $this->meta();
		
		$validator = $this->validator();
		$data      = $this->as_array($fields);
		
		if ( ! $this->_valid AND ! empty($data))
		{
			$this->_trigger('model.before_validate', array($validator));
			
			if ($validator->check())
			{
				$this->set($validator->as_array());
				$this->_valid = TRUE;
			}
			
			$this->_trigger('model.after_validate', array($validator));
		}
		else
		{
			$this->_valid = TRUE;
		}
		
		return $this->_valid;
	}
	
	/**
	 * Returns the current validator, or creates 
	 * a new one if $context is passed.
	 *
	 * @param   string  $context 
	 * @return  Validate
	 */
	public function validator($context = NULL)
	{
		if ($context === NULL)
		{
			return $this->_validator;
		}
		
		$meta = $this->meta();

		if (isset($meta->validate[$context]))
		{
			$context = $meta->validate[$context];
			$fields  = array_keys($context);
		}
		else
		{
			$context = NULL;
			$fields  = array_keys($meta->fields);
		}

		foreach ($fields as $field)
		{
			// Add the defaults and then the custom context rules
			foreach (array('filters', 'rules', 'callbacks') as $prop)
			{
				if (isset($meta->$prop[$field]))
				{
					$validator->$prop($field, $meta->$prop[$field]);
				}

				if (isset($context[$field][$prop]) AND is_array($context[$field][$prop]))
				{
					$validator->$prop($field, $context[$field][$prop]);
				}
			}
		}
		
		$this->_validator = $validator;
		
		return $this->_validator;
	}
	
	/**
	 * Inserts the current record.
	 *
	 * @return  $this
	 **/
	public function insert()
	{
		if ( ! $this->validate('insert'))
		{
			throw new Validate_Exception($this->_validator);
		}

		$meta   = $this->meta();
		$values = $saveable = array();
		
		if (FALSE === $this->_trigger('model.before_insert'))
		{
			return $this;
		}

		// Iterate through all fields in original in case any unchanged fields
		// have save() behavior like timestamp updating...
		foreach ($this->_data as $column => $value)
		{
			$field = $meta->field($column);

			if ($field->primary AND $value === NULL)
				continue;
				
			if ($field->in_db)
			{
				$values[$field->name] = $field->save($this, $value);
			}
			else
			{
				$saveable[$field->name] = $value;
			}
		}

		list($id) = Jelly::query($this->_model)
		                 ->set($values)
		                 ->insert();

		$this->load_values(array($meta->primary_key => $id) + $values + $saveable);

		foreach ($saveable as $field => $value)
		{
			$meta->field($field)->save($this, $value);
		}
		
		$this->_trigger('model.after_insert');

		return $this;
	}
	
	/**
	 * Updates the current record.
	 *
	 * @return  $this
	 **/
	public function update()
	{
		if ( ! $this->id())
		{
			return $this;
		}
		
		if ( ! $this->validate('update'))
		{
			throw new Validate_Exception($this->_validator);
		}
		
		$meta   = $this->meta();
		$values = $saveable = array();
		
		if (FALSE === $this->_trigger('model.before_update'))
		{
			return $this;
		}
		
		// Iterate through all fields in original in case any unchanged fields
		// have save() behavior like timestamp updating...
		foreach ($this->_data as $column => $value)
		{
			$field = $meta->field($column);
			
			if ($field->in_db)
			{
				$value = $field->save($this, $value);
				
				if ($field->changed($this, $value))
				{
					$values[$field->name] = $value;
				}
			}
			else
			{
				$saveable[$field->name] = $value;
			}
		}

		Jelly::query($this->_model, $this->id())
			 ->set($values)
			 ->update();

		$this->load_values($values + $saveable);

		foreach ($saveable as $field => $value)
		{
			$meta->field($field)->update($this, $value);
		}
		
		$this->_trigger('model.after_update');

		return $this;
	}
	
	/**
	 * Deletes a single record.
	 *
	 * @return  $this
	 **/
	public function delete()
	{
		$id     = $this->original($meta->primary_key);
		$meta   = $this->meta();
		$result = FALSE;

		// Are we loaded? Then we're just deleting this record
		if ($id)
		{	
			// Trigger callbacks to ensure we proceed
			$result = $this->_trigger('model.before_delete');
			
			if ($result === NULL)
			{
				foreach ($meta->fields as $field)
				{
					$field->delete($this);
				}
				
				Jelly::query($meta->model, $id)->delete();
			}
			
			$this->_trigger('model.after_delete');
		}

		return $this->reset();
	}
	
	/**
	 * Returns the model's primary key value.
	 *
	 * @return mixed
	 **/
	public function id()
	{
		return $this->get($this->meta()->primary_key);
	}
	
	/**
	 * Returns the model's name key value.
	 *
	 * @return mixed
	 **/
	public function name()
	{
		return $this->get($this->meta()->name_key);
	}
	
	/**
	 * Returns whether or not the model is loaded.
	 *
	 * @return boolean
	 **/
	public function loaded()
	{
		return $this->_loaded;
	}
	
	/**
	 * Returns whether or not the model is saved.
	 *
	 * @return boolean
	 **/
	public function saved()
	{
		return $this->_saved;
	}
	
	/**
	 * Returns the model's meta object
	 *
	 * @return Jelly_Meta
	 */
	public function meta()
	{
		return $this->_meta;
	}
	/**
	 * Sets a model to its original state, as if freshly instantiated.
	 *
	 * @return  $this
	 */
	public function reset()
	{
		$this->_meta = Jelly::meta(substr(get_class($this), strlen(Jelly::$model_prefix)));
		
		$this->_saved   =
		$this->_load_data = 
		$this->_valid   = FALSE;
		$this->_changed = array();
		
		$this->_data = 
		$this->_original = $this->meta()->defaults;
		
		$this->_with = 
		$this->_unmapped = array();
		
		return $this;
	}
	
	/**
	 * Removes any changes made to a model.
	 * 
	 * @return $this
	 */
	public function revert()
	{
		if ($this->id())
		{
			
		}
		
		$this->_data = $this->_original;
		
		return $this;
	}
	
	/**
	 * Convenience method for triggering an event
	 *
	 * @param   string  $event 
	 * @param   array   $args 
	 * @return  void
	 */
	protected function _trigger($event, $args = array())
	{
		$this->meta()->events->trigger($event, $this, $args);
	}
}