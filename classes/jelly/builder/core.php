<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Builder is a class used for query building. It handles
 * automatic aliasing of all models and columns (but also supports
 * unknown models and fields).
 * 
 * Because of the limitations of PHP and Kohana's class structure,
 * it must extend a Database_Query_Builder_Select. However, the 
 * instance is properly transposed into its actual type when compiled
 * or executed.
 * 
 * It is possible to use un-executed() query builder instances in other
 * query builder statements, just as you would with Kohana's native
 * facilities.
 *
 * @package Jelly
 */
abstract class Jelly_Builder_Core extends Kohana_Database_Query_Builder_Select
{	
	/**
	 * @var Jelly_Meta The first model to come in a from is cached here and used as the canonical model
	 */	
	protected $_meta = NULL;
	
	/**
	 * @var array Data to be updated
	 */
	protected $_set = array();
	
	/**
	 * @var array Columns to be inserted
	 */
	protected $_columns = array();
	
	/**
	 * @var array Values to be inserted
	 */
	protected $_values = array();
	
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
	 */
	public function __construct($model = NULL, $type = NULL, $cond = NULL)
	{
		parent::__construct();
		
		if (!$model OR !$type)
		{
			throw new Kohana_Exception(get_class($this) . ' requires $model and $type to be set in the constructor');
		}
		
		// Hopefully we have a model to work with
		$this->_meta = Jelly::meta($model);
		
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
				
				// Do we need to apply an initial condition?
				if ($cond)
				{
					// When conditions are passed, they're automatically limited to 1
					$this->limit(1);
					
					// Primary key
					if (is_int($cond) || is_string($cond))
					{
						$this->where($this->_meta->primary_key(), '=', $cond);
					}
					// Simple where clause
					else if (is_array($cond))
					{
						foreach($cond as $column => $value)
						{
							$this->where($column, '=', $value);
						}
					}
				}
			}
		}
		else
		{
			$this->from($model);
		}
		
		// Default to loading as arrays
		$this->as_object(FALSE);
		
		// Save this for building the query later on
		$this->_type = $type;
	}
	
	/**
	 * Builds the builder into a native query
	 *
	 * @param string $type 
	 * @return void
	 */
	public function execute($db = 'default')
	{
		// Don't repeat queries
		if (!$this->_result)
		{
			// See if we can use a better $db group
			if ($this->_meta)
			{
				$db = $this->_meta->db();
			}
			
			// We've now left the Jelly
			$this->_result = $this->_build()->execute($db);
			
			// Hand it over to Jelly_Result if it's a select
			if ($this->_type === Database::SELECT)
			{
				$model = ($this->_meta) ? $this->_meta->model() : NULL;
				$this->_result = new Jelly_Result($model, $this->_result);
				
				// If the record was limited to 1, we only return that model
				// Otherwise we return the whole result set.
				if ($this->_limit === 1)
				{
					$this->_result = $this->_result->current();
				}
			}
		}
		
		// Hand off the result to the Jelly_Result
		return $this->_result;
	}
	
	/**
	 * Compiles the builder into a usable expression
	 *
	 * @param Database $db 
	 * @return void
	 */
	public function compile(Database $db)
	{
		return $this->_build()->compile($db);
	}
	
	/**
	 * Returns a count with the current where clauses applied.
	 *
	 * @return void
	 */
	public function count()
	{
		$query = $this->_build(Database::SELECT);
		$db = (is_object($this->_meta)) ? $this->_meta->db() : 'default';
		
		// Find the count
		return (int) $query
						->select(array('COUNT("*")', 'total'))
						->execute($db)
						->get('total');
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
				
				if ($model = Jelly::meta($model))
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
	 * Set the values to update with an associative array.
	 *
	 * @param   array   associative (column => value) list
	 * @return  $this
	 */
	public function set(array $pairs, $alias = TRUE)
	{
		foreach ($pairs as $column => $value)
		{
			$this->value($column, $value, $alias);
		}

		return $this;
	}

	/**
	 * Set the value of a single column.
	 *
	 * @param   mixed  table name or array($table, $alias) or object
	 * @param   mixed  column value
	 * @return  $this
	 */
	public function value($column, $value, $alias = TRUE)
	{
		if ($alias)
		{
			$column = $this->_column($column);
		}
		
		$this->_set[$column] = $value;

		return $this;
	}
	
	/**
	 * Set the columns that will be inserted.
	 *
	 * @param   array  column names
	 * @return  $this
	 */
	public function columns(array $columns, $alias = TRUE)
	{
		if ($alias)
		{
			foreach ($columns as $i => $column)
			{
				$columns[$i] = $this->_column($column);
			}
		}
		
		$this->_columns = $columns;

		return $this;
	}
	
	/**
	 * Sets values on an insert
	 *
	 * @param  array $values 
	 * @return $this
	 */
	public function values(array $values)
	{
		// Get all of the passed values
		$values = func_get_args();

		$this->_values = array_merge($this->_values, $values);

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
			$field = Jelly::meta($parent)->fields($path);

			if (!($field instanceof Jelly_Field_Behavior_Joinable))
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
			$meta = Jelly::meta($model);
			
			// Select all of the model's fields
			foreach ($meta->fields() as $alias => $select)
			{
				if ($select->in_db)
				{
					// We have to manually alias, since the path does not necessarily correspond to the path
					$this->select(array($model.'.'.$alias, $chain.':'.$alias));
				}
			}
			
			// Let the field finish the rest
			$field->with($this);
			
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
		if ($meta = Jelly::meta($model))
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
		if ($this->_meta && ($model === NULL || $model == $this->_meta->table()))
		{
			$model = $this->_meta->model();
		}
		
		$table = $model;
		$column = $field;
		
		// See if the model is register
		if ($meta = Jelly::meta($model))
		{
			$table = $meta->table();
			
			// Find the field
			if ($field = $meta->fields($field))
			{
				$column = $field->column;
			}
		}
		
		// Only join when we actually have a table
		if ($join && $table)
		{
			return $table.'.'.$column;
		}
		else
		{
			return $column;
		}
	}
	
	/**
	 * Builders the instance into a usable Database_Query_Builder_* instance.
	 *
	 * @return void
	 */
	protected function _build($type = NULL)
	{
		if ($type === NULL)
		{
			$type = $this->_type;
		}
		
		switch($type)
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
		if ($this->_columns && $this->_values && $type === Database::INSERT)
		{
			$query->columns($this->_columns);
			
			// Have to do a call_user_func_array to support multiple sets
			call_user_func_array(array($query, 'values'), $this->_values);
		}
		
		if ($this->_set && $type === Database::UPDATE)
		{
			$query->set($this->_set);
		}
		
		return $query;
	}
}