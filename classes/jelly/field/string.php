<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles strings
 *
 * @package  Jelly
 */
abstract class Jelly_Field_String extends Jelly_Field
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
