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
	public $null = TRUE;

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
		if ($return)
		{
			$value = is_numeric($value) ? (int) $value : (string) $value;
		}
		
		return $value;
	}
}
