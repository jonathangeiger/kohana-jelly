<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Float extends Jelly_Field
{
	/**
	 * @var int The number of places to round the number, NULL to forgo rounding
	 */
	public $places = NULL;
	
	/**
	 * Converts to float and rounds the number if necessary
	 *
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		$this->value = (float)$value;
		
		if ($this->places !== NULL)
		{
			$this->value = round($value, $this->places);
		}
	}
}
