<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles primary keys.
 *
 * Currently, a primary key can be an integer or a string.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_Primary extends Jelly_Field
{
	/**
	 * @var  boolean  Defaults primary keys to primary
	 */
	public $primary = TRUE;
	
	/**
	 * @var  boolean  Default to converting empty values to NULL so keys are auto-incremented properly
	 */
	public $allow_null = TRUE;
	
	/**
	 * @var  int  Default is NULL
	 */
	public $default = NULL;
	
	/**
	 * Ensures allow_null is not set to FALSE on the field, as it prevents 
	 * proper auto-incrementing of a primary key.
	 *
	 * @param   array  $options 
	 */
	public function __construct($options = array())
	{
		parent::__construct($options);
		
		// Ensure allow_null is TRUE
		if ( ! $this->allow_null)
		{
			throw new Kohana_Exception(':class cannot have allow_null set to FALSE', array(
				':class' => get_class($this)));
		}
	}

	/**
	 * Converts numeric IDs to ints
	 *
	 * @param   mixed  $value
	 * @return  int|string
	 */
	public function set($value)
	{
		list($value, $return) = $this->_default($value);
		
		// Allow only strings and integers as primary keys
		if ( ! $return)
		{
			$value = is_numeric($value) ? (int) $value : (string) $value;
		}
		
		return $value;
	}
}
