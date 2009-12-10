<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Password extends Jelly_Field
{
	protected $hash_with = 'sha1';
	
	public function set($value)
	{
		$this->value = call_user_func($this->hash_with, $value);
	}
}