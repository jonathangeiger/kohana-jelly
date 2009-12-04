<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Float extends Jelly_Field
{
	/**
	 * @var int The number of places to round the number, NULL to forgo rounding
	 */
	protected $places = NULL;
	
	public function set($value)
	{
		$this->value = (float)$value;
		
		if ($this->places !== NULL)
		{
			$this->value = round($value, $this->places);
		}
	}
}
