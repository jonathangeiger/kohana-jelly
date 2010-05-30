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
 * @package  Jelly
 */
abstract class Jelly_Builder_Core extends Kohana_Database_Query_Builder_Select
{
	/**
	 * @var  string  The inital model used to construct the builder
	 */
	protected $_model = NULL;

	/**
	 * @var  Jelly_Meta  The meta object (if found) that is attached to this builder
	 */
	protected $_meta = NULL;

	/**
	 * @var  array  Data to be UPDATEd
	 */
	protected $_set = array();

	/**
	 * @var  array  Columns to be INSERTed
	 */
	protected $_columns = array();

	/**
	 * @var  array  Values to be INSERTed
	 */
	protected $_values = array();
	
	/**
	 * @var  boolean  The result, if the query has been executed
	 */
	protected $_result = NULL;
	
	/**
	 * @var  boolean  The type of the query, if provided
	 */
	protected $_type = NULL;

	/**
	 * Constructs a new Jelly_Builder instance.
	 *
	 * $model is not actually allowed to be NULL. It has
	 * a default because PHP throws strict errors otherwise.
	 *
	 * @param string $model
	 */
	public function __construct($model = NULL, $key = NULL)
	{
		parent::__construct();

		if ( ! $model)
		{
			throw new Kohana_Exception(get_class($this) . ' requires $model to be set in the constructor');
		}

		// Set the model and the initial from()
		$this->_model = Jelly::model_name($model);
		$this->_meta  = Jelly::meta($this->_model);
		$this->_initialize();
		
		// Default to using our key
		if ($key)
		{
			$this->where($this->unique_key($key), '=', $key)->limit(1);
		}
		
		// Default to loading as arrays
		$this->as_object(FALSE);
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
		if ($this->_meta)
		{
			return $this->_meta->behaviors()->call('builder_'.$method, $this, $args);
		}
		
		throw new Kohana_Exception('Invalid method :method called on class :class',
			array(':method' => $method, ':class' => get_class($this)));
	}

	/**
	 * Executes the query as a SELECT statement
	 *
	 * @param   string  $db 
	 * @return  Jelly_Collection | Jelly_Model
	 */
	public function select($db = NULL)
	{
		$db = $this->_db($db);
		
		// Select all of the columns for the model if we haven't already
		$this->_meta AND empty($this->_select) AND $this->select_column('*');
		
		// Trigger before_select callback
		$this->_meta AND $this->_meta->behaviors()->before_builder_select($this);
		
		// Ready to leave the builder
		$this->_result = $this->_build(Database::SELECT)->execute($db);
		
		// Figure out our result type
		$model = ($this->_meta) ? $this->_meta->model() : NULL;
		$this->_result = new Jelly_Collection($model, $this->_result);
		
		// Trigger after_query callbacks
		$this->_meta AND $this->_meta->behaviors()->after_builder_select($this, $this->_result);

		// If the record was limited to 1, we only return that model
		// Otherwise we return the whole result set.
		if ($this->_limit === 1)
		{
			$this->_result = $this->_result->current();
		}
		
		return $this->_result;
	}
	
	/**
	 * Executes the query as an INSERT statement
	 *
	 * @param   string  $db 
	 * @return  array
	 */
	public function insert($db = NULL)
	{
		$db = $this->_db($db);
		
		// Trigger callbacks
		$this->_meta AND $this->_meta->behaviors()->before_builder_insert($this);
		
		// Ready to leave the builder
		$result = $this->_build(Database::INSERT)->execute($db);
		
		// Trigger after_query callbacks
		$this->_meta AND $this->_meta->behaviors()->after_builder_insert($this, $result);
		
		return $result;
	}
	
