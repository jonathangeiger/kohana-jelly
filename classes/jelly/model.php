<?php defined('SYSPATH') or die('No direct script access.');

abstract class Jelly_Model
{			
	/**
	 * Factory for generating models
	 *
	 * @param string $model 
	 * @return object_name
	 * @author Jonathan Geiger
	 */
	public static function factory($model)
	{
		$class = "Model_".$model;
		return new $class;
	}
	
	/**
	 * @var string The table this model represents
	 */
	protected $_table = '';
	
	/**
	 * @var string The primary key
	 */
	protected $_primary_key;
	
	/**
	 * @var string The title key
	 */
	protected $_name_key = 'name';
	
	/**
	 * @var string The model name
	 */
	protected $_model = '';
	
	/**
	 * @var array A map to the resource's data and how to process each column.
	 */
	protected $_map = array();

	/**
	 * @var array Unmapped data, that is still accessible
	 */
	protected $_unmapped = array();
	
	/**
	 * @var array A cache of mapped values
	 */
	protected $_cache = array();
	
	/**
	 * @var array Original data
	 */
	protected $_original = array();
	
	/**
	 * @var array Changed data
	 */
	protected $_changed = array();

	/**
	 * @var boolean Whether or not the model is loaded
	 */
	protected $_loaded = FALSE;
	
	/**
	 * @var boolean Whether or not the model is saved
	 */
	protected $_saved = FALSE;
	
	/**
	 * @var array An array of ordering options for selects
	 */
	protected $_sorting = array();
	
	/**
	 * @var array Data that is automatically loaded into the model on each instantiation
	 */
	protected $_preload_data = array();
	
	/**
	 * @var boolean Whether or not the model has been inited. A flag for mysql_fetch_object
	 */
	protected $_init = FALSE;

	/**
	 * @var string The database key to use for connection
	 */
	protected $_db = 'default';
	protected $_db_applied = array();
	protected $_db_pending = array();
	protected $_db_reset   = TRUE;
	protected $_db_builder;
	
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
	 * Calls initialize() and sets up the model
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	public function __construct($id = NULL)
	{
		// Set the model if there is no default
		if (!$this->_model)
		{
			$this->_model = substr(strtolower(get_class($this)), 6);
		}
		
		// Set the table if there is no default
		if (!$this->_table)
		{
			$this->_table = inflector::plural($this->_model);
		}
		
		// And we're off
		$this->_init();
		
		// Have an id? Attempt to load it
		if (is_int($id))
		{
			$this->load($id);
		}
	}

	/**
	 * Performs some default-settings on the map.
	 *
	 * @return void
	 * @author Jonathan Geiger
	 */
	private function _init()
	{
		// Call the main initialization routine, which expects 
		// initialize() to set up the resource map
		$this->initialize();
		
		// Initialize all of the columns
		foreach ($this->_map as $column => $field)
		{
			// Initialize the field with a copy of the model and column
			$field->initialize($this, $column);
			
			// See if we need to automatically find the primary key
			if ($field->primary && empty($this->_primary_key))
			{
				$this->_primary_key = $column;
			}
		}
		
		// Add the values stored by __set
		if (is_array($this->_preload_data) && !empty($this->_preload_data))
		{
			$this->values($this->_preload_data, TRUE);
		}
		
		// Finished initialized
		$this->_init = TRUE;	
	}

