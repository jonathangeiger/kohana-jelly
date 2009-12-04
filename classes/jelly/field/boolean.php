<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Boolean extends Jelly_Field
{	
	public function set($value)
	{
		$this->value = (bool)$value;
	}
}
