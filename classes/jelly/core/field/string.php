<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles strings.
 * 
 * By default, strings do not allow NULL to be set on them, because 
 * it is generally redundant to have NULLs be allowed when empty strings
 * will suffice. 
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_String extends Jelly_Field
{
	/**
	 * @var  string  Default value is a string, since we null is FALSE
	 */
	public $default = '';
	
	/**
	 * @var  boolean  Do not allow null values by default
	 */
	public $allow_null = FALSE;
	
	/**
	 * Casts to a string, preserving NULLs along the way
	 *
	 * @param  mixed   $value
	 * @return string
	 */
	public function set($value)
	{
		list($value, $return) = $this->_default($value);
		
		if ( ! $return)
		{
			$value = (string) $value;
		}
		
		return $value;
	}
}
