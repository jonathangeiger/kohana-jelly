<?php defined('SYSPATH') or die('No direct script access.');

abstract class Jelly_Core_Builder extends Database_Query_Builder_Select
{	
	/**
	 * @var Jelly_Meta The first model to come in a from is cached here and used as the canonical model
	 */	
	protected $_meta = NULL;
	
	/**
	 * @var array Data to be set, if this is an insert or update
	 */
	protected $_set = array();
	
	/**
	 * @var int The query type
	 */
	protected $_type;
	
	/**
	 * @var boolean The result, if the query has been executed
	 */
	protected $_result = FALSE;
	
	/**
	 * Constructs a new Jelly_Builder instance. 
	 * 
	 * $model and $type are not actually allowed to be NULL, they 
	 * are just set so because PHP throws errors otherwise, since
	 * it doesn't conform the parent's definition.
	 *
	 * @param string $model 
	 * @author Expressway Video
	 */
	public function __construct($model = NULL, $type = NULL)
	{
		if (!$model OR !$type)
		{
			throw new Kohana_Exception(get_class($this) . ' requires $model and $type to be set in the constructor');
		}
		
		// Hopefully we have a model to work with
		$this->_meta = Jelly_Meta::get($model);
		
		// Can we set the default from?
		if ($this->_meta)
		{
			$this->from($this->_meta->table());
			
			// Apply sorting and with if necessary
			if ($type === Database::SELECT)
			{
				foreach ($this->_meta->sorting() as $column => $direction)
				{
					$this->order_by($column, $direction);
				}
				
				foreach ($this->_meta->load_with() as $relationship)
				{
					$this->with($this->_meta->load_with());
				}
			}
			
			// Default to loading as Jellys
			$this->as_object(Jelly_Meta::class_name($this->_meta->model()));
		}
		else
		{
			$this->from($model);
		}
		
		// Save this for building the query later on
		$this->_type = $type;
		
		parent::__construct();
	}
	
	/**
	 * Builds the builder into a native query
	 *
	 * @param string $type 
	 * @return void
	 * @author Expressway Video
	 */
	public function execute($db = NULL)
	{
		// Don't repeat queries
		if ($this->_result)
		{
			return $this->_result;
		}
		
		switch($this->_type)
		{
			case Database::SELECT:
				$query = DB::select();
				$query->_from		= $this->_from;
				$query->_select 	= $this->_select;
				$query->_distinct	= $this->_distinct;
				$query->_offset 	= $this->_offset;
				$query->_limit 		= $this->_limit;
				$query->_join 		= $this->_join;
				$query->_group_by 	= $this->_group_by;
				$query->_order_by 	= $this->_order_by;
				$query->_as_object  = $this->_as_object;
				break;
				
			case Database::UPDATE:
				$query = DB::update(current($this->_from));
				break;
				
			case Database::INSERT:
				$query = DB::insert(current($this->_from));
				break;		
				
			case Database::DELETE:
				$query = DB::delete(current($this->_from));
				break;
				
			default:
				throw new Kohana_Exception("Unsupported database constant");
				break;
		}
		
		// Copy over the common conditions to a new statement
		$query->_where = $this->_where;
		
		// Convert sets
		if ($this->_set && $this->_type === Database::INSERT)
		{
			$query->columns(array_keys($this->_set));
			$query->values($this->_set);
		}
		
		if ($this->_set && $this->_type === Database::UPDATE)
		{
			$query->set($this->_set);
		}
		
		// See if we can use a proper database
		if ($db === NULL && is_object($this->_meta))
		{
			$db = $this->_meta->db();
		}
		// Revert to the default database group
		else
		{
			$db = 'default';
		}
		
		// We've now left the Jelly
		$this->_result = $query->execute($db);
		
		return $this->_result;
	}
	
	/**
	 * Creates a new "AND WHERE" condition for the query.
	 *
	 * @param   mixed   column name or array($column, $alias) or object
	 * @param   string  logic operator
	 * @param   mixed   column value
	 * @return  $this
	 */
	public function and_where($column, $op, $value)
	{
		return parent::and_where($this->_column($column, TRUE), $op, $value);
	}

