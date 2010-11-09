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
	 * @see Jelly_Field::value
	 */
	public function value($model, $value)
	{
		list($value, $return) = $this->_default($value);
		
		if ( ! $return)
		{
		 	if (is_string($value) AND ($new_value = @unserialize($value)) !== FALSE)
			{
				$value = $new_value;
			}
		}
		
		return $value;
	}

	/**
	 * @see Jelly_Field::save
	 */
	public function save($model, $value)
	{
		if ($this->allow_null AND $value === NULL)
		{
			return NULL;
		}
		
		return @serialize($value);
	}
}
