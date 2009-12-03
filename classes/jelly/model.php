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
	 * @var string The database key to use for connection
	 */
	protected $_db = 'default';
	
	/**
	 * @var string The table this model represents
	 */
	protected $_table = '';
	
	/**
	 * @var string The model name
	 */
	protected $_model = '';
	
	/**
	 * @var array A map to the resource's data and how to process each column.
	 */
	protected $_map = array();
	
	/**
	 * @var array Allows us to quickly find fields in the ORM based on their actual name in the database.
	 */
	protected $_reverse_map = array();
	
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
	}
	
	/**
	 * Gets values from the fields
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
		if (isset($this->_map[$name]))
		{
			$this->_map[$name]->set($value);
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
			
			// Create the reverse map at the same time, which allows us to quickly 
			// find fields in the ORM based on their actual name in the database
			$this->_reverse_map[$field->column()] = $column;
		}
	}
	
	/**
	 * Returns the resource map
	 *
	 * @return array
	 * @author Jonathan Geiger
	 **/
	public function map()
	{
		return $this->_map;
	}
	
	/**
	 * Loads a single row or multiple rows
	 *
	 * @param Database_Query_Builder_Select $query 
	 * @param string $limit 
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function load(Database_Query_Builder_Select $query = NULL, $limit = 1)
	{
		// Set the working query
		$query = ($query === NULL) ? DB::select() : $query;
		
		// Ensure we're on the correct table
		$query->from($this->_table);
		
		// Apply the limit
		if ($limit)
		{
			$query->limit($limit);
		}
		
		$table = $this->_table;
		
		// Create the columns to select based on the fields
		foreach ($this->_map as $name => $field)
		{
			// Only load fields actually in the database
			if (!$field->in_db())
			{
				continue;
			}
			
			$column = $field->column();
			$query->select(array("{$table}.{$column}", $name));
			
			// Search on primary keys
			if ($field->primary() && $field->get())
			{
				$query->where("{$table}.{$column}", '=', $field->get());
			}
		}
		
		// Attempt to load it
		if ($limit == 1)
		{
			$result = $query->execute($this->_db);
			
			// Ensure we have something
			if (count($result))
			{
				$values = $result->current();
			}
			
			// Set the values in the object
			$this->values($values);
		}
	}
	
	/**
	 * Sets an array of values based on their key
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	public function values(array $values)
	{
		// Set the data
		foreach ($this->_map as $column => $field)
		{
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
}