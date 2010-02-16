<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Primary extends Jelly_Field
{
	/**
	 * @var boolean Defaults primary keys to primary
	 */
	public $primary = TRUE;
	
	/**
	 * Converts numeric IDs to ints
	 *
	 * @param  mixed $value 
	 * @return int|string
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		if (is_numeric($value))
		{
			return (int)$value;
		}
		else
		{
			return (string)$value;
		}
	}
}