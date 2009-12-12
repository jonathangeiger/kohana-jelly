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
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		if (is_numeric($value))
		{
			$this->value = (int)$value;
		}
		else
		{
			parent::set($value);
		}
	}
}