<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Password extends Jelly_Field
{
	/**
	 * @var callback A valid callback to use for hashing the password
	 */
	public $hash_with = 'sha1';
	
	/**
	 * Hashes the password on set
	 *
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		$this->value = call_user_func($this->hash_with, $value);
	}
}