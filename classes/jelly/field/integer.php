<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Integer extends Jelly_Field
{
	/**
	 * Converts the value to an integer
	 *
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		$this->value = (int)$value;
	}
}
