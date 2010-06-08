<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Core class that all fields must extend.
 *
 * @package  Jelly
 */
abstract class Jelly_Field_Core
{
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
	* @var  string  Description of the field. Default is `''` (an empty string).
	*/
	public $description = '';

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
	public $null = FALSE;

	/**
	* @var  array  {@link Kohana_Validate} filters for this field.
	*/
	public $filters = array();

	/**
	* @var  array  {@link Kohana_Validate} rules for this field.
	*/
	public $rules = array();

	/**
	* @var  array  {@link Kohana_Validate} callbacks for this field.
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

		// Check as to whether we need to add
		// some callbacks for shortcut properties
		if ($this->unique === TRUE)
		{
			$this->callbacks[] = array($this, '_is_unique');
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
			$this->label = inflector::humanize($column);
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
		return $value;
	}

	/**
	 * Returns a particular value processed according
	 * to the class's standards.
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $value
	 * @param   boolean      $loaded
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
	 * @param   Jelly  $model
	 * @param   mixed  $value
	 * @return  mixed
	 */
	public function save($model, $value, $loaded)
	{
		return $value;
	}

	/**
	 * Callback for validating that a field is unique.
	 *
	 * @param   Validate $data
	 * @param   string $field
	 * @return  void
	 * @author  Woody Gilk
	 */
	public function _is_unique(Validate $data, $field)
	{
		if ($data[$field])
		{
			$count = Jelly::query($this->model)
			              ->where($field, '=', $data[$field]);

			// Exclude unique key value from check if this is a lazy save
			if (isset($data[':unique_key']))
			{
				$count->where(':unique_key', '!=', $data[':unique_key']);
			}

			if ($count->count())
			{
				$data->error($field, 'unique');
			}
		}
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
