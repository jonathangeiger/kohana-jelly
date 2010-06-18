<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles boolean values.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_Boolean extends Jelly_Field
{
	/**
	 * @var  mixed  How TRUE is represented in the database
	 */
	public $true = 1;

	/**
	 * @var mixed How FALSE is represented in the database
	 */
	public $false = 0;
	
	/**
	 * @var  boolean  Null values are not allowed
	 */
	public $allow_null = FALSE;
	
	/**
	 * @var  boolean  Default value is FALSE, since NULL isn't allowed
	 */
	public $default = FALSE;
	
	/**
	 * Ensures convert_empty is not set on the field, as it prevents FALSE
	 * from ever being set on the field. 
	 *
	 * @param   array  $options 
	 */
	public function __construct($options = array())
	{
		parent::__construct($options);
		
		// Ensure convert_empty is FALSE
		if ($this->convert_empty)
		{
			throw new Kohana_Exception(':class cannot have convert_empty set to TRUE', array(
				':class' => get_class($this)));
		}
	}

	/**
	 * Validates a boolean out of the value with filter_var
	 *
	 * @param   mixed  $value
	 * @return  void
	 */
	public function set($value)
	{
		list($value, $return) = $this->_default($value);
		
		if ( ! $return)
		{
			$value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
		}
		
		return $value;
	}

	/**
	 * Returns the value as it should be represented in the database
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $value
	 * @param   boolean      $loaded
	 * @return  mixed
	 */
	public function save($model, $value, $loaded)
	{
		return ($value) ? $this->true : $this->false;
	}
}
