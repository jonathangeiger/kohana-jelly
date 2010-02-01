<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly is a Kohana 3.0 ORM. It itself is a conceptual fork of Kohana's ORM 
 * and Sprig. Some code and ideas are borrowed from both projects.
 * 
 * @author Jonathan Geiger
 * @author Woody Gilk
 * @link http://github.com/shadowhand/sprig/
 * @author Kohana Team
 * @link http://github.com/jheathco/kohana-orm
 * @license http://kohanaphp.com/license.html
 */
abstract class Jelly_Model
{				
	/**
	 * @var array Callable database methods
	 */
	protected static $_db_methods = array
	(
		'where', 'and_where', 'or_where', 'where_open', 'and_where_open', 'or_where_open', 'where_close',
		'and_where_close', 'or_where_close', 'distinct', 'select', 'from', 'join', 'on', 'group_by',
		'having', 'and_having', 'or_having', 'having_open', 'and_having_open', 'or_having_open',
		'having_close', 'and_having_close', 'or_having_close', 'order_by', 'limit', 'offset', 'cached',
		'table',
	);
	
	/**
	 * @var array DB methods that must be aliased
	 */
	protected static $_alias = array
	(
		'where', 'and_where', 'or_where', 'select', 'from', 'join', 'on', 'group_by',
		'having', 'and_having', 'or_having', 'order_by', 'table'
	);
	