	/**
	 * Executes the query as an UPDATE statement
	 *
	 * @param   string  $db 
	 * @return  int
	 */
	public function update($db = NULL)
	{
		$db = $this->_db($db);
		
		// Trigger callbacks
		$this->_meta AND $this->_meta->behaviors()->before_builder_update($this);
		
		// Ready to leave the builder
		$result = $this->_build(Database::UPDATE)->execute($db);
		
		// Trigger after_query callbacks
		$this->_meta AND $this->_meta->behaviors()->after_builder_update($this, $result);
		
		return $result;
	}
	
	/**
	 * Executes the query as a DELETE statement
	 *
	 * @param   string  $db 
	 * @return  int
	 */
	public function delete($db = NULL)
	{
		$db = $this->_db($db);
		$result = NULL;
		
		// Trigger callbacks
		if ($this->_meta)
		{
			// Listen for a result to see if we need to actually delete the record
			$result = $this->_meta->behaviors()->before_builder_delete($this);
		}
		
		if ($result === NULL)
		{
			$result = $this->_build(Database::DELETE)->execute($db);
		}
		
		// Trigger after_query callbacks
		$this->_meta AND $this->_meta->behaviors()->after_builder_delete($this, $result);
		
		return $result;
	}
	
	/**
	 * Counts the current query builder
	 *
	 * @param   string  $db 
	 * @return  int
	 */
	public function count($db = NULL)
	{
		$db = $this->_db($db);
		
		// Trigger callbacks
		$this->_meta AND $this->_meta->behaviors()->before_builder_select($this);
		
		// Start with a basic SELECT
		$query = $this->_build(Database::SELECT);
		
		// Dump a few unecessary bits that cause problems
		$query->_select = $query->_order_by = array();

		// Find the count
		$result = (int) $query
		               ->select(array('COUNT("*")', 'total'))
		               ->execute($db)
		               ->get('total');
		
		// Trigger after_query callbacks
		$this->_meta AND $this->_meta->behaviors()->after_builder_select($this, $result);
		
		return $result;
	}
	
	/**
	 * Loads a single record
	 *
	 * @param   int  $type 
	 * @return  mixed
	 */
	public function load($key = NULL)
	{
		if ($key)
		{
			$this->where($this->unique_key($key), '=', $key);
		}
		
		return $this->limit(1)->select();
	}

	/**
	 * Builds the builder into a native query
	 *
	 * @param   string  $type
	 * @return  void
	 */
	public function execute($db = NULL, $type = NULL)
	{
		$type === NULL AND $type = $this->_type;
		
		switch ($type)
		{
			case Database::SELECT:
				return $this->select($db);
			case Database::INSERT:
				return $this->insert($db);
			case Database::UPDATE:
				return $this->update($db);
			case Database::DELETE:
				return $this->delete($db);
		}
	}

	/**
	 * Compiles the builder into a usable expression
	 *
	 * @param   Database $db
	 * @return  Database_Query
	 */
	public function compile(Database $db, $type = NULL)
	{
		$type === NULL AND $type = $this->_type;
		
		// Select all of the columns for the model if we haven't already
		$this->_meta AND empty($this->_select) AND $this->select_column('*');
		
		return $this->_build($type)->compile($db);
	}
	
	/**
	 * Allows setting the type for dynamic execution
	 *
	 * @param   int  $type 
	 * @return  mixed
	 */
	public function type($type = NULL)
	{
		if ($type !== NULL)
		{
			$this->_type = $type;
			return $this;
		}
		
		return $this->_type;
	}
	
	/**
	 * Returns the unique key for a specific value. This method is expected
	 * to be overloaded in builders if the table has other unique columns.
	 *
	 * @param  mixed  $value
	 * @return string
	 */
	public function unique_key($value)
	{
		return $this->_meta->primary_key();
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
		return parent::and_where($this->_column($column, TRUE, $value), $op, $value);
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
		return parent::or_where($this->_column($column, TRUE, $value), $op, $value);
	}

	/**
	 * Choose the columns to select from.
	 *
	 * @param   mixed  column name or array($column, $alias) or object
	 * @param   ...
	 * @return  $this
	 */
	public function select_column($column)
	{
		return $this->select_columns(array($column));
	}

