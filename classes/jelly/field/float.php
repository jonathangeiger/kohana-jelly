<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles floats
 *
 * @package Jelly
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
	 * @param  mixed  $value
	 * @return mixed
	 */
	public function set($value)
	{
		$value = (float)$value;
		
		if ($this->places !== NULL)
		{
			$value = round($value, $this->places);
		}
		
		return $value;
	}
}
