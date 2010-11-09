<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Builder is an class used to override certain aspects of
 * Kohana's query builder. In particular, it uses Jelly's meta system
 * to support aliasing fields to columns and models to tables.
 * 
 * This class is designed to be extended by custom classes that need query
 * building functionality, such as Jelly's own Jelly_Manager.
 *
 * @package  Jelly
 */
class Jelly_Core_Query extends Kohana_Database_Query_Builder_Where
{
	/**
	 * @var  string  The initial model used to construct the builder
	 */
	protected $_model = NULL;
	
	/**
	 * @var  array  DB Group to use
	 */
	protected $_db = NULL;
	
	/**
	 * @var  array  Tables to act upon
	 */
	protected $_from = array();
	
	/**
	 * @var  array  WHERE
	 */
	protected $_where = array();

	/**
	 * @var  array  ORDER BY
	 */
	protected $_order_by = array();

	/**
	 * @var  int  LIMIT
	 */
	protected $_limit = NULL;

	/**
	 * @var  array  Columns to select from
	 */
	protected $_columns = array();

	/**
	 * @var  array  JOIN
	 */
	protected $_join = array();
	
	/**
	 * @var  Database_Query_Builder_Join  The last JOIN added
	 */
	protected $_last_join;

	/**
	 * @var  array  GROUP BY
	 */
	protected $_group_by = array();

	/**
	 * @var  array  HAVING
	 */
	protected $_having = array();

	/**
	 * @var  int  The SELECT offset
	 */
	protected $_offset = NULL;

	/**
	 * @var  bool  Whether or not the SELECT is a distinct one
	 */
	protected $_distinct = FALSE;

	/**
	 * @var  mixed  Return results as associative arrays or objects
	 */
	protected $_as_object = NULL;
	
	/**
	 * @var  array  Model <> table cache
	 */
	protected $_model_cache = array();
	
	/**
	 * @var  Jelly_Meta  Current meta object
	 */
	protected $_meta = NULL;
	
	/**
	 * Constructs a new Jelly_Builder instance.
	 *
	 * @param  string  The model or table to act upon
	 * @param  array   Initial data for the object
	 */
	public function __construct($model, $key = NULL)
	{
		parent::__construct(Database::SELECT, '');
		
		// Ensure the meta object is registered before we do anything
		Jelly::meta($model);
		
		// This is immutable and will never change
		$this->_model = $model;
		
		// reset() will automatically set a table()
		// and the default object type for us
		$this->reset();
	
		// key() will take care of an initial where clause
		if ($key !== NULL)
		{
			$this->_key($key);
		}
	}
	
	/**
	 * Executes the query as a SELECT statement.
	 *
	 * @return  $this
	 */
	public function select()
	{
		$as_object = $this->_as_object;
		
		if ($as_object === NULL)
		{
			$this->_as_object = FALSE;
		}
		
		$result = $this->execute($this->_db);
		
		if ($as_object === NULL)
		{
			$this->_as_object = NULL;
		}
		
		return $result;
	}
	
	/**
	 * Executes the query as an INSERT statement.
	 * 
	 * Multiple inserts are not supported.
	 *
	 * @param   array   An optional set of data to insert
	 * @return  array   array($insert_id, $rows_affected)
	 */
	public function insert(array $data = array())
	{
		if ($data)
		{
			$this->set($data);
		}
		
		return $this->_build(Database::INSERT)->execute($this->_db);
	}
	
	/**
	 * Executes the query as an UPDATE statement
	 *
	 * @param   array   An optional set of data to use for updating
	 * @return  int     The number of rows affected
	 */
	public function update(array $data = array())
	{
		if ($data)
		{
			$this->set($data);
		}
		
		return $this->_build(Database::UPDATE)->execute($this->_db);
	}
	
	/**
	 * Executes the query as a DELETE statement
	 *
	 * @return  int   The number of rows affected
	 */
	public function delete()
	{
		return $this->_build(Database::DELETE)->execute($this->_db);
	}
	
	/**
	 * Counts the current query builder.
	 * 
	 * If the query has already been selected this will 
	 * return the count of the result set.
	 *
	 * @return  int  The number of rows that match the query
	 */
	public function count()
	{
		// Clone this so we aren't modifying the current query
		$query = clone $this;
		
		// Dump a few unnecessary bits that cause problems
		$query->_select = 
		$query->_order_by = array();

		// Find the count
	 	return (int) $query
		               ->column(array('COUNT("*")', 'total'))
		               ->as_object(FALSE)
		               ->execute($this->_db)
		               ->get('total');
	}

