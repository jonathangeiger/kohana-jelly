<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles integer data-types
 *
 * @package  Jelly
 */
abstract class Jelly_Field_Integer extends Jelly_Field
{
	/**
	 * Converts the value to an integer
	 *
	 * @param   mixed  $value
	 * @return  int
	 */
	public function set($value)
	{
		if ($value === NULL OR ($this->null AND empty($value)))
		{
			return NULL;
		}

		return (int)$value;
	}
}