	/**
	 * Choose the columns to select from, using an array.
	 *
	 * @param   array  list of column names or aliases
	 * @return  $this
	 */
	public function select_columns(array $columns)
	{
		foreach ($columns as $i => $column)
		{
			if (is_array($column))
			{
				$columns[$i][0] = $this->_column($column[0], TRUE);
			}
			else
			{
				// Check for * and model.*
				if (FALSE !== strpos($column, '*'))
				{
					$meta = $this->_meta;

					if ($column != '*')
					{
						$meta = explode('.', $column);
						$meta = Jelly::meta($meta[0]);
					}

					// Can we continue? Only if there's a valid meta object
					if ($meta)
					{
						$add_columns = array();

						foreach ($meta->fields() as $field)
						{
							if ($field->in_db)
							{
								$add_columns[] = array($meta->table().'.'.$field->column, $field->name);
							}
						}

						// Add these columns before we continue
						parent::select_array($add_columns);

						// Remove the item we just added. It's no longer valid
						unset($columns[$i]);
						continue;
					}
				}

				$columns[$i] = $this->_column($column, TRUE);
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
			if ( ! $this->_meta)
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
	 * @param   mixed  column name or array($column, $alias) or object
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
				$columns[$i][0] = $this->_column($column[0]);
			}
			else
			{
				$columns[$i] = $this->_column($column);
			}
		}

		// Bypass parent since there is no reliable way to call parent method with arguments as an array
		$this->_group_by = array_merge($this->_group_by, $columns);

		return $this;
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
		return parent::and_having($this->_column($column, TRUE, $value), $op, $value);
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
		return parent::or_having($this->_column($column, TRUE, $value), $op, $value);
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
		return parent::order_by($this->_column($column, TRUE), $direction);
	}

	/**
	 * Set the values to update with an associative array.
	 *
	 * @param   array  associative (column => value) list
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
			$column = $this->_column($column, NULL, $value);
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
				$columns[$i] = $this->_column($column, FALSE);
			}
		}

		$this->_columns = $columns;

		return $this;
	}

	/**
	 * Sets values on an insert
	 *
	 * @param   array  $values
	 * @return  $this
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
		$parent = $this->_meta->model();
		$chain = '';

		foreach ($paths as $iteration => $path)
		{
			$field = Jelly::meta($parent)->fields($path);

			if ( ! ($field instanceof Jelly_Field_Behavior_Joinable))
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
					// We select from the field alias rather than the model to allow multiple joins to same model
					$this->select_column(array($field->name.'.'.$alias, $chain.':'.$alias));
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
		parent::reset();

		$this->_set     =
		$this->_columns =
		$this->_values  = array();
		$this->_result = NULL;
		
		// Re-register the model
		$this->_initialize();

		return $this;
	}
	
	/**
	 * Callback that can be overridden in the Builder.
	 *
	 * @see     Jelly_Behavior::before_select
	 * @return  void
	 */
	public function before_select() { }
	
	/**
	 * Callback that can be overridden in the Builder.
	 *
	 * @see     Jelly_Behavior::before_select
	 * @param   Jelly_Collection|Jelly_Model  $result
	 * @return  void
	 */
	public function after_select($result) { }