	/**
	 * Compiles the builder into a usable expression.
	 *
	 * The two params in this method signature do not apply.
	 * Instead, you should use the db() and type() methods.
	 * 
	 * @return  Database_Query
	 */
	public function compile(Database $db = NULL, $type = NULL)
	{
		$db = Database::instance($this->_db);
		
		// If we're not doing a SELECT, we can pass it off to the Database lib
		if ($this->type() !== Database::SELECT)
		{
			return $this->_build($this->type())->compile($db);
		}
		
		// Callback to quote identifiers
		$quote_ident = array($db, 'quote_identifier');

		// Callback to quote tables
		$quote_table = array($db, 'quote_table');

		// Start a selection query
		$query = 'SELECT ';

		if ($this->_distinct === TRUE)
		{
			// Select only unique results
			$query .= 'DISTINCT ';
		}

		if (empty($this->_columns))
		{
			$this->column('*');
		}
		
		// Select all columns
		$query .= implode(', ', array_unique(array_map($quote_ident, $this->_columns)));

		if ( ! empty($this->_from))
		{
			// Set tables to select from
			$query .= ' FROM '.implode(', ', array_unique(array_map($quote_table, $this->_from)));
		}

		if ( ! empty($this->_join))
		{
			// Add tables to join
			$query .= ' '.$this->_compile_join($db, $this->_join);
		}

		if ( ! empty($this->_where))
		{
			// Add selection conditions
			$query .= ' WHERE '.$this->_compile_conditions($db, $this->_where);
		}

		if ( ! empty($this->_group_by))
		{
			// Add sorting
			$query .= ' GROUP BY '.implode(', ', array_map($quote_ident, $this->_group_by));
		}

		if ( ! empty($this->_having))
		{
			// Add filtering conditions
			$query .= ' HAVING '.$this->_compile_conditions($db, $this->_having);
		}

		if ( ! empty($this->_order_by))
		{
			// Add sorting
			$query .= ' '.$this->_compile_order_by($db, $this->_order_by);
		}

		if ($this->_limit !== NULL)
		{
			// Add limiting
			$query .= ' LIMIT '.$this->_limit;
		}

		if ($this->_offset !== NULL)
		{
			// Add offsets
			$query .= ' OFFSET '.$this->_offset;
		}

		return $query;
	}
	
	/**
	 * Change the DB group to use.
	 * 
	 * @param   string  The DB group to set
	 * @return  string  The db group in use
	 */
	public function db($db = NULL)
	{
		if (func_num_args() === 0)
		{
			return $this->_db;
		}
		
		$this->_db = $db;
		return $this;
	}
	
	/**
	 * Change the Query type.
	 * 
	 * @param   string  The type to set
	 * @return  string  The type in use
	 */
	public function type($type = NULL)
	{
		if (func_num_args() === 0)
		{
			return $this->_type;
		}
		
		$this->_type = $type;
		return $this;
	}
	
	/**
	 * Set the values to insert or update with an associative array.
	 *
	 * @param   array  associative (field => value) list
	 * @return  $this
	 */
	public function set(array $data)
	{
		$set = array();
		
		foreach ($data as $field => $value)
		{
			$set[$this->_column($field, FALSE)] = $value;
		}
		
		$this->_set = array_merge($this->_set, $set);

		return $this;
	}
	
	/**
	 * Choose the table(s) to SELECT from.
	 * 
	 * By default, the model's tab is selected.
	 *
	 * @param   mixed  model name or array($model, $alias) or object
	 * @param   ...
	 * @return  $this
	 */
	public function from($tables)
	{
		$from = array();
		
		foreach (func_get_args() as $table)
		{
			$from[] = $this->_table($table);
		}
		
		$this->_from = array_merge($this->_from, $from);

		return $this;
	}
	
	/**
	 * Enables or disables selecting only unique columns using "SELECT DISTINCT"
	 *
	 * @param   boolean  enable or disable distinct columns
	 * @return  $this
	 */
	public function distinct($value)
	{
		$this->_distinct = (bool) $value;

		return $this;
	}
	
	/**
	 * Adds addition tables to "JOIN ...".
	 *
	 * @param   mixed   model name or array($model, $alias) or object
	 * @param   string  join type (LEFT, RIGHT, INNER, etc)
	 * @return  $this
	 */
	public function join($table, $type = NULL)
	{
		$this->_join[] = $this->_last_join = new Database_Query_Builder_Join($this->_table($table), $type);

		return $this;
	}

