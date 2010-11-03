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
abstract class Jelly_Core_Builder extends Kohana_Database_Query_Builder_Select
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
	 * @var  array  Alias cache
	 */
	protected $_model_cache = array();
	
	/**
	 * @var  array  Alias cache
	 */
	protected $_alias_cache = array();

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
		if ($key !== NULL)
		{
			$this->key($key);
		}
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
			return $this->_meta->events()->trigger('builder.call_'.$method, $this, $args);
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
		$db   = $this->_db($db);
		$meta = $this->_meta;
		
		if ($meta)
		{
			// Select all of the columns for the model if we haven't already
			empty($this->_select) AND $this->select_column('*');
			
			// Trigger before_select callback
			$meta->events()->trigger('builder.before_select', $this);
		}
		
		// Ready to leave the builder, we need to figure out what type to return
		$this->_result = $this->_build(Database::SELECT);
		
		// Return an actual array
		if ($this->_as_object === FALSE OR Jelly::meta($this->_as_object))
		{
			$this->_result->as_assoc();
		}
		else
		{
			$this->_result->as_object($this->_as_object);
		}
		
		// Pass off to Jelly_Collection, which manages the result
		$this->_result = new Jelly_Collection($this->_result->execute($db), $this->_as_object);
		
		// Trigger after_query callbacks
		if ($meta)
		{
			$meta->events()->trigger('builder.after_select', $this);
		}

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
		$db   = $this->_db($db);
		$meta = $this->_meta;
		
		// Trigger callbacks
		$meta AND $meta->events()->trigger('builder.before_insert', $this);
		
		// Ready to leave the builder
		$result = $this->_build(Database::INSERT)->execute($db);
		
		// Trigger after_query callbacks
		$meta AND $meta->events()->trigger('builder.after_insert', $this);
		
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
		$db   = $this->_db($db);
		$meta = $this->_meta;
		
		// Trigger callbacks
		$meta AND $meta->events()->trigger('builder.before_update', $this);
		
		// Ready to leave the builder
		$result = $this->_build(Database::UPDATE)->execute($db);
		
		// Trigger after_query callbacks
		$meta AND $meta->events()->trigger('builder.after_update', $this);
			
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
		$db     = $this->_db($db);
		$meta   = $this->_meta;
		$result = NULL;
		
		// Trigger callbacks
		if ($meta)
		{
			// Listen for a result to see if we need to actually delete the record
			$result = $meta->events()->trigger('builder.before_delete', $this);
		}
		
		if ($result === NULL)
		{
			$result = $this->_build(Database::DELETE)->execute($db);
		}
		
		// Trigger after_query callbacks
		if ($meta)
		{
			// Allow the events to modify the result
			$result = $meta->events()->trigger('builder.after_delete', $this);
		}
		
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
		$db   = $this->_db($db);
		$meta = $this->_meta;
		
		// Trigger callbacks
		$meta AND $meta->events()->trigger('builder.before_select', $this);
		
		// Start with a basic SELECT
		$query = $this->_build(Database::SELECT)->as_object(FALSE);
		
		// Dump a few unecessary bits that cause problems
		$query->_select = $query->_order_by = array();

		// Find the count
		$result = (int) $query
		               ->select(array('COUNT("*")', 'total'))
		               ->execute($db)
		               ->get('total');
		
		// Trigger after_query callbacks
		$meta AND $meta->events()->trigger('builder.after_select', $this);
		
		return $result;
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
	 * Selects for a specific key and limits the selection to 1 so that
	 * a single model is returned.
	 *
	 * @param   mixed  $key 
	 * @return  $this
	 */
	public function key($key)
	{
		return $this->where($this->_model.'.'.$this->unique_key($key), '=', $key)->limit(1);
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
	 * Returns results as objects
	 *
	 * @param   string  classname or TRUE for stdClass
	 * @return  $this
	 */
	public function as_object($class = TRUE, array $params = NULL)
	{
		// Class is TRUE, default to the model
		if ($class === TRUE AND $this->_meta)
		{
			$class = Jelly::class_name($this->_meta->model());
		}
		
		return parent::as_object($class);
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
	 * Returns the meta object attached to this builder 
	 * or NULL if nothing is attached.
	 * 
	 * @return  Jelly_Meta
	 */
	public function meta()
	{
		return $this->_meta;
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
		return parent::and_where($this->_field_alias($column, $value), $op, $value);
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
		return parent::or_where($this->_field_alias($column, $value), $op, $value);
	}
	
	/**
	 * Choose the fields(s) to select from. 
	 * 
	 *     $query->select_column('column');
	 *     $query->select_column('field', 'alias');
	 *     $query->select_column(array('column', 'column2', '...'));
	 *
	 * @param   string|array  list of field names or actual columns
	 * @param   string        An optional alias if passing a string for $columns
	 * @return  $this
	 */
	public function select_column($columns, $alias = NULL)
	{
		// Allow passing a single argument
		if ( ! is_array($columns))
		{
			// Check for an alias
			if ($alias)
			{
				$columns = array($columns, $alias);
			}
			
			$columns = array($columns);
		}
		
		foreach ($columns as $i => $column)
		{
			if (is_array($column))
			{
				$columns[$i][0] = $this->_field_alias($column[0]);
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
						$add = array();

						foreach ($meta->fields() as $field)
						{
							if ($field->in_db)
							{
								$add[] = array($this->_field_alias($field->model.'.'.$field->name), $field->name);
							}
							else if ($field->column instanceof Database_Expression)
							{
							    $add[] = array($field->column, $field->name);
							}
						}

						// Add these columns before we continue
						parent::select_array($add);

						// Remove the item we just added. It's no longer valid
						unset($columns[$i]);
						continue;
					}
				}

				$columns[$i] = $this->_field_alias($column);
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
			$table = $this->_model_alias($table);
			
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
		return parent::join($this->_model_alias($table), $type);
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
		return parent::on($this->_field_alias($c1), $op, $this->_field_alias($c2));
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
				$columns[$i][0] = $this->_field_alias($column[0]);
			}
			else
			{
				$columns[$i] = $this->_field_alias($column);
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
		return parent::and_having($this->_field_alias($column, $value), $op, $value);
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
		return parent::or_having($this->_field_alias($column, $value), $op, $value);
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
		return parent::order_by($this->_field_alias($column), $direction);
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
			$column = $this->_field_alias($column, $value, FALSE);
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
				$columns[$i] = $this->_field_alias($column, NULL, FALSE);
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
			foreach ($meta->fields() as $alias => $select)
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
	 * Initializes the builder by setting a default 
	 * table and adding the load_with joins.
	 *
	 * @return void
	 */
	protected function _initialize()
	{
		// Set a few defaults
		if ($this->_meta)
		{
			$this->from($this->_meta->model());

			// Load with automatically here.
			foreach ($this->_meta->load_with() as $relationship)
			{
				$this->with($relationship);
			}

			// Default to loading the current model
			$this->as_object(TRUE);
		}
		else
		{
			$this->from($this->_model);
		}
	}
	
	/**
	 * Aliases a model to its actual table name. Returns an alias
	 * suitable to pass to from() or join().
	 *
	 * @param  string $model 
	 * @return array
	 */
	protected function _model_alias($model)
	{
		$original = $table = $model;
		$alias = NULL;
		$found = NULL;
		
		// Split apart array(table, alias)
		if (is_array($model))
		{
			list($model, $alias) = $model;

			$original = "$model.$alias";
		}
		
		// Check to see if it's a known alias first
		if (isset($this->_alias_cache[$model]))
		{
			return $this->_alias_cache[$model];
		}
	
		// We're caching results to improve speed
		if ( ! isset($this->_model_cache[$original]))
		{	
			// Standard model
			if ($meta = Jelly::meta($model))
			{
				$table = $meta->table();
				$alias = $alias ? $alias : $table;
			}
			// Joinable field was passed, use its model
			else if (($pos = strpos($model, ':')) !== FALSE) 
			{
				if ($pos !== 0)
				{
					list($parent, $field) = explode(':', $model, 2);
				}
				else
				{
					$field = substr($model, 1);
					$parent = $this->_model;
				}
				
				$alias = $alias ? $alias : $parent.':'.$field;
				$model = Jelly::meta($parent)->field($field)->foreign['model'];
				$table = Jelly::meta($model)->table();
			}
			// Unknown Table
			else
			{	
				$table = $model;
				$model = NULL;
				$alias = $alias ? $alias : $table;
			}
			
			// Cache what we've found
			$this->_model_cache[$original] = array($table, $alias, $model);
			$this->_alias_cache[$alias]    = $this->_model_cache[$original];
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
	 * @param   string  A value to pass to unique_key, if necessary
	 * @return  string
	 */
	protected function _field_alias($field, $value = NULL, $join_if_sure = TRUE)
	{
		$original = $field;
		
		// Do nothing for Database Expressions and sub-queries
		if ($field instanceof Database_Expression OR $field instanceof Database_Query)
		{
			return $field;
		}

		// Alias the field(s) in FUNC("field")
		if (strpos($field, '"') !== FALSE)
		{	
			return preg_replace('/"(.+?)"/e', '"\\"".$this->_field_alias("$1")."\\""', $field);
		}
		
		// We always return fields as they came
		$join = (boolean) strpos($field, '.');
		
		// Determine the default model
		if ( ! $join)
		{
			$model = $this->_model;
		}
		else
		{
			list($model, $field) = explode('.', $field, 2);	
		}
		
		// Have the column default to the field
		$column = $field;
		
		// Alias the model
 		list(, $alias, $model) = $this->_model_alias($model);
		
		// Expand meta-aliases
		if (strpos($field, ':') !== FALSE)
		{
			extract($this->_meta_alias($field, array(
				'model' => $model,
				'field' => $field,
				'value' => $value,
			)));
			
			$column = $field;
		}
		
		// Alias to the column
		if ($meta = Jelly::meta($model) AND $field_obj = $meta->field($field) AND $field_obj->in_db)
		{
			$column = $field_obj->column;
			
			// We're 99% sure adding the table name in front won't cause problems now
			$join = $join_if_sure ? TRUE : $join;
		}
		
		return $join ? $alias.'.'.$column : $column;
	}
	
	/**
	 * Resolves meta-aliases.
	 *
	 * @param   string $alias 
	 * @param   array  $state 
	 * @return  array
	 */
	protected function _meta_alias($alias, $state)
	{
		$original = $alias;
		
		// The default model is the current field's model
		$model = isset($state['model']) ? $state['model'] : $this->_model;
		
		// Check for a model operator
		if (substr($alias, 0, 1) !== ':')
		{
			list($model, $alias) = explode(':', $alias);

			// Append the : back onto $field, it's key for recognizing the alias below
			$alias = ':'.$alias;
		}
		
		return $this->_expand_alias($model, $alias, $state);
	}
	
	/**
	 * Easy-to-override method that expands aliases.
	 *
	 * @param   string $model 
	 * @param   string $alias
	 * @param   array $state 
	 * @return  array
	 */
	protected function _expand_alias($model, $alias, $state)
	{
		switch ($alias)
		{
			case ':primary_key':
				$state['field'] = Jelly::meta($model)->primary_key();
				break;
			case ':name_key':
				$state['field'] = Jelly::meta($model)->name_key();
				break;
			case ':foreign_key':
				$state['field'] = Jelly::meta($model)->foreign_key();
				break;
			case ':unique_key':
				$state['field'] = Jelly::query(Jelly::meta($model)->model())->unique_key($state['value']);
				break;
			default:
				throw new Kohana_Exception('Unknown meta alias :alias', array(
					':alias' => $alias));
		}
		
		return $state;
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
				$query = DB::update(current($this->_from[0]));
				break;

			case Database::INSERT:
				$query = DB::insert(current($this->_from[0]));
				break;

			case Database::DELETE:
				$query = DB::delete(current($this->_from[0]));
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
