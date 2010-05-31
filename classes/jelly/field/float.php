<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles floats.
 *
 * You can specify an optional places property to
 * round the value to the specified number of places.
 *
 * @package  Jelly
 */
abstract class Jelly_Field_Float extends Jelly_Field
{
	/**
	 * @var int The number of places to round the number, NULL to forgo rounding
	 */
	public $places = NULL;

	/**
	 * Converts to float and rounds the number if necessary
	 *
	 * @param   mixed  $value
	 * @return  mixed
	 */
	public function set($value)
	{
		if ($value === NULL OR ($this->null AND empty($value)))
		{
			return NULL;
		}

		$value = (float)$value;

		if (is_numeric($this->places))
		{
			$value = round($value, $this->places);
		}

		return $value;
	}
}
