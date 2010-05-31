<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles long strings
 *
 * @package  Jelly
 */
abstract class Jelly_Field_Text extends Jelly_Field
{
	/**
	 * Casts to a string, preserving NULLs along the way
	 *
	 * @param  mixed   $value
	 * @return string
	 */
	public function set($value)
	{
		if ($value === NULL OR ($this->null AND empty($value)))
		{
			return NULL;
		}

		return (string) $value;
	}
}