	/**
	 * Creates a new "OR WHERE" condition for the query.
	 *
	 * @param   mixed   column name or array($column, $alias) or object
	 * @param   string  logic operator
	 * @param   mixed   column value
	 * @return  $this
	 */
	public function or_where($column, $op, $value)
	{
		return parent::or_where($this->_column($column, TRUE), $op, $value);
	}
	
	
	/**
	 * Choose the columns to select from.
	 *
	 * @param   mixed  column name or array($column, $alias) or object
	 * @param   ...
	 * @return  $this
	 */
	public function select($columns = NULL)
	{
		return $this->select_array(func_get_args());
	}

	/**
	 * Choose the columns to select from, using an array.
	 *
	 * @param   array  list of column names or aliases
	 * @return  $this
	 */
	public function select_array(array $columns)
	{
		foreach ($columns as $i => $table)
		{
			if (is_array($table))
			{
				$args[$i][0] = $this->_column($table[0], TRUE);
			}
			else
			{
				$args[$i] = $this->_column($table, TRUE);
			}
		}
		
		return parent::select_array($columns);
	}

	/**
	 * Choose the tables to select "FROM ..."
	 *
	 * @param   mixed  table name or array($table, $alias) or object
	 * @param   ...
	 * @return  $this
	 */
	public function from($tables)
	{
		$tables = func_get_args();
		
		foreach ($tables as $i => $table)
		{
			// Cache the first meta
			if (!$this->_meta)
			{
				$model = $table;
				
				if (is_array($model))
				{
					$model = $model[0];
				}
				
				if ($model = Jelly_Meta::get($model))
				{
					$this->_meta = $model;
				}
			}
			
			if (is_array($table))
			{
				$table[0] = $this->_table($table[0]);
			}
			else
			{
				$table = $this->_table($table);
			}
			
			parent::from($table);
		}
		
		return $this;
	}

	/**
	 * Adds addition tables to "JOIN ...".
	 *
	 * @param   mixed   column name or array($column, $alias) or object
	 * @param   string  join type (LEFT, RIGHT, INNER, etc)
	 * @return  $this
	 */
	public function join($table, $type = NULL)
	{
		if (is_array($table))
		{
			$table[0] = $this->_table($table[0]);
		}
		else
		{
			$table = $this->_table($table);
		}
		
		return parent::join($table, $type);
	}

	/**
	 * Adds "ON ..." conditions for the last created JOIN statement.
	 *
	 * @param   mixed   column name or array($column, $alias) or object
	 * @param   string  logic operator
	 * @param   mixed   column name or array($column, $alias) or object
	 * @return  $this
	 */
	public function on($c1, $op, $c2)
	{
		return parent::on($this->_column($c1, TRUE), $op, $this->_column($c2, TRUE));
	}

	/**
	 * Creates a "GROUP BY ..." filter.
	 *
	 * @param   mixed   column name or array($column, $alias) or object
	 * @param   ...
	 * @return  $this
	 */
	public function group_by($columns)
	{
		$columns = func_get_args();
		
		foreach($columns as $i => $column)
		{
			if (is_array($column))
			{
				$columns[$i][0] = $this->_table($column[0]);
			}
			else
			{
				$columns[$i] = $this->_table($column);
			}	
		}

		return parent::group_by($columns);
	}

	/**
	 * Creates a new "AND HAVING" condition for the query.
	 *
	 * @param   mixed   column name or array($column, $alias) or object
	 * @param   string  logic operator
	 * @param   mixed   column value
	 * @return  $this
	 */
	public function and_having($column, $op, $value = NULL)
	{
		return parent::and_having($this->_column($column), $op, $value);
	}

	/**
	 * Creates a new "OR HAVING" condition for the query.
	 *
	 * @param   mixed   column name or array($column, $alias) or object
	 * @param   string  logic operator
	 * @param   mixed   column value
	 * @return  $this
	 */
	public function or_having($column, $op, $value = NULL)
	{
		return parent::or_having($this->_column($column), $op, $value);
	}

