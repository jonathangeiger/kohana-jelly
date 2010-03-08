<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles serialized data
 *
 * @package  Jelly
 */
abstract class Jelly_Field_Serialized extends Jelly_Field
{
	/**
	 * Unserializes data as soon as it comes in.
	 *
	 * Incoming data that isn't actually serialized will not be harmed.
	 *
	 * @param   mixed  $value 
	 * @return  mixed
	 */
	public function set($value)
	{
	 	if ($return = @unserialize($value))
		{
			return $return;
		}

		return $value;
	}

	/**
	 * Saves the value as a serialized object
	 *
	 * @param   Jelly  $model
	 * @param   mixed  $value
	 * @return  string
	 */
	public function save($model, $value, $loaded)
	{
		return @serialize($value);
	}
}