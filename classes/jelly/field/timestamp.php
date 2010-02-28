<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles timestamps
 *
 * @package Jelly
 */
abstract class Jelly_Field_Timestamp extends Jelly_Field
{
	/**
	 * @var boolean Whether or not to automatically set now() on creation
	 */
	public $auto_now_create = FALSE;
	
	/**
	 * @var boolean Whether or not to automatically set now() on update
	 */
	public $auto_now_update = FALSE;
	
	/**
	 * @var string A date formula representing the time in the database
	 */
	public $format = NULL;
	
	/**
	 * @var string A pretty format used for representing the date to users
	 */
	public $pretty_format = 'r';
	
	/**
	 * Converts the time to a UNIX timestamp
	 *
	 * @param  mixed  $value 
	 * @return mixed
	 */
	public function set($value)
	{
		if (FALSE !== strtotime($value))
		{
			return strtotime($value);
		}
		// Already a timestamp?
		elseif (is_numeric($value))
		{
			return (int) $value;
		}
		
		return $value;
	}
	
	/**
	 * Automatically creates or updates the time and 
	 * converts it, if necessary
	 *
	 * @param  Jelly $model
	 * @param  mixed $value
	 * @return mixed
	 */
	public function save($model, $value, $loaded)
	{
		if (( ! $loaded AND $this->auto_now_create) OR ($loaded AND $this->auto_now_update))
		{
			$value = time();
		}
		
		// Convert if necessary
		if ($this->format)
		{
			$value = date($this->format, $value);
		}
		
		return $value;
	}
}