<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles serialized data.
 *
 * When set, the field attempts to unserialize the data into it's
 * actual PHP representation. When the model is saved, the value
 * is serialized back and saved as a string into the column.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_Serialized extends Jelly_Field
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
		list($value, $return) = $this->_default($value);
		
		if ( ! $return)
		{
		 	if (($new_value = @unserialize($value)) !== FALSE)
			{
				$value = $new_value;
			}
		}
		
		return $value;
	}

	/**
	 * Saves the value as a serialized string
	 *
	 * @param   Jelly  $model
	 * @param   mixed  $value
	 * @return  string
	 */
	public function save($model, $value, $loaded)
	{
		if ($this->allow_null AND $value === NULL)
		{
			return NULL;
		}
		
		return @serialize($value);
	}
}