	/**
	 * Adds "ON ..." conditions for the last created JOIN statement.
	 *
	 * @param   mixed   field name or array($field, $alias) or object
	 * @param   string  logic operator
	 * @param   mixed   field name or array($field, $alias) or object
	 * @return  $this
	 */
	public function on($c1, $op, $c2)
	{
		$this->_last_join->on($this->_column($c1), $op, $this->_column($c2));

		return $this;
	}

	/**
	 * Creates a "GROUP BY ..." filter.
	 *
	 * @param   mixed   field name or array($field, $alias) or object
	 * @param   ...
	 * @return  $this
	 */
	public function group_by($fields)
	{
		$fields = array();

		foreach (func_get_args() as $field)
		{
			if (is_array($field))
			{
				$fields[] = array($this->_column($field[0]), $field[1]);
			}
			else
			{
				$fields[] = $this->_column($field);
			}
		}

		$this->_group_by = array_merge($this->_group_by, $fields);

		return $this;
	}

	/**
	 * Alias of and_having()
	 *
	 * @param   mixed   field name or array($field, $alias) or object
	 * @param   string  logic operator
	 * @param   mixed   column value
	 * @return  $this
	 */
	public function having($field, $op, $value = NULL)
	{
		return $this->and_having($field, $op, $value);
	}

	/**
	 * Creates a new "AND HAVING" condition for the query.
	 *
	 * @param   mixed   field name or array($field, $alias) or object
	 * @param   string  logic operator
	 * @param   mixed   column value
	 * @return  $this
	 */
	public function and_having($field, $op, $value = NULL)
	{
		$this->_having[] = array('AND' => array($this->_column($field), $op, $value));

		return $this;
	}

	/**
	 * Creates a new "OR HAVING" condition for the query.
	 *
	 * @param   mixed   field name or array($field, $alias) or object
	 * @param   string  logic operator
	 * @param   mixed   column value
	 * @return  $this
	 */
	public function or_having($column, $op, $value = NULL)
	{
		$this->_having[] = array('OR' => array($this->_column($field), $op, $value));

		return $this;
	}

	/**
	 * Alias of and_having_open()
	 *
	 * @return  $this
	 */
	public function having_open()
	{
		return $this->and_having_open();
	}

	/**
	 * Opens a new "AND HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function and_having_open()
	{
		$this->_having[] = array('AND' => '(');

		return $this;
	}

	/**
	 * Opens a new "OR HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function or_having_open()
	{
		$this->_having[] = array('OR' => '(');

		return $this;
	}

	/**
	 * Closes an open "AND HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function having_close()
	{
		return $this->and_having_close();
	}

	/**
	 * Closes an open "AND HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function and_having_close()
	{
		$this->_having[] = array('AND' => ')');

		return $this;
	}

	/**
	 * Closes an open "OR HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function or_having_close()
	{
		$this->_having[] = array('OR' => ')');

		return $this;
	}

	/**
	 * Start returning results after "OFFSET ..."
	 *
	 * @param   integer   starting result number
	 * @return  $this
	 */
	public function offset($number)
	{
		$this->_offset = (int) $number;

		return $this;
	}

	/**
	 * Alias of and_where()
	 *
	 * @param   mixed   field name or array($field, $alias) or object
	 * @param   string  logic operator
	 * @param   mixed   column value
	 * @return  $this
	 */
	public function where($field, $op, $value)
	{
		return $this->and_where($field, $op, $value);
	}

	/**
	 * Creates a new "AND WHERE" condition for the query.
	 *
	 * @param   mixed   field name or array($field, $alias) or object
	 * @param   string  logic operator
	 * @param   mixed   column value
	 * @return  $this
	 */
	public function and_where($field, $op, $value)
	{
		$this->_where[] = array('AND' => array($this->_column($field), $op, $value));

		return $this;
	}

	/**
	 * Creates a new "OR WHERE" condition for the query.
	 *
	 * @param   mixed   field name or array($field, $alias) or object
	 * @param   string  logic operator
	 * @param   mixed   column value
	 * @return  $this
	 */
	public function or_where($field, $op, $value)
	{
		$this->_where[] = array('OR' => array($this->_column($field), $op, $value));

		return $this;
	}

	/**
	 * Applies sorting with "ORDER BY ..."
	 *
	 * @param   mixed   field name or object
	 * @param   string  direction of sorting
	 * @return  $this
	 */
	public function order_by($column, $direction = NULL)
	{
		$this->_order_by[] = array($this->_column($column), $direction);

		return $this;
	}