	/**
	 * This method aliases models to tables.
	 *
	 * @param   string  $table
	 * @return  string
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
	 * $value is passed so the :unique_key meta alias can be used.
	 *
	 * @param   string   $field
	 * @param   boolean  $join
	 * @param   mixed    $value
	 * @return  string
	 */
	protected function _column($field, $join = TRUE, $value = NULL)
	{
		$model = NULL;

		// Check for functions
		if (strpos($field, '"') !== FALSE)
		{
			// Quote the column in FUNC("ident") identifiers
			return preg_replace('/"(.+?)"/e', '"\\"".$this->_column("$1")."\\""', $field);
		}

		// Test for Database Expressions
		if ($field instanceof Database_Expression)
		{
			return $field;
		}

		// Set if we find this is a reference to a joined field
		$join_table_alias = FALSE;

		// Field has no model
		if (strpos($field, '.') === FALSE)
		{
			// If we have a meta alias with no model use this model to resolve it
			// or if we have a valid field for this model assume that's what we mean
			if (strpos($field, ':') !== FALSE OR ($this->_meta AND $this->_meta->fields($field)))
			{
				$field = $this->_model.'.'.$field;
			}
			else
			{
				// This is not a model field or meta alias, so don't bother trying to alias it and
				// return it as it is
				return $field;
			}
		}
		else
		{
			list($model, $field) = explode('.', $field, 2);

			// Check to see if the 'model' passed is actually a relationship alias
			if ($field_object = $this->_meta->fields($model) AND $field_object instanceof Jelly_Field_Behavior_Joinable)
			{
				// The model specified looks like a relationship alias in this context
				// that means we alias the field name to a column but use the join alias for the table
				$join_table_alias = Jelly::join_alias($field_object);

				// Change the field to use the appropriate model so it can be properly aliased
				$field = $field_object->foreign['model'].'.'.$field;
			}
			else
			{
				// Put field back together
				$field = $model.'.'.$field;
			}
		}

		$alias = Jelly::alias($field, $value);

		if ($join_table_alias)
		{
			// Replace the actual table with the join alias
			$alias['table'] = $join_table_alias;
		}

		if ($join)
		{
			return implode('.', $alias);
		}
		else
		{
			return $alias['column'];
		}
	}

	/**
	 * Builders the instance into a usable
	 * Database_Query_Builder_* instance.
	 *
	 * @return  Database_Query_Builder
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

				if ($this->_meta AND ! count($this->_order_by))
				{
					// Don't add default sorting if order_by() has been set manually
					foreach ($this->_meta->sorting() as $column => $direction)
					{
						$this->order_by($column, $direction);
					}
				}

				$query = DB::select();
				$query->_from       = $this->_from;
				$query->_select     = $this->_select;
				$query->_distinct   = $this->_distinct;
				$query->_offset     = $this->_offset;
				$query->_join       = $this->_join;
				$query->_group_by   = $this->_group_by;
				$query->_having     = $this->_having;
				$query->_order_by   = $this->_order_by;
				$query->_as_object  = $this->_as_object;
				$query->_lifetime   = $this->_lifetime;
				$query->_limit      = $this->_limit;
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
				throw new Kohana_Exception("Jelly_Builder compiled without a query type specified");
				break;
		}

		// Copy over the common conditions to a new statement
		$query->_where = $this->_where;

		// Convert sets
		if ($this->_columns AND $this->_values AND $type === Database::INSERT)
		{
			$query->columns($this->_columns);

			// Have to do a call_user_func_array to support multiple sets
			call_user_func_array(array($query, 'values'), $this->_values);
		}

		if ($this->_set AND $type === Database::UPDATE)
		{
			$query->set($this->_set);
		}

		return $query;
	}
	
	/**
	 * Initializes the builder by setting a default 
	 * table and adding the load_with joins.
	 *
	 * @return void
	 */
	protected function _initialize()
	{
		// Can we set the default from?
		if ($this->_meta)
		{
			$this->from($this->_meta->table());

			// Load with automatically here.
			foreach ($this->_meta->load_with() as $relationship)
			{
				$this->with($relationship);
			}
		}
		else
		{
			$this->from($this->_model);
		}
	}
	
	/**
	 * Returns a proper db group.
	 *
	 * @param   mixed  $db 
	 * @return  void
	 */
	protected function _db($db)
	{
		// Nothing provided, give 'em something gooood
		if ($db === NULL)
		{
			return $this->_meta ? $this->_meta->db() : 'default';
		}
		
		return $db;
	}
}
