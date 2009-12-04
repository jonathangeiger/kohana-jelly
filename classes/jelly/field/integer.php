<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Integer extends Jelly_Field
{
	public function set($value)
	{
		$this->value = (int)$value;
	}
}
