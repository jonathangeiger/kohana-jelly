<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles timestamps and conversions to and from different formats.
 *
 * All timestamps are represented internally by UNIX timestamps, regardless
 * of their format in the database. When the model is saved, the value is
 * converted back to the format specified by $format (which is a valid
 * date() string).
 *
 * This means that you can have timestamp logic exist relatively independently
 * of your database's format. If, one day, you wish to change the format used
 * to represent dates in the database, you just have to update the $format
 * property for the field.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_Timestamp extends Jelly_Field
{
	/**
	 * @var  int  Default is NULL, which implies no date
	 */
	public $default = NULL;
	
	/**
	 * @var  boolean  Whether or not to automatically set now() on creation
	 */
	public $auto_now_create = FALSE;

	/**
	 * @var  boolean  Whether or not to automatically set now() on update
	 */
	public $auto_now_update = FALSE;

	/**
	 * @var  string  A date formula representing the time in the database
	 */
	public $format = NULL;
	
	/**
	 * Constructor. Sets the default to 0 if we have no format, or an empty string otherwise.
	 *
	 * @param   array   $options 
	 */
	public function __construct($options = array())
	{
		parent::__construct($options);
		
		if ( ! isset($options['default']) AND ! $this->allow_null)
		{
			// Having a format implies we're saving a string, so we want a proper default
			$this->default = $this->format ? '' : 0;
		}
	}

	/**
	 * Converts the time to a UNIX timestamp
	 *
	 * @param   mixed  $value
	 * @return  mixed
	 */
	public function set($value)
	{
		list($value, $return) = $this->_default($value);
		
		if ( ! $return)
		{
			if (is_numeric($value))
			{
				$value = (int) $value;
			}
			else if (FALSE !== ($to_time = strtotime($value)))
			{
				$value = $to_time;
			}
		}
		
		return $value;
	}

	/**
	 * Automatically creates or updates the time and
	 * converts it, if necessary
	 *
	 * @param   Jelly  $model
	 * @param   mixed  $value
	 * @return  mixed
	 */
	public function save($model, $value, $loaded)
	{
		// Do we need to provide a default since we're creating or updating
		if (( ! $loaded AND $this->auto_now_create) OR ($loaded AND $this->auto_now_update))
		{
			$value = time();
		}

		// Convert if necessary
		if ($this->format)
		{
			// Does it need converting?
			if ( ! is_numeric($value) AND FALSE !== strtotime($value))
			{
				$value = strtotime($value);
			}

			if (is_numeric($value))
			{
				$value = date($this->format, $value);
			}
		}

		return $value;
	}
}
