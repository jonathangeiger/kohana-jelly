<?php defined('SYSPATH') or die('No direct script access.');

abstract class Jelly_Model
{			
	/**
	 * Factory for generating models
	 *
	 * @param string $model 
	 * @return object
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
	 * @var array A cache of mapped values
	 */
	protected $_cache = array();
	
	/**
	 * @var array Changed data
	 */
	protected $_changed = array();

	/**
	 * @var boolean Whether or not the model is loaded
	 */
	protected $_loaded = FALSE;
	
	/**
	 * @var boolean Whether or not the model is loaded
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
		'having_close', 'and_having_close', 'or_having_close', 'order_by', 'limit', 'offset', 'cached'
	);
	
	/**
	 * @var array DB methods that must be aliased
	 */
	protected static $_alias = array
	(
		'where', 'and_where', 'or_where', 'select', 'from', 'join', 'on', 'group_by',
		'having', 'and_having', 'or_having', 'order_by',
	);
	
	/**
	 * Calls initialize() and sets up the model
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	public function __construct()
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
		
		// Call the main initialization routine, which expects 
		// initialize() to set up the resource map
		$this->initialize();
		
		// Map the map
		$this->_map();
		
		// Add the values stored by __set
		if (is_array($this->_preload_data) && !empty($this->_preload_data))
		{
			$this->values($this->_preload_data, TRUE);
		}
		
		// Finished initialized
		$this->_preload_data = NULL;
	}
	
	/**
	 * Expected to initialize $_map
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	abstract protected function initialize();
	
	/**
	 * Performs some default-settings on the map.
	 *
	 * @return void
	 * @author Jonathan Geiger
	 */
	private function _map()
	{
		foreach ($this->_map as $column => $field)
		{
			// Initialize the field with a copy of the model and column
			$field->initialize($this, $column);
			
			// See if we need to automatically find the primary key
			if ($field->primary() && empty($this->_primary_key))
			{
				$this->_primary_key = $column;
			}
		}
	}
	
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
		if (is_array($this->_preload_data))
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
					// Ignore aliased selects
					if (is_array($arg))
					{
						unset($args[$i]);
					}
					else
					{
						$args[$i]= $this->alias($arg);
					}
				}
			}
			
			// Table alias
			if ($method == 'from' || $method == 'join')
			{
				if (is_array($args[0]))
				{
					foreach($args as $index => $table)
					{
						$args[$i] = Jelly::factory($table)->_table;
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
				$args[0] = $this->alias($args[0]);
				$args[2] = $this->alias($args[2]);
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
			$this->limit($limit);
		}
		
		$table = $this->_table;
		
		// Set the working query
		$query = $this->build(Database::SELECT);
		$query->from($table);
		
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
		}
		else
		{
			// Apply sorting options
			foreach($this->_sorting as $column => $direction)
			{
				$query->order_by($column, $direction);
			}
			
			return $query->as_object(get_class($this))->execute($this->_db);
		}
		
		return $this;
	}
	
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
	public function save()
	{
		// Stuff that will be inserted
		$values = array();
		
		// These will be processed later
		$relations = array();
		
		// Run through the main table data
		foreach($this->_map as $column => $field)
		{
			// Skip the primary key; it will be auto-incremented
			if ($field->primary())
			{
				continue;
			}
			
			// Only add actual columns
			if ($field->in_db())
			{	
				if (isset($this->_changed[$column]))
				{
					if (isset($data[$column]))
					{
						$this->set($column, $data[$column]);
					}	

					$values[$field->column()] = $field->save($this->_loaded);
				}
			}
			else
			{
				$relations[$column] = $field;
			}
		}
		
		// Check if we have a loaded object, in which case its an update
		if ($this->_loaded)
		{
			DB::update($this->_table)
				->set($values)
				->where($this->primary_key(), '=', $this->id())
				->execute($this->_db);
				
			// Has id changed? 
			if (isset($values[$this->_primary_key]))
			{
				$id = $values[$this->_primary_key];
			}
			else
			{
				$id = $this->id();
			}
		}
		else
		{
			list($id) = DB::insert($this->_table)
						->columns(array_keys($values))
						->values($values)
						->execute($this->_db);
		}
		
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
			
		// Reload		
		$this->load($id);
		
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
		$data = Validate::factory($this->as_array());
		
		foreach ($this->_map as $column => $field)
		{
			if (!$data->offsetExists($column))
			{
				// Do not add any rules for this field
				continue;
			}

			$data->label($column, $field->label());

			if ($field->filters())
			{
				$data->filters($column, $field->filters());
			}

			if ($field->rules())
			{
				$data->rules($column, $field->rules());
			}

			if ($field->callbacks())
			{
				$data->callbacks($column, $field->callbacks());
			}			
		}

		if (!$data->check())
		{
			throw new Validate_Exception($data);
		}

		return $data->as_array();
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
	public function alias($column, $table = NULL)
	{
		if (empty($table))
		{
			if (isset($this->_map[$column]))
			{
				return $this->_map[$column]->column();
			}
		}
		
		// Save the original if we can't find the table
		$original = $column;
		
		// Handles aliased columns
		if (is_array($column))
		{
			$column = $args[0];
		}
		
		// Check for a table		
		if (strpos($column, '.') !== FALSE)
		{
			list($table, $column) = explode('.', $column);
		}
		
		if ($table == $this->_model || $table == $this->_table)
		{
			if (isset($this->_map[$column]))
			{
				return $this->table_name().'.'.$this->_map[$column]->column();	
			}	
		}
		else
		{
			if (Kohana::auto_load('model/'.str_replace('_', '/', $table)))
			{
				// Find the actual table name
				$table = Jelly::Factory($table);

				if (isset($table->_map[$column]))
				{
					return $table->table_name().'.'.$table->_map[$column]->column();
				}
				else
				{
					return $table->table_name().'.'.$column;
				}
			}
		}
		
		return $original;
	}
	
	/**
	 * Sets an array of values based on their key
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	public function values(array $values, $reverse = FALSE)
	{
		// Set the data
		foreach ($this->_map as $alias => $field)
		{			
			if ($reverse)
			{
				$column = $field->column();
			}
			else
			{
				$column = $alias;
			}
			
			// Ensure there's something to work with
			if (!isset($values[$column]))
			{
				continue;
			}
						
			$this->set($alias, $values[$column]);
		}
		
		return $this;
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
			case Database::DELETE:
				$this->_db_builder = DB::delete($this->_table);
		}
		
		// Process pending database method calls
		foreach ($this->_db_pending as $method)
		{
			$name = $method['name'];
			$args = $method['args'];

			$this->_db_applied[$name] = $args;

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
	protected function reset_db()
	{
		$this->_db_builder = NULL;
		$this->_db_applied = array();
		$this->_db_pending = array();
	}
}