	/**
	 * Applies sorting with "ORDER BY ..."
	 *
	 * @param   mixed   column name or array($column, $alias) or object
	 * @param   string  direction of sorting
	 * @return  $this
	 */
	public function order_by($column, $direction = NULL)
	{
		return parent::order_by($this->_column($column), $direction);
	}
	
	/**
	 * Allows setting for UPDATEs or INSERTs
	 *
	 * @param  array $values 
	 * @return $this
	 */
	public function set($values, $alias = TRUE)
	{
		foreach ($values as $key => $value)
		{
			$this->value($key, $value, $alias);
		}
		
		return $this;
	}
	
	/**
	 * Sets a single value
	 *
	 * @param  string $key 
	 * @param  string $value 
	 * @param  string $alias 
	 * @return $this
	 */
	public function value($key, $value, $alias = TRUE)
	{
		if ($alias)
		{
			$key = $this->_column($key);
		}
		
		$this->_set[$key] = $value;
		
		return $this;
	}
	
	/**
	 * Allows joining 1:1 relationships in a single query.
	 *
	 * @param  string $alias 
	 * @return $this
	 */
	public function with($relationship)
	{
		// We'll start with the first one and work our way down
		$paths = explode(":", $relationship);
		$parent = $this->_meta->model();
		$chain = '';
		
		foreach ($paths as $iteration => $path)
		{
			$field = Jelly_Meta::get($parent)->fields($path);

			if (!($field instanceof Jelly_Behavior_Field_Joinable))
			{
				// Entire list is invalid
				break;
			}

			// If we're on the first iteration, the parent path is just the 
			// name of the model, otherwise we use the chain
			if ($iteration === 0)
			{
				$prev_chain = $parent;
			}
			else
			{
				$prev_chain = $chain;
			}
			
			$chain .= ":".$field->name;
					
			// Set the next iteration's parent
			$model = $field->foreign['model'];
			$meta = Jelly_Meta::get($model);
			
			// Select all of the model's fields
			foreach ($meta->fields() as $alias => $select)
			{
				if ($select->in_db)
				{
					// Withs have to manually alias
					$column = $meta->fields($alias)->column;
					
					// We have to manually alias, since the path does not necessarily correspond to the path
					$this->select(array($chain.'.'.$column, $chain.':'.$alias));
				}
			}
			
			// Let the field finish the rest
			$field->with($this, $path, $chain, $prev_chain);
			
			// Model now becomes the parent
			$parent = $model;
		}
		
		return $this;
	}
	
	/**
	 * This is an internal method used for aliasing only things coming 
	 * to the query builder, since they can come in so many formats.
	 * 
	 * This method aliases tables
	 *
	 * @param  string	$table 
	 * @return string
	 */
	protected function _table($model)
	{
		if ($meta = Jelly_Meta::get($model))
		{
			$model = $meta->table();
		}
		
		return $model;
	}
	
	/**
	 * This is an internal method used for aliasing only things coming 
	 * to the query builder, since they can come in so many formats.
	 *
	 * @param  string	$field 
	 * @param  boolean	$join
	 * @return string
	 */
	protected function _column($field, $join = NULL)
	{
		$model = NULL;
		
		// Check for functions
		if (strpos($field, '"') !== FALSE)
		{
			// Quote the column in FUNC("ident") identifiers
			return preg_replace('/"(.+?)"/e', '"\\"".$this->_column("$1")."\\""', $field);
		}
		
		// with() call, aliasing is already completed
		if (strpos($field, ':') !== FALSE)
		{			
			return $field;
		}
		
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
		
		// If the model is NULL, $this's table name or model name
		// We just replace if with the current model's name
		if ($model === NULL || $model == $this->_meta->table())
		{
			$model = $this->_meta->model();
		}
		
		$table = $model;
		$column = $field;
		
		// See if the model is register
		if ($meta = Jelly_Meta::get($model))
		{
			$table = $meta->table();
			
			// Find the field
			if ($field = $meta->fields($field))
			{
				$column = $field->column;
			}
		}
		
		if ($join)
		{
			return $table.'.'.$column;
		}
		else
		{
			return $column;
		}
	}
}