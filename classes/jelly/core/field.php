<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Core class that all fields must extend.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field
{
	/**
	 * Constants for checking field support
	 */
	const SAVE       = 'save';
	const HAS        = 'has';
	const WITH       = 'with';
	const ADD_REMOVE = 'add_remove';
	const JOIN       = 'join';
	
	/**
	 * @var  string  The model's name
	 */
	public $model;

	/**
	 * @var  string  The column's name in the database
	 */
	public $column;

	/**
	 * @var  string  A pretty name for the field
	 */
	public $label;

	/**
	 * @var  string  The field's name in the form
	 */
	public $name;

	/**
	 * @var  boolean  Whether or not the field should be unique
	 */
	public $unique = FALSE;

	/**
	* @var  boolean  A primary key field.
	*/
	public $primary = FALSE;

	/**
	* @var  boolean  The column is present in the database table. Default: TRUE
	*/
	public $in_db = TRUE;

	/**
	* @var  mixed  Default value
	*/
	public $default = NULL;

	/**
	 * @var  boolean  Whether or not empty() values should be converted to NULL
	 */
	public $convert_empty = FALSE;
	
	/**
	 * @var  mixed   The value to convert empty values to. This is only used if convert_empty is TRUE
	 */
	public $empty_value = NULL;
	
	/**
	 * @var  boolean  Whether or not NULL values are allowed
	 */
	public $allow_null = TRUE;

	/**
	* @var  array  {@link Jelly_Validator} filters for this field.
	*              Filters are called whenever data is set on the field.
	*/
	public $filters = array();

	/**
	* @var  array  {@link Jelly_Validator} rules for this field.
	*/
	public $rules = array();

	/**
	* @var  array  {@link Jelly_Validator} callbacks for this field.
	*/
	public $callbacks = array();

	/**
	 * Sets all options
	 *
	 * @return  void
	 **/
	public function __construct($options = array())
	{
		// Assume it's the column name
		if (is_string($options))
		{
			$this->column = $options;
		}
		elseif (is_array($options))
		{
			// Just throw them into the class as public variables
			foreach ($options as $name => $value)
			{
				$this->$name = $value;
			}
		}
		
		// See if we need to allow_null values because of convert_empty
		if ($this->convert_empty AND $this->empty_value === NULL)
		{
			$this->allow_null = TRUE;
		}
		
		// Default value is going to be NULL if null is true
		// to mimic the SQL defaults
		if ( ! array_key_exists('default', (array) $options) AND $this->allow_null)
		{
			$this->default = NULL;
		}
		
		// Default the empty value to NULL when allow_null is TRUE, but be careful not
		// to override a programmer-configured empty_value
		if ( ! empty($options['allow_null']) AND ! array_key_exists('empty_value', (array) $options))
		{
			$this->empty_value = NULL;
		}
	}

	/**
	 * This is called after construction so that fields can finish
	 * constructing themselves with a copy of the column it represents.
	 *
	 * @param   string  $model
	 * @param   string  $column
	 * @return  void
	 **/
	public function initialize($model, $column)
	{
		// This will come in handy for setting complex relationships
		$this->model = $model;

		// This is for naming form fields
		$this->name = $column;

		if ( ! $this->column)
		{
			$this->column = $column;
		}

		// Check for a name, because we can easily provide a default
		if ( ! $this->label)
		{
			$this->label = ucwords(inflector::humanize($column));
		}
		
		// Check as to whether we need to add
		// some callbacks for shortcut properties
		if ($this->unique === TRUE)
		{
			$this->rules[] = array(array($this, '_is_unique'), array(':validate', ':model', ':value', ':key'));
		}
	}

	/**
	 * Sets a particular value processed according
	 * to the class's standards.
	 *
	 * @param   mixed  $value
	 * @return  mixed
	 **/
	public function set($value)
	{
		list($value, $return) = $this->_default($value);
		
		return $value;
	}

	/**
	 * Returns a particular value processed according
	 * to the class's standards.
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $value
	 * @return  mixed
	 **/
	public function get($model, $value)
	{
		return $value;
	}

	/**
	 * Called just before saving if the field is $in_db, and just after if it's not.
	 *
	 * If $in_db, it is expected to return a value suitable for insertion
	 * into the database.
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $value
	 * @param   bool         $loaded
	 * @return  mixed
	 */
	public function save($model, $value, $loaded)
	{
		return $value;
	}
	
	/**
	 * Triggered whenever the model this field is attached to is deleted.
	 * 
	 * This is useful for fields that need to implement some sort of
	 * garbage collection. 
	 * 
	 * This method is called just before the actual record in the database
	 * is deleted, and is not called at all if a model behavior stops 
	 * the actual deletion of the record.
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $key
	 * @return  void
	 */
	public function delete($model, $key)
	{
		return;
	}
	
	/**
	 * Returns whether or not a field supports a particular feature.
	 * 
	 * This is abstracted away so Jelly_Model and Jelly_Builder don't
	 * have to litter their code with instanceof checks and so we
	 * can change the underlying implementation at will.
	 * 
	 * It is also easily overridable so custom fields can add their
	 * own support for specific features if they want.
	 * 
	 * @param   string   The feature you're checking for support
	 * @return  boolean
	 */
	public function supports($feature)
	{
		switch ($feature)
		{
			case Jelly_Field::SAVE:
				return $this instanceof Jelly_Field_Supports_Save;
			case Jelly_Field::WITH:
				return $this instanceof Jelly_Field_Supports_With;
			case Jelly_Field::HAS:
				return $this instanceof Jelly_Field_Supports_Has;
			case Jelly_Field::ADD_REMOVE:
				return $this instanceof Jelly_Field_Supports_AddRemove;
			case Jelly_Field::JOIN:
				return $this instanceof Jelly_Field_Supports_Join;
		}
		
		return FALSE;
	}
	

	/**
	 * Callback for validating that a field is unique.
	 *
	 * @param   Validate $data
	 * @param   string $field
	 * @return  void
	 */
	public function _is_unique(Validate $data, Jelly_Model $model, $value, $key)
	{	
		// According to the SQL standard NULL is not checked by the unique constraint
		if ($data[$this->name] !== NULL)
		{
			$query = Jelly::query($model)->where($this->name, '=', $data[$this->name]);

			// Exclude unique key value from check if this is a lazy save
			if ($key)
			{
				$query->where(':unique_key', '!=', $key);
			}

			if ($query->count())
			{
				$data->error($this->name, 'unique');
			}
		}
	}
	
	/**
	 * Potentially converts the value to NULL or default depending on
	 * the fields configuration. An array is returned with the first
	 * element being the new value and the second being a boolean
	 * as to whether the field should return the value provided or
	 * continue processing it.
	 *
	 * @param   mixed  $value 
	 * @return  array
	 */
	protected function _default($value)
	{
		$return = FALSE;
		
		// Convert empty values to NULL, if needed
		if ($this->convert_empty AND empty($value))
		{
			$value  = $this->empty_value;
			$return = TRUE;
		}
		
		// Allow NULL values to pass through untouched by the field
		if ($this->allow_null AND $value === NULL)
		{
			$value  = NULL;
			$return = TRUE;
		}
		
		return array($value, $return);
	}

	/**
	 * Converts a bunch of types to an array of ids
	 *
	 * @param   mixed  $models
	 * @return  array
	 */
	protected function _ids($models)
	{
		$ids = array();

		// Handle Database Results
		if ($models instanceof Iterator OR is_array($models))
		{
			foreach($models as $row)
			{
				if (is_object($row))
				{
					// Ignore unloaded relations
					if ($row->loaded())
					{
						$ids[] = $row->id();
					}
				}
				else
				{
					$ids[] = $row;
				}
			}
		}
		// And individual models
		elseif (is_object($models))
		{
			// Ignore unloaded relations
			if ($models->loaded())
			{
				$ids[] = $models->id();
			}
		}
		// And everything else
		else
		{
			$ids[] = $models;
		}

		return $ids;
	}
}