	/**
	 * Factory for generating models. Fields are initialized only 
	 * on the first instantiation of the model, and never again.
	 * 
	 * Model's do not have to be instantiated through here; they 
	 * can be constructed directly.
	 *
	 * @param   mixed  $model  A model name or another Jelly to create
	 * @param   mixed  $id     The id or where clause to load upon construction
	 * @return  Jelly
	 * @author  Jonathan Geiger
	 */
	public static function factory($model, $id = NULL)
	{	
		$class = Jelly_Meta::class_name($model);
		
		return new $class($id);
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
	 * @var array Data set by mysql_fetch_object. Daggers to ye who overwrites this.
	 */
	protected $_preload_data = array();
	
	/**
	 * @var boolean Whether or not the model is loading original data
	 */
	protected $_loading = TRUE;

	/**
	 * @var array Applied query builder methods
	 */
	protected $_db_applied = array();
	
	/**
	 * @var array Pending query builder methods
	 */
	protected $_db_pending = array();
	
	/**
	 * @var object Current query builder
	 */
	protected $_db_builder;

	/**
	 * An optional conditional can be passed. If it is an integer 
	 * or a string, it will be assumed to be a primary key and 
	 * the record will be loaded automatically into the model.
	 * If it is an associative array, it is used in constructing 
	 * a where clause and the record is loaded automatically.
	 *
	 * @param   mixed  $cond  A primary key or where clause to use for auto-loading a particular record
	 * @author  Jonathan Geiger
	 **/
	public function __construct($cond = NULL)
	{
		// Copy over the defaults into the original data. This also has 
		// the added benefit of registering the model's metadata, if it does not exist yet
		$this->_original = $this->meta('defaults');
		
		// Reset to an empty object
		$this->reset();

		// Add the values stored by mysql_set_object
		if (is_array($this->_preload_data) && !empty($this->_preload_data))
		{
			$this->values($this->_preload_data, TRUE);
			$this->_loaded = $this->_saved = TRUE;
		}
		
		// Finished initialized
		$this->_loading = FALSE;
		
		// Have an id? Attempt to load it
		if (is_int($cond) || is_string($cond) || is_array($cond))
		{
			$this->load($cond, 1);
		}
	}
	
	/**
	 * Proxies to get()
	 *
	 * @see get()
	 * @param string $name 
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function __get($name)
	{
		return $this->get($name, TRUE);
	}
	
	/**
	 * Gets the internally represented value from 
	 * a field or unmapped column.
	 *
	 * @param   string   $name   The field's name
	 * @param   boolean  $value  If FALSE, relationships won't be loaded
	 * @return  mixed
	 * @author  Jonathan Geiger
	 */
	public function get($name, $verbose = TRUE)
	{		
		if (isset($this->meta()->fields[$name]))
		{		
			$field = $this->meta()->fields[$name];
				
			// Return changed values first
			if (isset($this->_changed[$name]))
			{
				return $field->get($this, $this->_changed[$name], $verbose);
			}
			else
			{
				return $field->get($this, $this->_original[$name], $verbose);
			}
		}
		// Return unmapped data from custom queries
		else if (isset($this->_unmapped[$name]))
		{
			return $this->_unmapped[$name];
		}
	}
	
	/**
	 * Proxies to set()
	 *
	 * @see set()
	 * @param string $name 
	 * @param mixed $value 
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function __set($name, $value)
	{
		// Being set by mysql_fetch_object, store the values for the constructor
		if ($this->_loading)
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
	 * @param string $name 
	 * @param string $value 
	 * @return Jelly Returns $this
	 * @author Jonathan Geiger
	 */
	public function set($name, $value)
	{
		if (isset($this->meta()->fields[$name]))
		{		
			$field = $this->meta()->fields[$name];
					
			// If we're intitially setting data on the object 
			// that is coming from the database, it goes to $_data
			if ($this->_loading === TRUE)
			{
				$this->_original[$name] = $field->set($value);
			}
			// Otherwise we're setting changes
			else
			{
				$this->_changed[$name] = $field->set($value);
				$this->_saved = FALSE;
			}
		}
		// Allow setting unmapped data from custom queries
		else
		{
			$this->_unmapped[$name] = $value;
		}
		
		return $this;
	}
	
	/**
	 * Returns true if $name is a field of the 
	 * model or an unmapped column.
	 *
	 * @param string $name 
	 * @return boolean
	 * @author Jonathan Geiger
	 */
	public function __isset($name)
	{
		return (isset($this->_original[$name]) || isset($this->_unmapped[$name]));
	}
	
	/**
	 * This doesn't unset fields. Rather, it sets them to 
	 * their default value. Unmapped values are unset.
	 *
	 * @param string $name 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function __unset($name)
	{
		if (isset($this->_original[$name]))
		{
			// We don't want to unset the keys, because 
			// they are assumed to exist. Just NULL them out.
			$this->_original[$name] = $this->meta()->defaults[$name];
		}
		
		// This doesn't matter
		unset($this->_unmapped[$name]);
	}
	
	/**
	 * Handles pass-through to database methods. Calls to query methods
	 * (query, get, insert, update) are not allowed. Query builder methods
	 * are chainable.
	 *
	 * @param   string  method name
	 * @param   array   method arguments
	 * @return  Jelly   Returns $this
	 */
	public function __call($method, array $args)
	{
		if (in_array($method, Jelly::$_db_methods))
		{
			// Add support for column aliasing
			// Get the edge-cases first
			if ($method == 'select')
			{
				foreach ($args as $i => $arg)
				{
					if (is_array($arg))
					{
						$args[$i][0] = $this->_qb_alias($arg[0], TRUE);
					}
					else
					{
						$args[$i]= $this->_qb_alias($arg, TRUE);
					}
				}
			}
			
			// Table alias
			else if ($method == 'from' || $method == 'join')
			{
				if (is_array($args[0]))
				{
					foreach($args[0] as $i => $table)
					{
						$args[0][$i] = Jelly::model_alias($table);
					}
				}
				else
				{
					$args[0] = Jelly::model_alias($args[0]);
				}
			}
			
			// Join on
			else if ($method == 'on')
			{
				$args[0] = $this->_qb_alias($args[0],TRUE);
				$args[2] = $this->_qb_alias($args[2], TRUE);
			}
			
			// Everything else
			else if (in_array($method, self::$_alias))
			{
				$args[0] = $this->_qb_alias($args[0]);
			}
			
			// Add pending database call which is executed after query type is determined
			$this->_db_pending[] = array('name' => $method, 'args' => $args);
		}
		else
		{
			throw new Kohana_Exception('Invalid method :method called in :class',
				array(':method' => $method, ':class' => get_class($this)));
		}
		
		return $this;
	}
	
	/**
	 * Aliases a column that exists only in this model
	 *
	 * If $field is null, the model's table name is returned.
	 * Otherwise, the normal rules apply.
	 * 
	 * @param  string   $field  The field's name
	 * @param  boolean  $join   Whether or not to return the table and column joined
	 * @return string
	 * @author Jonathan Geiger
	 **/
	public function alias($field = NULL, $join = NULL)
	{	
		$meta = $this->meta();
		
		// Return the model's alias if nothing is passed
		if (!$field)
		{
			return $meta->table;
		}
		
		// Split off the table name; we already know that
		if (strpos($field, '.') !== FALSE)
		{			
			list(, $field) = explode('.', $field);
		}
		
		// Check and concatenate
		if (isset($meta->fields[$field]))
		{
			$field = $meta->fields[$field]->column;
		}
		
		if ($join)
		{
			return $meta->table.'.'.$field;
		}
		else
		{
			return $field;
		}
	}
	
	/**
	 * This is an internal method used for aliasing only things coming 
	 * to the query builder, since they can come in so many formats.
	 *
	 * @param  string   $field 
	 * @param  boolean  $join
	 * @return string
	 * @author Jonathan Geiger
	 */
	protected function _qb_alias($field, $join = NULL)
	{
		$model = NULL;
		
		if (strpos($field, '.') !== FALSE)
		{			
			list($model, $field) = explode('.', $field);
			
			// If $join is NULL, the column is returned as it came
			// If it was joined when it came in, it returns joined
			if ($join === NULL)
			{
				$join = TRUE;
			}
		}
		else
		{
			if ($join === NULL)
			{
				$join = FALSE;
			}
		}
		
		// If the model is NULL, $this's table name or model name
		// We just replace if with the current model's name
		if ($model === NULL || $model == $this->_table)
		{
			$model = Jelly_Meta::model_name($this);
		}
		
		return Jelly_Meta::column($model.'.'.$field, $join);
	}
	
	/**
	 * Returns metadata for this particular object
	 *
	 * @param  string $property 
	 * @return Jelly_Meta
	 * @author Jonathan Geiger
	 */
	public function meta($property = NULL)
	{
		return Jelly_Meta::get($this, $property);
	}
	
	/**
	 * Counts the number of records for the current query
	 *
	 * @param   mixed  $where  An associative array to use as the where clause, or a primary key
	 * @return  Jelly  Returns $this
	 */
	public function count($where = NULL)
	{
		$meta = $this->meta();
		
		if (is_int($where) || is_string($where))
		{
			$this->where($meta->primary_key, '=', $where);
		}
		// Add the where
		else if (is_array($where))
		{
			foreach($where as $column => $value)
			{
				$this->where($column, '=', $value);
			}
		}
		
		$query = $this->build(Database::SELECT);
	
		return $query->select(array('COUNT("*")', 'total'))
			->from($meta->table)
			->execute($meta->db)
			->get('total');
	}
	
	/**
	 * Returns whether or not that model is related to the 
	 * $model specified. This only works with relationships
	 * where the model "has" another model or models:
	 * 
	 * has_many, has_one, many_to_many
	 *
	 * @param string $name 
	 * @param mixed $models
	 * @return boolean
	 * @author Jonathan Geiger
	 */
	public function has($name, $models)
	{
		$ids = array();
		
		// Everything comes in as an array of ids, so we must convert things like
		// has ('alias', 1), or has('alias', $some_jelly_model)
		if (!is_array($models) && !$models instanceof Database_Result)
		{
			if (is_object($models))
			{
				$models = $models->id();
			}
			
			$ids = array($models);
		}
		// Construct the primary keys of the models. That's all we'll need
		else
		{
			foreach ($models as $model)
			{
				if (is_object($model))
				{
					$model = $model->id();
				}
				
				$ids[] = $model;
			}
		}
		
		$fields = $this->meta()->fields;
		
		// Proxy to the field. It handles everything
		if (isset($fields[$name]) AND is_callable(array($fields[$name], 'has')))
		{
			return $fields[$name]->has($model, $ids);
		}
		
		return FALSE;
	}
	
	/**
	 * Loads a single row or multiple rows
	 *
	 * @param  mixed  $where  an array or id to load 
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function load($where = NULL, $limit = NULL)
	{
		$meta = $this->meta();
		
		// Apply the limit
		if (is_int($where) || is_string($where))
		{
			$this->where($meta->primary_key, '=', $where);
			$limit = 1;
		}
		
		// Simple where clause
		else if (is_array($where))
		{
			foreach($where as $column => $value)
			{
				$this->where($column, '=', $value);
			}
		}
		
		// Set the working query
		$query = $this->build(Database::SELECT);
		$query->from($meta->table);
		
		// limit() is overloaded so that if the second argument exists 
		// the call will override what's passed here for $limit. This allows us
		// to set a limit before load and have it load single rows directly into the object
		if (isset($this->_db_applied['limit'][1]) && $this->_db_applied['limit'][0] == 1)
		{
			$limit = 1;
		}
		
		// Apply the limit if we can
		if ($limit !== NULL)
		{
			$query->limit($limit);
		}
		
		// We can load directly into the model
		if ($limit === 1)
		{
			$result = $query->execute($meta->db);
			
			// Ensure we have something
			if (count($result))
			{
				$values = $result->current();
				
				// Set this flag so that the values are loaded into the correct place
				$this->_loading = TRUE;
				
				// Insert the values, make sure to reverse alias them
				$this->values($values, TRUE);
				
				// We're good!
				$this->_loaded = $this->_saved = TRUE;
				$this->_changed = array();
				$this->_loading = FALSE;
			}

			return $this->end();
		}
		else
		{
			// Apply sorting options
			foreach($meta->sorting as $column => $direction)
			{
				$query->order_by($this->alias($column), $direction);
			}
			
			return $query->as_object(get_class($this))->execute($meta->db);
		}
	}
	
	/**
	 * Returns whether or not the model is loaded
	 *
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function loaded()
	{	
		return $this->_loaded;
	}
	
	/**
	 * Creates a new record based on the current model
	 *
	 * @return mixed
	 * @author Jonathan Geiger
	 **/
	public function save($save_related = TRUE)
	{
		$meta = $this->meta();
		
		if ($meta->validate_on_save)
		{
			$this->validate();
		}
		
		// Stuff that will be inserted
		$values = array();
		
		// These will be processed later
		$relations = array();
		
		// Run through the main table data
		foreach($meta->fields as $column => $field)
		{			
			// Only add actual columns
			if ($field->in_db)
			{	
				if (isset($this->_changed[$column]))
				{
					$this->_original[$column] = $values[$field->column] = $field->save($this, $this->_changed[$column]);
				}
				// Set default data. Careful not to override unchanged data!
				else if ($this->_original[$column] == $meta->defaults[$column] && !$field->primary)
				{
					$this->_original[$column] = $values[$field->column] = $field->save($this, $field->default);
				}
			}
			else
			{
				$relations[$column] = $field;
			}
		}
		
		// Set this just in case it doesn't change with the insert/update
		$id = $this->id();
		
		// Check if we have a loaded object, in which case it's an update
		if ($this->_loaded)
		{
			// Do we even have to update anything in the main table?
			if ($values)
			{
				DB::update($meta->table)
					->set($values)
					->where($this->alias($meta->primary_key), '=', $this->id())
					->execute($meta->db);

				// Has id changed? 
				if (isset($values[$meta->primary_key]))
				{
					$id = $values[$meta->primary_key];
				}
			}
		}
		else
		{
			list($id) = DB::insert($meta->table)
						->columns(array_keys($values))
						->values($values)
						->execute($meta->db);
		}
		
		// Update the primary key
		$this->_original[$meta->primary_key] = $id;
		
		if ($save_related)
		{
			// Load the relations
			foreach($relations as $column => $field)
			{				
				if (isset($this->_changed[$column]))
				{
					$this->_original[$column] = $field->save($this, $this->_changed[$column]);
				}
			}
		}

		// We're good!
		$this->_loaded = $this->_saved = TRUE;
		$this->_changed = array();
		
		// Delete the last queries
		$this->end();
		
		return $this;
	}
	
	/**
	 * Whether or not the model is saved
	 *
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function saved()
	{	
		return $this->_saved;
	}
	
	/**
	 * Deletes a single or multiple records
	 * 
	 * If we're loaded(), it just deletes this object, otherwise it deletes 
	 * whatever the query matches. 
	 *
	 * @param $where A simple where statement
	 * @return self
	 * @author Jonathan Geiger
	 **/
	public function delete($where = NULL)
	{
		$meta = $this->meta();
		
		// Delete an id
		if (is_int($where) || is_string($where))
		{
			$this->where($meta->primary_key, '=', $where);
		}
		// Simple where clause
		else if (is_array($where))
		{
			foreach($where as $column => $value)
			{
				$this->where($column, '=', $value);
			}
		}
		
		// Are we loaded? Then we're just deleting this record
		if ($this->_loaded)
		{
			$this->where($meta->primary_key, '=', $this->id());
		}
		
		// Here goes nothing. NO LIMIT CLAUSE?!?!
		$this->execute(Database::DELETE);
		
		// Re-initialize to an empty object
		$this->reset();
		
		return $this;
	}
	
	/**
	 * Resets the model to an empty state
	 *
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function reset()
	{
		// Reset all queries
		$this->end();

		// Reset all of the data back to its default state
		$this->_original = $this->meta()->defaults;
		
		// Reset the various states
		$this->_loaded = $this->_saved = FALSE;
		
		// Clear the cache of values
		$this->_changed = array();
		
		return $this;
	}
	
	/**
	 * Validates and filters the data
	 *
	 * @throws Validate_Exception
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function validate()
	{
		// Only validate changed data if it's an update
		if ($this->_loaded)
		{
			$data = $this->_changed;
		}
		// Validate all data on insert
		else
		{
			$data = $this->_changed + $this->_original;
		}
		
		// Create the validation object
		$data = Validate::factory($data);
		
		// Loop through all columns, adding rules where data exists
		foreach ($this->meta()->fields as $column => $field)
		{
			// Do not add any rules for this field
			if (!$data->offsetExists($column))
			{
				continue;
			}

			$data->label($column, $field->label);

			if ($field->filters)
			{
				$data->filters($column, $field->filters);
			}

			if ($field->rules)
			{
				$data->rules($column, $field->rules);
			}

			if ($field->callbacks)
			{
				$data->callbacks($column, $field->callbacks);
			}			
		}

		if ($data->check())
		{
			// Insert filtered data back into the model
			$this->values($data->as_array());
		}
		else
		{
			throw new Validate_Exception($data);
		}
	}
	
	/**
	 * Returns data as an array
	 *
	 * @param string $verbose 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function as_array($verbose = FALSE)
	{
		$result = array();
		
		foreach($this->meta()->fields as $column => $field)
		{
			$result[$column] = $this->get($column, $verbose);
		}
		
		return $result;
	}
	
	/**
	 * Sets an array of values based on their key
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	public function values(array $values, $reverse = FALSE)
	{
		// We'll remove elements from this array when they've been found
		$unmapped = $values;
		
		// Why this way? Because it allows the model to have 
		// multiple fields that are based on the same column
		foreach ($this->meta()->fields as $alias => $field)
		{
			$column = ($reverse) ? $field->column : $alias;
			
			// Remove found values from the unmapped
			if (isset($unmapped[$column]))
			{
				unset($unmapped[$column]);
			}
			
			if (isset($values[$column]))
			{
				$this->set($alias, $values[$column]);
			}
		}
		
		// Set any left over unmapped values
		if ($unmapped)
		{
			$this->_unmapped = array_merge($this->_unmapped, $unmapped);
		}
		
		return $this;
	}
	
	/**
	 * Returns the value of the primary key for the row
	 *
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function id()
	{
		return $this->get($this->meta()->primary_key);
	}
	
	/**
	 * Returns the value of the model's primary value
	 *
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function name()
	{
		return $this->get($this->meta()->name_key);
	}
	
	/**
	 * Returns a view object the represents the field. If $prefix is an array,
	 * it will be used for the data and $prefix will be set to the default.
	 *
	 * @param string $name The field to render
	 * @param string|array $prefix 
	 * @param string $data 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function input($name, $prefix = NULL, $data = array())
	{
		if (isset($this->meta()->fields[$name]))
		{
			// More data munging. But it makes the API so much more intuitive
			if (is_array($prefix))
			{
				$data = $prefix;
				$prefix = NULL;
			}
			
			// Set a default prefix if it's NULL
			if ($prefix === NULL)
			{
				$prefix = 'jelly/field';
			}
			
			// Ensure there is a default value. Some fields overridde this
			$data['value'] = $this->get($name, FALSE);
			$data['model'] = $this;
			
			return $this->_fields[$name]->input($prefix, $data);
		}
	}
	
	/**
	 * Returns the raw query builder query, executed. The args are 
	 * slightly different depending on the $type to execute:
	 * 
	 * If the type is a Database::INSERT or Database::UPDATE, and the 
	 * second argument is an array, the second argument is assumed to be 
	 * the data to insert or update (the keys of the array will be aliased).
	 * The third argument then becomes $as_object.
	 * 
	 * Otherwise, $as_object is the second argument.
	 * 
	 * @param const $type A Database constant type to use for the query
	 * @param mixed $data Depends on the above documentation
	 * @param mixed $as_object Depends on the above documentation
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function execute($type = Database::SELECT, $data = NULL, $as_object = NULL)
	{
		$query = $this->build($type);
		$meta = $this->meta();
		
		// Have we got a from for SELECTS?
		if ($type === Database::SELECT && !isset($this->_db_applied['from']))
		{
			$query->from($meta->table);
		}
		// All other methods require table() to be set
		else if ($type !== Database::SELECT && !isset($this->_db_applied['table']))
		{
			$query->table($meta->table);
		}
		
		// Perform a little arg-munging since UPDATES and INSERTS accept a data parameter
		if ($type === Database::UPDATE && is_array($data))
		{
			// Since we're out of the Jelly, we have to alias manually
			foreach($data as $column => $value)
			{
				$query->value($this->alias($column), $value);
			}
		}
		else if ($type === Database::INSERT && is_array($data))
		{
			// Keys have to be manually aliased
			$columns = array();
			$values = array();
			foreach ($data as $column => $value)
			{
				$columns[] = $this->alias($column);
				$values[] = $value;
			}
			
			$query->columns($columns);
			$query->values($values);
		}
		else
		{
			$as_object = $data;
		}
		
		// Return a StdClass. Much faster
		if ($as_object === NULL)
		{
			$query->as_object(get_class($this));
		}
		// Allow custom classes
		else if (is_string($as_object))
		{
			$query->as_object($as_object);
		}
		// Return as an array
		else
		{
			$query->as_array();
		}
		
		return $query->execute($meta->db);
	}
	
	/**
	 * Initializes the Database Builder to given query type
	 *
	 * @param   int  Type of Database query
	 * @return  ORM
	 */
	public function build($type)
	{
		$meta = $this->meta();
		
		// Construct new builder object based on query type
		switch ($type)
		{
			case Database::SELECT:
				$this->_db_builder = DB::select();
				break;
			case Database::UPDATE:
				$this->_db_builder = DB::update($meta->table);
				break;
			case Database::INSERT:
				$this->_db_builder = DB::insert($meta->table);
				break;
			case Database::DELETE:
				$this->_db_builder = DB::delete($meta->table);
				break;
		}
		
		// Process pending database method calls
		foreach ($this->_db_pending as $method)
		{
			$name = $method['name'];
			$args = $method['args'];

			$this->_db_applied[$name] = $args;
			
			if (!method_exists($this->_db_builder, $method['name']))
			{
				continue;
			}
			
			switch (count($args))
			{
				case 0:
					$this->_db_builder->$name();
				break;
				case 1:
					$this->_db_builder->$name($args[0]);
				break;
				case 2:
					$this->_db_builder->$name($args[0], $args[1]);
				break;
				case 3:
					$this->_db_builder->$name($args[0], $args[1], $args[2]);
				break;
				case 4:
					$this->_db_builder->$name($args[0], $args[1], $args[2], $args[3]);
				break;
				default:
					// Here comes the snail...
					call_user_func_array(array($this->_db_builder, $name), $args);
				break;
			}
		}

		return $this->_db_builder;
	}
	
	/**
	 * Resets the database builder
	 *
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function end()
	{
		$this->_db_builder = NULL;
		$this->_db_applied = array();
		$this->_db_pending = array();
		
		return $this;
	}
}