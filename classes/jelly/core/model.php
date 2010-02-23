<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly is a Kohana 3.0 ORM. It itself is a conceptual fork of Kohana's ORM 
 * and Sprig. Some code and ideas are borrowed from both projects.
 * 
 * @package Jelly
 * 
 * @author Jonathan Geiger
 * @author Paul Banks
 * @author Woody Gilk
 * @author Kohana Team
 * 
 * @link http://github.com/shadowhand/sprig/
 * @link http://github.com/jheathco/kohana-orm
 * 
 * @license http://kohanaphp.com/license.html
 */
abstract class Jelly_Core_Model
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
	 * @param	mixed  $model  A model name or another Jelly to create
	 * @param	mixed  $id	   The id or where clause to load upon construction
	 * @return	Jelly
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
	 * @var array Data that's already been retrieved is cached
	 */
	protected $_retrieved = array();
	
	/**
	 * @var array Unmapped data that is still accessible
	 */
	protected $_unmapped = array();

	/**
	 * @var boolean Whether or not the model is loaded
	 */
	protected $_loaded = FALSE;
	
	/**
	 * @var Jelly_Meta a copy of this object's meta object
	 */
	protected $_meta = NULL;
	
	/**
	 * @var array a copy of this object's fields
	 */
	protected $_fields = array();
	
	/**
	 * @var boolean Whether or not the model is saved
	 */
	protected $_saved = FALSE;
	
	/**
	 * @var array Data set by mysql_fetch_object. Daggers to ye who overloads this.
	 */
	protected $_preload_data = array();
	
	/**
	 * @var array With data
	 */
	protected $_with = array();
	
	/**
	 * @var array Applied with associations
	 */
	protected $_with_applied = array();
	
	/**
	 * @var array Incoming with results
	 */
	protected $_with_values = array();

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
	 * @param	mixed  $cond  A primary key or where clause to use for auto-loading a particular record
	 **/
	public function __construct($cond = NULL)
	{
		// Copy over the defaults into the original data. This also has 
		// the added benefit of registering the model's metadata, if it does not exist yet
		$this->_original = $this->meta('defaults');
		
		// Add the values stored by mysql_set_object
		if (!empty($this->_preload_data) && is_array($this->_preload_data))
		{
			$this->set($this->_preload_data, TRUE, TRUE);
			$this->_loaded = $this->_saved = TRUE;
			$this->_preload_data = array();
		}
		
		// Have an id? Attempt to load it
		if ($cond && (is_int($cond) || is_string($cond) || is_array($cond)))
		{
			$this->load($cond, 1);
		}
	}
	
	/**
	 * Displays the record's id
	 *
	 * @return string
	 */
	public function __toString()
	{
		return (string) $this->id();
	}
	
	/**
	 * Returns field values as members of the object. 
	 * 
	 * A few things to note:
	 * 
	 * * Values that are returned are cached (unlike get()) until they are changed
	 * * Relations are automatically load()ed
	 *
	 * @see	   get()
	 * @param  string $name 
	 * @return mixed
	 */
	public function __get($name)
	{	
		// Alias the field to its actual name. We must do this now
		// so that any aliases will be cached under the real fields
		// name, rather than under its alias name
		$name = $this->field($name, TRUE);
		
		if (!array_key_exists($name, $this->_retrieved))
		{
			$value = $this->get($name);
			
			// Auto-load relations
			if ($value instanceof Jelly && !$value->_loaded)
			{
				$this->_retrieved[$name] = $value->load();
			}
			else
			{
				$this->_retrieved[$name] = $value;
			}
		}
		
		return $this->_retrieved[$name];
	}
	
	/**
	 * Gets the internally represented value from a field or unmapped column.
	 * 
	 * * If an array or TRUE is passed for $name, an array of fields will be returned.
	 * * If $changed is FALSE, only original data for the field will be returned.
	 *
	 * @param	array|int|boolean $name		The field's name
	 * @param	boolean			  $changed
	 * @return	mixed
	 */
	public function get($name, $changed = TRUE)
	{	
		$meta = $this->meta();
		
		// Passing TRUE or an array of fields to get returns them as an array
		if (is_array($name) || $name === TRUE)	
		{
			$fields = ($name === TRUE) ? array_keys($meta->fields) : $name;
			$result = array();

			foreach($fields as $field)
			{
				if ($changed)
				{
					$result[$field] = $this->__get($field);
				}
				else
				{
					$result[$field] = $this->get($field, FALSE);
				}
			}

			return $result;
		}
		else 
		{
			if ($field = $this->field($name))
			{	
				// Alias the name to its actual name
				$name = $field->name;
				
				// Changes trump with() and original values
				if ($changed && array_key_exists($name, $this->_changed))
				{	
					$value = $field->get($this, $this->_changed[$name]);
				}
				else if ($changed && array_key_exists($name, $this->_with_values))
				{
					$model = Jelly::Factory($name);
					$model->set($this->_with_values[$name], FALSE, TRUE);
					
					// Try and verify that it's actually loaded
					if ($model->id())
					{
						$model->_loaded = TRUE;
						$model->_saved = TRUE;
					}
					
					$value = $model;
				}
				else
				{
					$value = $field->get($this, $this->_original[$name]);
				}
				
				return $value;
			}
			// Return unmapped data from custom queries
			else if (isset($this->_unmapped[$name]))
			{
				return $this->_unmapped[$name];
			}
		}
	}
	
	/**
	 * Allows members to be set on the object.
	 * 
	 * Under the hood, this is just proxying to set()
	 *
	 * @see	   set()
	 * @param  string $name 
	 * @param  mixed $value 
	 * @return void
	 */
	public function __set($name, $value)
	{
		// Being set by mysql_fetch_object, store the values for the constructor
		if (empty($this->_original))
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
	 * A few things to note:
	 * 
	 *	- If $values is a string, $alias will be used as the value 
	 *	  and $alias will be set to False.
	 *	- If $original is TRUE, the data will be set as original 
	 *	  (not changed) as if it came from the database.
	 *
	 * @param  string  $name 
	 * @param  string  $value 
	 * @return Jelly   Returns $this
	 */
	public function set($values, $alias = FALSE, $original = FALSE)
	{
		$meta = $this->meta();
		
		// Accept set('name', 'value');
		if (!is_array($values))
		{
			$values = array($values => $alias);
			$alias = FALSE;
		}
		
		// Determine where to write the data to, changed or original
		if ($original)
		{
			$data_location =& $this->_original;
		}
		else
		{
			$data_location =& $this->_changed;
		}

		// Why this way? Because it allows the model to have 
		// multiple fields that are based on the same column
		foreach($values as $key => $value)
		{
			// Key is coming from a with statement
			if (substr($key, 0, 1) === ':')
			{
				$targets = explode(':', ltrim($key, ':'), 2);
				
				// Alias as it comes back in, which allows people to use with()
				// with alaised field names
				$relationship = $this->field(array_shift($targets), TRUE);
								
				if (!array_key_exists($relationship, $this->_with_values))
				{
					$this->_with_values[$relationship] = array();
				}
				
				$this->_with_values[$relationship][implode(':', $targets)] = $value;
			}
			// Key is coming from a database result
			else if ($alias === TRUE && !empty($meta->columns[$key]))
			{
				// Contains an array of fields that the column is mapped to
				// This allows multiple fields to get data from the same column
				foreach ($meta->columns[$key] as $field)
				{
					$data_location[$field] = $meta->fields[$field]->set($value);
					
					// Invalidate the cache
					if (array_key_exists($field, $this->_retrieved))
					{
						unset($this->_retrieved[$field]);
					}
				}
			}
			// Standard setting of a field 
			else if ($alias === FALSE && $field = $this->field($key))
	        {
				$value = $field->set($value);

				// Check if the original value has really changed
				if ($this->_original[$field->name] !== $value) 
				{
					$data_location[$field->name] = $value;
	
					// Invalidate the cache
					if (array_key_exists($field->name, $this->_retrieved))
					{
						unset($this->_retrieved[$field->name]);
					}
				}
 			}
			else
			{
				$this->_unmapped[$key] = $value;
			}
		}
		
		return $this;
	}
	
	/**
	 * Returns true if $name is a field of the model or an unmapped column.
	 *
	 * @param  string	$name 
	 * @return boolean
	 */
	public function __isset($name)
	{
		return ($this->field($name) || array_key_exists($name, $this->_unmapped));
	}
	
	/**
	 * This doesn't unset fields. Rather, it sets them to their default 
	 * value. Unmapped, changed, and retrieved values are unset.
	 * 
	 * In essence, unsetting a field sets it as if you never made any changes 
	 * to it, and clears the cache if the value has been retrieved with those changes.
	 *
	 * @param  string $name 
	 * @return void
	 */
	public function __unset($name)
	{
		if ($field = $this->field($name, TRUE))
		{
			// We don't want to unset the keys, because 
			// they are assumed to exist. Just set them back to defaults
			$this->_original[$field] = $this->meta()->defaults[$field];
			
			// Ensure changed and retrieved data is cleared
			// This effectively clears the cache and any changes
			if (array_key_exists($name, $this->_changed))
			{
				unset($this->_changed[$name]);
			}
			
			if (array_key_exists($name, $this->_retrieved))
			{
				unset($this->_retrieved[$name]);
			}
		}
		
		// This doesn't matter
		if (array_key_exists($name, $this->_unmapped))
		{
			unset($this->_unmapped[$name]);
		}
	}
	
	/**
	 * Handles pass-through to database methods. Calls to query methods
	 * (query, get, insert, update) are not allowed. Query builder methods
	 * are chainable.
	 *
	 * @param	string	method name
	 * @param	array	method arguments
	 * @return	Jelly	Returns $this
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
					$args[0][0] = Jelly_Meta::table($args[0][0]);
				}
				else
				{
					$args[0] = Jelly_Meta::table($args[0]);
				}
			}
			
			// Join on
			else if ($method == 'on')
			{
				$args[0] = $this->_qb_alias($args[0], TRUE);
				$args[2] = $this->_qb_alias($args[2], TRUE);
			}
			
			// Everything else
			else if (in_array($method, self::$_alias))
			{
				$args[0] = $this->_qb_alias($args[0], TRUE);
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
	 * Allows serialization of a model and all of its retrieved and related properties.
	 * 
	 * This fixes a bug with retrieved MySQL results.
	 *
	 * @return array
	 * @author Paul Banks
	 */
	public function __sleep()
	{
		foreach ($this->_retrieved as $field => $object)
		{
			if ($object instanceof Database_MySQL_Result)
			{
				// Database_MySQL_Results handle results differenly, so they must be converted
				// Otherwise they are invalide when they wake up.
				$this->_retrieved[$field] = new Database_Result_Cached($object->as_array(), '');				
			}
		}		

		// Return array of all properties to get them serialised
		return array_keys(get_object_vars($this));
	}
	
	/**
	 * Loads a single row or multiple rows. If $where is a string or integer
	 * it is assumed that you are searching for the model's primary key. In
	 * which case, $limit will be automatically set to 1 and the result
	 * will be loaded into $this.
	 * 
	 * If $limit is 1 the result is always loaded into $this. Otherwise,
	 * a database_result is returned.
	 *
	 * @param  mixed  $where  an array or id to load 
	 * @return mixed
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
		
		// Anything to load with?
		if ($meta->load_with)
		{
			$this->with($meta->load_with);
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
				// Insert the original values
				$this->set($result[0], TRUE, TRUE);
				
				// We're good!
				$this->_loaded = $this->_saved = TRUE;
				$this->_changed = array();
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
	 * Creates a new record based on the current model. If save related is TRUE
	 * any changes made to relations will be updated as well.
	 * 
	 * Keep in mind, however, that only the relation to the other model will be saved,
	 * and not the actual model that it is related to.
	 * 
	 * If the model's meta data is set to validate on save, then 
	 * this could potentially throw a Validate_Exception.
	 *
	 * @param  bool	  Whether or not to save related changes
	 * @return Jelly  Returns $this
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
				if (array_key_exists($column, $this->_changed))
				{
					$this->_original[$column] = $values[$field->column] = $field->save($this, $this->_changed[$column]);
				}
				// Set default data. Careful not to override unchanged data!
				else if ($this->_original[$column] == $meta->defaults[$column] && !$field->primary)
				{
					$this->_original[$column] = $values[$field->column] = $field->save($this, $field->default);
				}
			}
			else if ($field instanceof Jelly_Behavior_Field_Saveable)
			{
				$relations[$column] = $field;
			}
		}
		
		// Remove the primary key if it's empty, SQLite accepts it but 
		// most other databases won't if it's going to auto-increment
		// It is often inadvertently set by validate() when inserting
		if (empty($values[$meta->primary_key]))
		{
			unset($values[$meta->primary_key]);
			unset($this->_changed[$meta->primary_key]);	
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
				if (array_key_exists($column, $this->_changed))
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
	 * Deletes a single or multiple records
	 * 
	 * If we're loaded(), it just deletes this object, otherwise it deletes 
	 * whatever the query matches. 
	 *
	 * @param  $where  A simple where statement
	 * @return Jelly   Returns $this
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
	 * Counts the number of records for the current query
	 *
	 * @param	mixed  $where  An associative array to use as the where clause, or a primary key
	 * @return	Jelly  Returns $this
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
	 * Allows joining 1:1 relationships in a single query.
	 *
	 * @param string $alias 
	 * @return void
	 */
	public function with($relationship)
	{
		$meta = $this->meta();
		
		// Make sure we select for this object, since * will not be applied
		if (empty($this->_with_applied))
		{
			$this->select('*');
		}
		
		// Allow unlimited args
		foreach ((array)$relationship as $target_path)
		{
			// We'll start with the first one and work our way down
			$paths = explode(":", $target_path);
			$parent = $this;
			$chain = '';
			
			foreach ($paths as $iteration => $path)
			{
				$field = Jelly_Meta::field($parent, $path);
				
				if (!($field instanceof Jelly_Behavior_Field_Joinable))
				{
					// Entire list is invalid
					continue 2;
				}

				// If we're on the first iteration, the parent path is just the 
				// name of the model, otherwise we use the chain
				if ($iteration === 0)
				{
					$prev_chain = Jelly_Meta::model_name($this);
				}
				else
				{
					$prev_chain = $chain;
				}
				
				$chain .= ":".$field->name;
						
				// Set the next iteration's parent
				$model = $field->foreign['model'];
				
				// Select all of the model's fields
				foreach (Jelly_Meta::get($model)->fields as $alias => $select)
				{
					if ($select->in_db)
					{
						// Withs have to manually alias
						$column = Jelly_Meta::column($model, $alias);
						
						// We have to manually alias, since the path does not necessarily correspond to the path
						$this->select(array($chain.'.'.$column, $chain.':'.$alias));
					}
				}
				
				// Let the field finish the rest
				$field->with($this, $path, $chain, $prev_chain);
				
				// Model now becomes the parent
				$parent = $model;
			}
		}
		
		return $this;
	}
			

	/**
	 * Returns whether or not that model is related to the 
	 * $model specified. This only works with relationships
	 * where the model "has" another model or models:
	 * 
	 * has_many, has_one, many_to_many
	 *
	 * @param  string	$name 
	 * @param  mixed	$models
	 * @return boolean
	 */
	public function has($name, $models)
	{
		$field = $this->field($name);
		
		// Don't continue without knowing we have something to work with
		if ($field instanceof Jelly_Behavior_Field_Haveable)
		{
			$name = $field->name;
		}
		else
		{
			return FALSE;
		}
		
		$ids = array();
		
		// Everything comes in as an array of ids, so we must convert things like
		// has ('alias', 1), or has('alias', $some_jelly_model)
		if (!is_array($models) && !$models instanceof Iterator)
		{
			if (is_object($models))
			{
				$models = $models->id();
			}
			
			$ids[] = $models;
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
		
		return $field->has($this, $ids);
	}
	
	/**
	 * Adds a specific model(s) to the relationship.
	 * 
	 * $models can be one of the following:
	 * 
	 * - A primary key
	 * - Another Jelly model
	 * - An iterable collection of primary keys or 
	 *	 Jelly models, such as an array or Database_Result
	 * 
	 * Even though semantically odd, this method can be used for 
	 * changing 1:1 relationships like hasOne and belongsTo.
	 * 
	 * If you set more than one for these types of relationships,
	 * however, only the first will be used.
	 *
	 * @param  string  $name 
	 * @param  string  $models 
	 * @return Jelly   Returns $this
	 */
	public function add($name, $models)
	{
		return $this->_change($name, $models, TRUE);
	}
	
	/**
	 * Removes a specific model(s) from the relationship.
	 * 
	 * $models can be one of the following:
	 * 
	 * - A primary key
	 * - Another Jelly model
	 * - An iterable collection of primary keys or 
	 *	 Jelly models, such as an array or Database_Result
	 * 
	 * Even though semantically odd, this method can be used for 
	 * changing 1:1 relationships like hasOne and belongsTo.
	 * 
	 * If you set more than one for these types of relationships,
	 * however, only the first will be used.
	 *
	 * @param  string  $name 
	 * @param  string  $models 
	 * @return Jelly   Returns $this
	 */
	public function remove($name, $models)
	{
		return $this->_change($name, $models, FALSE);
	}
	
	/**
	 * Validates and filters the data
	 *
	 * @throws Validate_Exception
	 * @return void
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
		
		if (empty($data))
		{
			return $this;
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
			$this->set($data->as_array());
		}
		else
		{
			throw new Validate_Exception($data);
		}
		
		return $this;
	}
	
	/**
	 * Aliases a column that exists only in this model
	 *
	 * If $field is null, the model's table name is returned.
	 * Otherwise, the normal rules apply.
	 * 
	 * @param  string	$field	The field's name
	 * @param  boolean	$join	Whether or not to return the table and column joined
	 * @return string
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
		if ($this->field($field))
		{
			$field = $this->field($field)->column;
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
		// Allow custom classes and such
		else 
		{
			$query->as_object($as_object);
		}
		
		return $query->execute($meta->db);
	}
	
	/**
	 * Initializes the Database Builder to given query type
	 *
	 * @param	int	 Type of Database query
	 * @return	Database_Query_Builder
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
	 * Resets the model to an empty state, as if you 
	 * were constructing a brand new object.
	 * 
	 * All changes, queries, and other state is lost.
	 *
	 * @return void
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
	 * Resets the database builder
	 *
	 * @return Jelly  Returns $this
	 */
	public function end()
	{
		$this->_db_builder = NULL;
		$this->_db_applied = array();
		$this->_db_pending = array();
		$this->_with_applied = array();
		
		return $this;
	}

	/**
	 * Returns a view object the represents the field. If $prefix is an array,
	 * it will be used for the data and $prefix will be set to the default.
	 *
	 * @param  string		 $name	  The field to render
	 * @param  string|array	 $prefix 
	 * @param  string		 $data 
	 * @return View
	 */
	public function input($name, $prefix = NULL, $data = array())
	{
		$meta = $this->meta();
		$name = $this->field($name, TRUE);
		
		if (isset($meta->fields[$name]))
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
				$prefix = $meta->input_prefix;
			}
			
			// Ensure there is a default value. Some fields overridde this
			$data['value'] = $this->__get($name);
			$data['model'] = $this;
			
			return $meta->fields[$name]->input($prefix, $data);
		}
	}
	
	/**
	 * Returns metadata for this particular object
	 *
	 * @param  string  $property 
	 * @return Jelly_Meta
	 */
	public function meta($property = NULL)
	{
		// Cache the meta object
		if (!$this->_meta)
		{
			$this->_meta = Jelly_Meta::get($this);
		}
		
		if ($property)
		{
			if (isset($this->_meta->$property))
			{
				return $this->_meta->$property;
			}
			else
			{
				return NULL;
			}
		}
		
		return $this->_meta;
	}
	
	/**
	 * Returns a field for this particular object. Aliases are resolved automatically.
	 *
	 * @param  string  $property 
	 * @param  boolean $name
	 * @return Jelly_Field
	 */
	public function field($field, $name = FALSE)
	{
		if (empty($this->_fields[$field]))
		{
			$this->_fields[$field] = Jelly_Meta::field($this, $field);
		}
		
		if ($name)
		{
			if (is_object($this->_fields[$field]))
			{
				return $this->_fields[$field]->name;
			}
		}
		else
		{
			return $this->_fields[$field];
		}
		
		return NULL;
	}

	/**
	 * Returns whether or not the model is loaded
	 *
	 * @return boolean
	 */
	public function loaded()
	{	
		return $this->_loaded;
	}

	/**
	 * Whether or not the model is saved
	 *
	 * @return boolean
	 */
	public function saved()
	{	
		return $this->_saved;
	}
	
	/**
	 * Returns the value of the primary key for the row
	 *
	 * @return mixed
	 */
	public function id()
	{
		return $this->get($this->meta()->primary_key);
	}
	
	/**
	 * Returns the value of the model's primary value
	 *
	 * @return mixed
	 */
	public function name()
	{
		return $this->get($this->meta()->name_key);
	}

	/**
	 * Changes a relation by adding or removing specific records from the relation.
	 *
	 * @param  string  $name	The name of the field
	 * @param  mixed   $models	Models or primary keys to add or remove
	 * @param  string  $add		True to add, False to remove
	 * @return Jelly   Returns $this
	 */
	protected function _change($name, $models, $add)
	{
		$field = $this->field($name);
		
		if ($field instanceof Jelly_Behavior_Field_Changeable)
		{
			$name = $field->name;
		}
		else
		{
			return $this;
		}
		
		// If this is set, we don't need to re-retrieve the values
		if (!array_key_exists($name, $this->_changed))
		{
			$current = array();
			$value = $this->__get($name);
			
			if ($value instanceof Database_Result)
			{
				foreach ($value as $model)
				{
					$current[] = $model->id();
				}
			}
			else
			{
				$current[] = $value->id();
			}
		}
		else
		{
			$current = $this->_changed[$name];
		}
		
		$changes = array();
				
		// Handle Database Results
		if ($models instanceof Iterator || is_array($models))
		{
			foreach($models as $row)
			{
				if (is_object($row))
				{
					// Ignore unloaded relations
					if ($row->loaded())
					{
						$changes[] = $row->id();
					}
				}
				else
				{
					$changes[] = $row;
				}
			}
		}
		// And individual models
		else if (is_object($models))
		{
			// Ignore unloaded relations
			if ($models->loaded())
			{
				$current[] = $models->id();
			}
		}
		// And everything else
		else
		{
			$changes[] = $models;
		}
		
		// Are we adding or removing?
		if ($add)
		{
			$changes = array_unique(array_merge($current, $changes));
		}
		else
		{
			$changes = array_diff($current, $changes);
		}
		
		// Set it 
		$this->set($name, $changes);
		
		// Chainable
		return $this;
	}
	
	/**
	 * This is an internal method used for aliasing only things coming 
	 * to the query builder, since they can come in so many formats.
	 *
	 * @param  string	$field 
	 * @param  boolean	$join
	 * @return string
	 */
	protected function _qb_alias($field, $join = NULL)
	{
		$model = NULL;
		
		// Check for functions
		if (strpos($field, '"') !== FALSE)
		{
			// Quote the column in FUNC("ident") identifiers
			return preg_replace('/"(.+?)"/e', '"\\"".$this->_qb_alias("$1")."\\""', $field);
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
}