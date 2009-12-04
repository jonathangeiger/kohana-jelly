<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Primary extends Jelly_Field
{
	protected $primary = TRUE;
	
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