	/**
	 * Expected to initialize $_map
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	abstract protected function initialize();
	
	/**
	 * Proxies to get for dynamic getting of properties
	 *
	 * @param string $name 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function __get($name)
	{
		return $this->get($name, TRUE);
	}
	
	/**
	 * Gets the internally represented value from a field
	 *
	 * @param string $name 
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function get($name, $verbose = TRUE)
	{
		if (isset($this->_map[$name]))
		{			
			// Check the cache
			if (!isset($this->_cache[$name][$verbose]))
			{
				$this->_cache[$name][$verbose] = $this->_map[$name]->get($verbose);
			}

			// Fill the cache
			return $this->_cache[$name][$verbose];
		}
		// Return unmapped data from custom queries
		else if (isset($this->_unmapped[$name]))
		{
			return $this->_unmapped[$name];
		}
	}
	
	/**
	 * Proxies to set for dynamic getting of properties
	 *
	 * @param string $name 
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function __set($name, $value)
	{
		// Being set by mysql_fetch_object, store the values for the constructor
		if ($this->_init === FALSE)
		{
			$this->_preload_data[$name] = $value;
			$this->_loaded = TRUE;
			return;
		}
		
		$this->set($name, $value);
	}
	
	/**
	 * Sets values in the fields
	 *
	 * @param string $name 
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($name, $value)
	{
		// Normal, user-initiated set
		if (isset($this->_map[$name]))
		{
			$this->_map[$name]->set($value);
			
			// Clear the cache if need be
			if (isset($this->_cache[$name]))
			{
				unset($this->_cache[$name]);
			}
			
			// Track changes
			$this->_changed[$name] = TRUE;
			$this->_saved = FALSE;
		}
		// Allow setting unmapped data from custom queries
		else
		{
			$this->_unmapped[$name] = $value;
		}
	}
	
	/**
	 * Handles pass-through to database methods. Calls to query methods
	 * (query, get, insert, update) are not allowed. Query builder methods
	 * are chainable.
	 *
	 * @param   string  method name
	 * @param   array   method arguments
	 * @return  mixed
	 */
	public function __call($method, array $args)
	{
		if (in_array($method, self::$_db_methods))
		{
			// Add support for column aliasing
			// Get the edge-cases first
			if ($method == 'select')
			{
				foreach ($args as $i => $arg)
				{
					if (is_array($arg))
					{
						$args[$i][0] = $this->alias($arg[0], NULL, TRUE);
					}
					else
					{
						$args[$i]= $this->alias($arg, NULL, TRUE);
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
						$args[0][$i] = Jelly::factory($table)->_table;
					}
				}
				else
				{
					$args[0] = Jelly::factory($args[0])->_table;
				}
			}
			
			// Join on
			else if ($method == 'on')
			{
				$args[0] = $this->alias($args[0], NULL, TRUE);
				$args[2] = $this->alias($args[2], NULL, TRUE);
			}
			
			// Everything else
			else if (in_array($method, self::$_alias))
			{
				$args[0] = $this->alias($args[0]);
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
	 * Count the number of records for the current query
	 *
	 * @param 	$where 	An associative array to use as the where clause
	 * @return  $this
	 */
	public function count($where = NULL)
	{
		// Add the where
		if (is_array($where))
		{
			foreach($where as $column => $value)
			{
				$this->where($column, '=', $value);
			}
		}
		
		$query = $this->build(Database::SELECT);
	
		return $query->select(array('COUNT("*")', 'total'))
			->from($this->_table)
			->execute($this->_db)
			->get('total');
	}
	
	/**
	 * Loads a single row or multiple rows
	 *
	 * @param mixed an array or id to load 
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function load($where = NULL, $limit = NULL)
	{
		// Apply the limit
		if (is_int($where) && $limit === NULL)
		{
			$this->where($this->_primary_key, '=', $where);
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
		$query->from($this->_table);
		
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
		
		// Attempt to load it
		if ($limit === 1)
		{
			$result = $query->execute($this->_db);
			
			// Ensure we have something
			if (count($result))
			{
				$values = $result->current();
				
				// If there was no select applied it was likely SELECT *, 
				// so we need to alias the columns
				$this->values($values, TRUE);
				
				// We're good!
				$this->_loaded = TRUE;
				$this->_cache = NULL;
				$this->_changed = NULL;
				$this->_saved = TRUE;
			}

			return $this->end();
		}
		else
		{
			// Apply sorting options
			foreach($this->_sorting as $column => $direction)
			{
				$query->order_by($this->alias($column), $direction);
			}
			
			return $query->as_object(get_class($this))->execute($this->_db);
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
		// Stuff that will be inserted
		$values = array();
		
		// These will be processed later
		$relations = array();
		
		// Run through the main table data
		foreach($this->_map as $column => $field)
		{
			// Only add actual columns
			if ($field->in_db)
			{	
				if (isset($this->_changed[$column]))
				{
					if (isset($data[$column]))
					{
						$this->set($column, $data[$column]);
					}	

					$values[$field->column] = $field->save($this->_loaded);
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
				DB::update($this->_table)
					->set($values)
					->where($this->alias($this->primary_key()), '=', $this->id())
					->execute($this->_db);

				// Has id changed? 
				if (isset($values[$this->_primary_key]))
				{
					$id = $values[$this->_primary_key];
				}
			}
		}
		else
		{
			list($id) = DB::insert($this->_table)
						->columns(array_keys($values))
						->values($values)
						->execute($this->_db);
						
			// Update the primary key
			$this->set($this->_primary_key, $id);
		}
		
		if ($save_related)
		{
			// Load the relations
			foreach($relations as $column => $field)
			{
				if (isset($this->_changed[$column]))
				{
					if (isset($data[$column]))
					{
						$field->set($data[$column]);
					}

					$field->save($id);
				}
			}
		}

		// We're good!
		$this->_loaded = TRUE;
		$this->_cache = NULL;
		$this->_changed = NULL;
		$this->_saved = TRUE;
		
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
		// Delete an id
		if (is_int($where))
		{
			$this->where($this->_primary_key, '=', $where);
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
			$this->where($this->_primary_key, '=', $this->id());
		}
		
		// Set the working query
		$query = $this->build(Database::DELETE);
		$query->table($this->_table);
		
		// Here goes nothing
		$query->execute($this->_db);
		
		// Clean up the object
		$this->_loaded = FALSE;
		$this->_saved = FALSE;
		$this->_changed = array();
		$this->_cache = NULL;
		
		// Re-initialize to an empty object
		$this->_init();
		
		return $this;
	}
	
	/**
	 * Validates and filters the data
	 *
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function validate()
	{
		// Only validate changed data if it's an update
		if ($this->_loaded)
		{
			$data = array_intersect_key($this->as_array(), $this->_changed);
		}
		// Validate all data on insert
		else
		{
			$data = $this->as_array();
		}
		
		// Create the validation object
		$data = Validate::factory($data);
		
		// Loop through all columns, adding rules where data exists
		foreach ($this->_map as $column => $field)
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
			
			// Only === TRUE indicates success
			return TRUE;
		}
		else
		{
			return $data;
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
		
		foreach($this->_map as $column => $field)
		{
			$result[$column] = $this->get($column, $verbose);
		}
		
		return $result;
	}
	
	/**
	 * Returns the actual column name of an aliased column
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	public function alias($field = NULL, $model = NULL, $join = NULL)
	{	
		// Save these in case we can't find anything
		$table = $model;
		$column = $field;
						
		// Default to this model
		if ($model === NULL)
		{
			$model = $this->_model;
		}
		
		// table.field coming in as $field
		if (strpos($field, '.') !== FALSE)
		{
			list($model, $field) = explode('.', $field);
		}
		
		// Attempt to find a valid model to work with
		if ($model == $this->_model || $model == $this->_table)
		{
			$model = $this;
		}
		else if (class_exists('Model_'.$model, FALSE) 
				|| Kohana::find_file('classes', 'model/'.str_replace('_', '/', $model)))
		{
			$model = Jelly::factory($model);
		}
		else
		{
			$model = FALSE;
		}
		
		// We can't do anything if we don't have a model
		if ($model)
		{
			$table = $model->_table;
			
			// Search for a field
			if ($field && isset($model->_map[$field]))
			{
				$column = $model->_map[$field]->column;
			}
			// Test for *
			else if ($field === '*')
			{
				$column = '*';
			}
		}
				
		// Put it all back together
		if ($join && $table && $column)
		{
			return $table.'.'.$column;
		}
		else if ($column)
		{
			return $column;
		}
		else if ($table)
		{
			return $table;
		}
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
		foreach ($this->_map as $alias => $field)
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
	 * Returns the db group
	 *
	 * @return string
	 * @author Jonathan Geiger
	 */
	public function db()
	{
		return $this->_db;
	}
	
	/**
	 * Returns the model name
	 *
	 * @return string
	 * @author Jonathan Geiger
	 */
	public function model_name()
	{
		return $this->_model;
	}	
	
	/**
	 * Returns the model's table name
	 *
	 * @return string
	 * @author Jonathan Geiger
	 */
	public function table_name()
	{
		return $this->_table;
	}
	
	/**
	 * Returns the name of the primary key for the table
	 *
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function primary_key()
	{	
		return $this->_primary_key;
	}
	
	/**
	 * Returns the name of the primary key for the table
	 *
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function name_key()
	{
		return $this->_name_key;
	}
	
	/**
	 * Returns the value of the primary key for the row
	 *
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function id()
	{
		return $this->_map[$this->_primary_key]->get();
	}
	
	/**
	 * Returns the value of the model's primary value
	 *
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function name()
	{
		return $this->_map[$this->_name_key]->get();
	}
	
	/**
	 * Returns the raw query builder query, executed.
	 *
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function execute($type = Database::SELECT, $data = NULL, $as_object = NULL)
	{
		$query = $this->build($type);
		
		// Have we got a from for SELECTS?
		if ($type === Database::SELECT && !isset($this->_db_applied['from']))
		{
			$query->from($this->_table);
		}
		// All other methods require table() to be set
		else if ($type !== Database::SELECT && !isset($this->_db_applied['table']))
		{
			$query->table($this->_table);
		}
		
		// Perform a little arg-munging since UPDATES accept a data parameter
		if ($type === Database::UPDATE && is_array($data))
		{
			// Since we're out of the Jelly, we have to alias manually
			foreach($data as $column => $value)
			{
				$query->value($this->alias($column), $value);
			}
		}
		else
		{
			$as_object = $data;
		}
		
		// Return a StdClass. Much faster
		if ($as_object === NULL)
		{
			$query->as_object();
		}
		// Return as a Jelly, SLOW for large result sets
		else if ($as_object === NULL)
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
		
		return $query->execute($this->_db);
	}
	
	/**
	 * Initializes the Database Builder to given query type
	 *
	 * @param   int  Type of Database query
	 * @return  ORM
	 */
	public function build($type)
	{
		// Construct new builder object based on query type
		switch ($type)
		{
			case Database::SELECT:
				$this->_db_builder = DB::select();
				break;
			case Database::UPDATE:
				$this->_db_builder = DB::update($this->_table);
				break;
			case Database::INSERT:
				$this->_db_builder = DB::insert($this->_table);
				break;
			case Database::DELETE:
				$this->_db_builder = DB::delete($this->_table);
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