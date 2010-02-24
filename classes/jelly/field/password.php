<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles passwords
 *
 * @package Jelly
 */
abstract class Jelly_Field_Password extends Field_String
{
	/**
	 * @var callback A valid callback to use for hashing the password or FALSE to not hash
	 */
	public $hash_with = 'sha1';
	
	/**
	 * Hashes the password on save only if it's changed
	 *
	 * @param string $model 
	 * @param string $value 
	 * @return void
	 */
	public function save($model, $value, $loaded)
	{
		if ($this->hash_with)
		{
			// Verify value has changed
			if ($model->get($this->name, FALSE) != $model->get($this->name))
			{
				$value = call_user_func($this->hash_with, $value);
			}
		}
		
		return $value;
	}
}