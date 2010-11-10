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
	protected $_initial = array();

	/**
	 * @var  array  The current state of the data on the object
	 */
	protected $_current = array();
	
	/**
	 * @var  array  Tracks potentially changed fields (not in use yet)
	 */
	protected $_changed = array();
	
	/**
	 * @var  string  Whether or not we consider the model loaded
	 */
	protected $_loaded = FALSE;
	
	/**
	 * @var  array  An array of context => valid flags
	 */
	 protected $_valid = array();
	
	/**
	 * @var  Boolean  All current validators
	 */
	 protected $_validators = array();
	
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
	 * Set the current value for a single field.
	 * 
	 * @param   string  The name of the field
	 * @return  mixed
	 */
	public function __set($name, $value)
	{
		// Being set by *_fetch_object, store the values for the constructor
		if (is_array($this->_load_data))
		{
			return $this->_load_data[$name] = $value;
		}

		$this->_set(array($name => $value));
	}
	
	/**
	 * Gets the current value for a single field.
	 * 
	 * @param   string  The name of the field
	 * @return  mixed
	 */
	public function __get($name)
	{
		return current($this->_get(array($name)));
	}
	
	/**
	 * Passes unknown methods along to the behaviors.
	 *
	 * @todo    Invalid method detection
	 * @param   string  $method
	 * @param   array   $args
	 * @return  mixed
	 **/
	public function __call($method, $args)
	{
		return $this->_trigger('model.call_'.$method, $this, $args);
	}
	
	/**
	 * Returns true if $name is a field of the model.
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
		return (bool)($this->meta()->field($name));
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
			$this->_current[$field->name] = $field->default;
		}
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
	 * Sets the value of a field or fields:
	 * 
	 *     $model->set('id', 1);
	 *     $model->set(array('id' => $id, 'name' => $name));
	 * 
	 * @param   string|array   A map of the data to set or the name of the field
	 * @param   mixed          The value to set if passing a string for the first param
	 * @return  $this
	 */
	public function set($data, $value = NULL)
	{
		if (is_array($data))
		{
			$this->_set($data);
		}
		else
		{
			$this->_set(array($data => $value));
		}
		
		return $this;
	}
	
	/**
	 * Gets the current value of a field or fields:
	 * 
	 *     // Returns the value of one field
	 *     $model->get('id');
	 *     => 1
	 * 
	 *     // Returns a map of the two fields
	 *     $model->get(array('id', 'name'));
	 *     => array('id' => 1, 'name' => 'Foo')
	 * 
	 *     // Returns a map of all fields
	 *     $model->get(TRUE);
	 *     => array('id' => 1, 'name' => 'Foo', ...);
	 * 
	 * @param   mixed  The name of the field
	 * @return  mixed
	 */
	public function get($names = TRUE)
	{
		if ($names === TRUE)
		{
			$names = array_keys($this->meta()->fields);
		}
		
		$data = $this->_get((array) $names);
		
		return is_array($names) ? $data : $data[$names];
	}
	
	/**
	 * Gets the initial value of a field or set of fields,
	 * before it was processed by the field and before it was
	 * changed.
	 * 
	 * This is usually the value that was set directly from the
	 * database, or the value that was set as a default by the field.
	 * 
	 *     // Assume $model has id:1 and name:"Foo"
	 *     $model->set(array('id' => 2, 'name' => 'Bar'));
	 * 
	 *     // Returns the value for the id field
	 *     $model->initial('id');
	 *     => 1
	 * 
	 *     // Returns a map of the two fields
	 *     $model->initial(array('id', 'name'));
	 *     => array('id' => 1, 'name' => 'Foo')
	 * 
	 *     // Returns a map of all fields
	 *     $model->initial(TRUE);
	 *     => array('id' => 1, 'name' => 'Foo', ...);
	 * 
	 * @param   mixed  The name(s) of the fields
	 * @return  mixed
	 */
	public function initial($names = TRUE)
	{
		if ($names === TRUE)
		{
			$names = array_keys($this->meta()->fields);
		}
		
		// Notice the TRUE, it tells _set() to return initial values
		$data = $this->_get((array) $names, TRUE);
		
		return is_array($names) ? $data : $data[$names];
	}
	
	/**
	 * Returns the changed fields in the model.
	 * Returns FALSE if nothing has changed,
	 * or an array of fields that were changed.
	 * 
	 *     $model->changed('id');
	 *     => FALSE
	 * 
	 *     $model->changed(array('id', 'name'));
	 *     => array('name')
	 * 
	 *     $model->changed('name')
	 *     => 'name'
	 * 
	 *     $model->changed()
	 *     => array('name', 'description', ...);
	 * 
	 * Invalid fields are ignored and have no bearing 
	 * on the outcome of the method.
	 * 
	 * @param   mixed  The name(s) of the fields
	 * @return  mixed
	 */
	public function changed($names = TRUE)
	{
		if ($names === TRUE)
		{
			$names = array_keys($this->meta()->fields);
		}
		
		$meta = $this->meta();
		
		// Single field should return a string if changed
		if ( ! is_array($names))
		{
			if ($field = $meta->field($names))
			{
				if ($field->changed($this, $this->get($field->name)))
				{
					$changed = $names;
				}
			}
		}
		else
		{
			$changed = array();
			
			foreach ($names as $name)
			{
				if ($field = $meta->field($name))
				{
					$initial = $this->_initial[$field->name];
					$current = $this->_current[$field->name];
					
					if ($field->changed($this, $initial, $current))
					{
						$changed[] = $name;
					}
				}
			}
		}
		
		return $changed ? $changed : FALSE;
	}
	
	/**
	 * Reverts any current values back to their initial values.
	 * 
	 * @return $this
	 */
	public function revert($names = TRUE)
	{
		if ($names === TRUE)
		{
			$names = array_keys($this->meta()->fields);
		}
		
		$meta = $this->meta();
		
		foreach ((array)$names as $name)
		{
			if ($field = $meta->field($name))
			{
				$this->_current[$field->name] = $this->_initial[$field->name];
			}
		}
		
		return $this;
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
		$this->reset();
		$this->_set($values, TRUE);
		
		// Changed data should now be in sync with original data
		$this->_current = $this->_initial;
		
		// Context has changed, since we assume we're loaded
		if ($this->id())
		{
			$this->_loaded = TRUE;
		}
	}
	
	/**
	 * Validates the current state of the model.
	 * 
	 * @param   mixed  $context
	 * @return  boolean
	 */
	public function validate($context = NULL)
	{	
		$context = $this->_context($context);
		
		if (empty($this->_valid[$context]))
		{
			$validator = $this->_validator($context);
			
			$this->_trigger('model.before_validate', array($validator));
			
			if ($validator->check())
			{
				// We have these callbacks here so fields can do amazing things
				// without having to attach themselves to the validator
				foreach ($meta->fields as $field)
				{
					$field->validate($this, $validator, $context);
				}
				
				if ($validator->errors())
				{
					$this->_valid[$context] = FALSE;
				}
				else
				{
					$this->_valid[$context] = TRUE;
					
					// Re-integrate the changed data, since it may 
					// have been filtered or otherwise altered
					$this->set($validator->as_array());
				}
			}
			
			$this->_trigger('model.after_validate', array($validator));
		}
		
		return $this->_valid[$context];
	}
	
	/**
	 * Invalidates the specified context.
	 * 
	 * This must be done manually if you want to revalidate
	 * a model, as it is almost impossible to keep track
	 * of all the different changes.
	 * 
	 * This is a design decision, since it's very rare that
	 * you'll validate a model and want to re-validate it
	 * but very common that you'll want to manually validate
	 * before saving without the save triggering another 
	 * pointless validation.
	 * 
	 * @param   mixed  $context
	 * @return  boolean
	 */
	public function revalidate($context = NULL)
	{	
		$context = $this->_context($context);
		
		unset($this->_valid[$context]);
		unset($this->_validator[$context]);
		
		return $this->validate($context);
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
		$context = $this->_context($context);
		
		if (empty($this->_validator))
		{
			$meta = $this->meta();
			
			if (isset($meta->validate[$context]))
			{
				$extras = $meta->validate[$context];
				$fields = array_keys($context);
			}
			else
			{
				$extras = NULL;
				$fields = array_keys($meta->fields);
			}

			$validator = Validate::factory($this->get($fields));

			foreach ($fields as $field)
			{
				foreach (array('filters', 'rules', 'callbacks') as $prop)
				{
					// Default rules are defined directly on the meta object
					if (isset($meta->$prop[$field]))
					{
						$validator->$prop($field, $meta->$prop[$field]);
					}

					// Then augmented (or inherited) by the context
					if (isset($extras[$field][$prop]) AND is_array($extras[$field][$prop]))
					{
						$validator->$prop($field, $extras[$field][$prop]);
					}
				}
			}
			
			$this->_validator[$context] = $validator;
		}
		
		return $this->_validator[$context];
	}
	
	/**
	 * Inserts the current record.
	 *
	 * @return  $this
	 **/
	public function insert()
	{
		if ($this->_loaded)
		{
			throw new Jelly_Exception('Cannot insert unloaded model :model',
				array(':model' => $this->__tostring()));
		}
		
		if ( ! $this->validate('insert'))
		{
			throw new Validate_Exception($this->validator('insert'));
		}

		$meta   = $this->meta();
		$values = $saveable = array();
		
		if (FALSE === $this->_trigger('model.before_insert'))
		{
			return $this;
		}

		// Iterate through all fields in original in case any unchanged fields
		// have save() behavior like timestamp updating...
		foreach ($this->_current as $column => $value)
		{
			$field = $meta->field($column);

			if ($field->primary AND $value === NULL)
			{
				continue;
			}
			else if ($field->in_db)
			{
				$values[$field->name] = $field->save($this, $value);
			}
			else
			{
				$saveable[$field->name] = $value;
			}
		}

		list($id) = Jelly::query($meta->model)
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
		if ( ! $this->_loaded)
		{
			throw new Jelly_Exception('Cannot update unloaded model :model',
				array(':model' => $this->__tostring()));
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
		foreach ($this->_current as $column => $value)
		{
			$field = $meta->field($column);
			
			if ($field->in_db)
			{
				$initial = $this->_initial[$field->name];
				$value   = $field->save($this, $value);
				
				if ($field->changed($this, $initial, $value))
				{
					$values[$field->name] = $value;
				}
			}
			else
			{
				$saveable[$field->name] = $value;
			}
		}

		Jelly::query($meta->model, $this->id())
			 ->set($values)
			 ->update();

		$this->load_values($values + $saveable);

		foreach ($saveable as $field => $value)
		{
			$meta->field($field)->save($this, $value);
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
		$meta = $this->meta();
		
		if ( ! $this->_loaded)
		{
			throw new Jelly_Exception('Cannot delete unloaded model :model',
				array(':model' => $this->__tostring()));
		}

		// Trigger callbacks to ensure we proceed
		$result = $this->_trigger('model.before_delete');
		
		if ($result === NULL)
		{
			foreach ($meta->fields as $field)
			{
				$field->delete($this);
			}
			
			Jelly::query($meta->model, $this->id())->delete();
		}
		
		$this->_trigger('model.after_delete');

		return $this->reset();
	}
	
	/**
	 * Sets a model to its original state, as if freshly instantiated.
	 *
	 * @return  $this
	 */
	public function reset()
	{
		$this->_meta = Jelly::meta(substr(get_class($this), strlen(Jelly::$model_prefix)));
		
		$this->_loaded =
		$this->_load_data = FALSE;
		
		$this->_current = 
		$this->_initial = $this->meta()->defaults;
		
		$this->_changed =
		$this->_valid =
		$this->_validators = array();
		
		return $this;
	}
	
	/**
	 * Returns the model's primary key.
	 *
	 * @return  Jelly_Meta
	 */
	public function id()
	{
		return $this->get($this->meta()->primary_key);
	}
	
	/**
	 * Returns the model's meta object.
	 *
	 * @return  Jelly_Meta
	 */
	public function meta()
	{
		return $this->_meta;
	}
	
	/**
	 * Sets values.
	 *
	 * @param   array  $values 
	 * @param   array  $container 
	 * @return  void
	 */
	protected function _set($values, $initial = FALSE)
	{
		$meta = $this->meta();
		
		foreach ($values as $key => $value)
		{
			if ($field = $meta->field($key))
			{
				if ($initial)
				{
					$this->_initial[$field->name] = $field->set($this, $value);
				}
				else
				{
					$this->_current[$field->name] = $field->set($this, $value);
				}
			}
			else
			{
				$this->_data[$key] = $value;
			}
		}
		
		return $this;
	}
	
	/**
	 * Gets values.
	 *
	 * @param   array  $values 
	 * @param   array  $container 
	 * @return  void
	 */
	protected function _get($keys, $initial = FALSE)
	{
		$meta = $this->meta();
		$data = array();
		
		foreach ($keys as $key)
		{
			$value = NULL;
			
			if ($field = $meta->field($key))
			{
				if ($initial)
				{
					$value = $this->_initial[$field->name] = $field->get($this->_initial[$field->name]);
				}
				else
				{
					$value = $this->_current[$field->name] = $field->get($this, $this->_current[$field->name]);
				}
			}
			else if (isset($this->_data[$key]))
			{
				$value = $this->_data[$key];
			}
			
			$data[$key] = $value;
		}
		
		return $data;
	}
	
	/**
	 * Returns a usable context for validation based on whether or not
	 * the model is loaded.
	 * 
	 * @param   string  $context
	 * @return  string
	 */
	protected function _context($context)
	{
		if ($context === NULL)
		{
			$context = $this->_loaded ? 'update' : 'insert';
		}
		
		return $context;
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