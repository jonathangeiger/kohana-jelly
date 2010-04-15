<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles passwords by automatically hashing them before they're
 * saved to the database.
 *
 * It is important to note that a new password is hashed only when the
 * model is saved. This means you must save a model if you want to
 * compare hashes of two passwords.
 *
 * @package  Jelly
 */
abstract class Jelly_Field_Password extends Field_String
{
	/**
	 * @var  callback  A valid callback to use for hashing the password or FALSE to not hash
	 */
	public $hash_with = 'sha1';

	/**
	 * Hashes the password on save only if it's changed
	 *
	 * @param   string  $model
	 * @param   string  $value
	 * @return  string
	 */
	public function save($model, $value, $loaded)
	{
		if ($this->hash_with)
		{
			// Verify value has changed
			if ($model->changed($this->name))
			{
				$value = call_user_func($this->hash_with, $value);
			}
		}

		return $value;
	}
}
