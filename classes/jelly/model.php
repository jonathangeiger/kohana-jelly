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
	 * Gets the internally represented value from a field
	 *
	 * @param string $name 
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function __get($name)
	{
		if (isset($this->_map[$name]))
		{
			return $this->_map[$name]->get();
		}
	}
	
	/**
	 * Sets values in the fields
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
			return;
		}
		
		// Normal, user-initiated set
		if (isset($this->_map[$name]))
		{
			$this->_map[$name]->set($value);
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
						$args[$i] = Model::factory($table)->_table;
					}
				}
				else
				{
					$args[0] = Model::factory($args[0])->_table;
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

			return $this;
		}
		else
		{
			throw new Kohana_Exception('Invalid method :method called in :class',
				array(':method' => $method, ':class' => get_class($this)));
		}
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
			if ($field->primary())
			{
				$this->_primary_key = $column;
			}
		}
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
		// Set the working query
		$query = $this->build(Database::SELECT);
		$query->from($this->_table);
		
		// Apply the limit
		if (is_int($where) && $limit === NULL)
		{
			$query->where($this->alias($this->_primary_key), '=', $where);
			$limit = 1;
		}
		
		// Simple where clause
		else if (is_array($where))
		{
			foreach($where as $column => $value)
			{
				$query->where($this->alias($column), '=', $value);
			}
		}
		
		// Apply the limit if we can
		if ($limit !== NULL)
		{
			$query->limit($limit);
		}
		
		$table = $this->_table;
		
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
	
	public function as_array($verbose = FALSE)
	{
		$result = array();
		
		foreach($this->_map as $column => $field)
		{
			$result[$column] = $field->get($verbose);
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
		if ($table == NULL) $table = $this->_table;
		
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
			// Find the actual table name
			$table = Model::Factory($table);
			
			if (isset($table->_map[$column]))
			{
				return $table->table_name().'.'.$table->_map[$column]->column();
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
		foreach ($this->_map as $column => $field)
		{
			if ($reverse)
			{
				$column = $field->column();
			}
			
			// Ensure there's something to work with
			if (!isset($values[$column]))
			{
				continue;
			}
			
			// Pass it off to the field object, which already 
			// has a reference to $this
			$field->set($values[$column]);
		}
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
	 * Returns the currently applied query builder
	 *
	 * @param string $type 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function build($type)
	{
		return $this->_build($type)->_db_builder;
	}
	
	/**
	 * Initializes the Database Builder to given query type
	 *
	 * @param   int  Type of Database query
	 * @return  ORM
	 */
	protected function _build($type)
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

		return $this;
	}
}