	/**
	 * Return up to "LIMIT ..." results
	 *
	 * @param   integer  maximum results to return
	 * @return  $this
	 */
	public function limit($number)
	{
		$this->_limit = (int) $number;

		return $this;
	}
	
	/**
	 * Choose the fields(s) to select from. 
	 *
	 * @param   mixed   field name or array($field, $alias) or object
	 * @return  $this
	 */
	public function column($fields)
	{
		$columns = array();
		
		foreach (func_get_args() as $i => $field)
		{
			if (is_array($field))
			{
				$columns[] = array($this->_column($field[0]), $field[1]);
			}
			else
			{
				if (FALSE !== ($pos = strpos($field, '*')))
				{
					// See if we can locate a meta object from the model
					$model = $pos ? substr($field, 0, $pos - 1) : $this->_model;
					list(,,$actual_model) = $this->_table($model);
					$meta  = Jelly::meta($actual_model);
					
					if ($meta->model)
					{
						foreach ($meta->fields as $field)
						{
							$alias = $this->_model !== $model ? $model.':'.$field->name : $field->name;
								
							if ($field->in_db)
							{
								$columns[] = array($this->_column($model.'.'.$field->name), $alias);
							}
							else if ($field->column instanceof Database_Expression)
							{
							    $columns[] = array($field->column, $alias);
							}
						}
						
						continue;
					}
				}

				$columns[] = $this->_column($field);
			}
		}
		
		$this->_columns = array_merge($this->_columns, $columns);

		return $this;
	}


	/**
	 * Allows joining 1:1 relationships in a single query.
	 *
	 * It is possible to join a relationship to a join using
	 * the following syntax:
	 *
	 * $post->join("author:role");
	 *
	 * Assuming a post belongs to an author and an author has one role.
	 *
	 * Currently, no checks are made to see if a join has already
	 * been made, so joining a model twice will result in
	 * a failed query.
	 *
	 * @param   string  $alias
	 * @return  $this
	 */
	public function with($relationship)
	{
		// Ensure the main model is selected
		$this->select_column($this->_model.'.*');

		// We'll start with the first one and work our way down
		$paths = explode(":", $relationship);
		$parent = $this->_meta->model;
		$chain = '';

		foreach ($paths as $iteration => $path)
		{
			$field = Jelly::meta($parent)->field($path);

			if ( ! $field->supports(Jelly_Field::WITH))
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
			foreach ($meta->fields as $alias => $select)
			{
				if ($select->in_db)
				{
					// We select from the field alias rather than the model to allow multiple joins to same model
					$this->select_column($parent.':'.$field->name.'.'.$alias, $chain.':'.$alias);
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
	 * Resets the query builder to an empty state.
	 *
	 * The query type and model is not reset.
	 *
	 * @return  $this
	 */
	public function reset()
	{
		$this->_tables =
		$this->_where  =
		$this->_order_by =
		$this->_columns =
		$this->_set =
		$this->_join =
		$this->_group_by =
		$this->_having =
		$this->_model_cache =
		$this->_field_cache =
		$this->_parameters = array();
		
		$this->_offset =
		$this->_limit = NULL;
		
		$this->_distinct = FALSE;
		
		// Set meta
		$this->_meta = Jelly::meta($this->_model);
		
		// Re-register the model
		$this->from($this->_model);

		// Default to loading the current model
		$this->as_object(NULL);
		
		// Properly set the DB
		$this->db($this->meta()->db);

		return $this;
	}
	
	/**
	 * Returns the model this query is based upon.
	 *
	 * @return  string
	 **/
	public function meta()
	{
		return $this->_meta;
	}
	
	/**
	 * This is an easy to override method that is called
	 * if a key is passed to the builder.
	 * 
	 * The query is automatically limited to 1 if called.
	 * 
	 * @param   mixed  $key
	 * @return  void
	 */
	protected function _key($key)
	{
		$this->limit(1);
		
		if (is_array($key))
		{
			foreach ($key as $field => $value)
			{
				$this->where($field, '=', $value);
			}
		}
		else if (is_numeric($key))
		{
			$this->where($this->_model.'.:primary_key', '=', $key);
		}
		else
		{
			$this->where($this->_model.'.:name_key', '=', $key);
		}
	}
	
	/**
	 * Aliases a model to its actual table name. Returns an alias
	 * suitable to pass to table() or join().
	 *
	 * @param  string  $model 
	 * @return array
	 */
	protected function _table($model)
	{
		$original = 
		$table    = $model;
		$alias    = NULL;
		$found    = NULL;
		
		// Split apart array(table, alias)
		if (is_array($model))
		{
			list($model, $alias) = $model;
			$original = "$model.$alias";
		}
		
		// Allow passing an alias to get at the model
		if (isset($this->_model_cache[$model]))
		{
			return $this->_model_cache[$model];
		}
	
		// We're caching results to improve speed
		if ( ! isset($this->_model_cache[$original]))
		{	
			$meta = Jelly::meta($model);
			
			// Standard model
			if ($meta->model)
			{
				$table = $meta->table;
				$alias = $alias ? $alias : $table;
			}
			// Joinable field was passed, use its model
			else if (($pos = strpos($model, ':')) !== FALSE) 
			{
				$parent = $pos ? substr($model, 0, $pos)  : $this->_model;
				$field  = $pos ? substr($model, $pos + 1) : substr($model, 1);
				$alias  = $alias ? $alias : $parent.':'.$field;
				
				$model = Jelly::meta($parent)->field($field)->foreign['model'];
				$table = Jelly::meta($model)->table;
			}
			// Unknown Table
			else
			{	
				$table = $model;
				$model = NULL;
				$alias = $alias ? $alias : $table;
			}
			
			// Cache what we've found
			$this->_model_cache[$alias] = 
			$this->_model_cache[$original] = array($table, $alias, $model);
		}
		
		return $this->_model_cache[$original];
	}
	
	/**
	 * Aliases a field to its actual representation in the database. Meta-aliases
	 * are resolved and table-aliases are taken into account.
	 * 
	 * Note that the aliased field will be returned in the format you pass it in:
	 * 
	 *    model.field => table.column
	 *    field => column
	 *
	 * @param   mixed   The field to alias, in field or model.field format
	 * @return  string
	 */
	protected function _column($field, $join_if_sure = TRUE)
	{
		if ($field instanceof Database_Expression OR $field instanceof Database_Query)
		{
			return $field;
		}
		
		if (strpos($field, '"') !== FALSE)
		{	
			// Alias the field(s) in FUNC("field")
			return preg_replace('/"(.+?)"/e', '"\\"".$this->_column("$1")."\\""', $field);
		}
		
		$join = (bool) strpos($field, '.');
		
		if ( ! $join)
		{
			$model = $this->_model;
		}
		else
		{
			list($model, $field) = explode('.', $field, 2);	
		}
		
		$column = $field;
		
 		list(, $alias, $model) = $this->_table($model);
		
		// Expand meta-aliases
		if (FALSE !== ($pos = strpos($field, ':')))
		{
			$meta  = Jelly::meta($model);
			
			// Check for a model operator
			if ($pos !== 0)
			{
				$meta  = Jelly::meta(substr($field, 0, $pos));
				$field = substr($field, $pos);
			}
			
			$column = $field = $this->_meta_alias($meta, $field);
		}
		
		// Alias to the column
		if ($field = Jelly::meta($model)->field($field) AND $field->in_db)
		{
			$column = $field->column; 
			$join   = $join_if_sure ? TRUE : $join;
		}
		
		return $join ? $alias.'.'.$column : $column;
	}
	
	/**
	 * Easy-to-override method that expands aliases.
	 *
	 * @param   string $model 
	 * @param   string $alias
	 * @param   array $state 
	 * @return  array
	 */
	protected function _meta_alias($meta, $alias)
	{
		return $meta->{substr($alias, 1)};
	}

	/**
	 * Builds the instance into a usable Database_Query_Builder_*.
	 *
	 * @return  mixed
	 */
	protected function _build($type)
	{
		switch($type)
		{
			case Database::UPDATE:
				$query = DB::update(Jelly::meta($this->_model)->table);
				break;

			case Database::INSERT:
				$query = DB::insert(Jelly::meta($this->_model)->table);
				break;

			case Database::DELETE:
				$query = DB::delete(Jelly::meta($this->_model)->table);
				break;

			default:
				throw new Kohana_Exception('Invalid query type');
				break;
		}

		$query->_where = $this->_where;
		$query->_limit = $this->_limit;

		if ($this->_set AND $type === Database::INSERT)
		{
			$query->columns(array_keys($this->_set));
			$query->values(array_values($this->_set));
		}

		if ($this->_set AND $type === Database::UPDATE)
		{
			$query->set($this->_set);
		}

		return $query;
	}
}
