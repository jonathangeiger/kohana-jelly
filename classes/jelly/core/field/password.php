<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles passwords by automatically hashing them before they're
 * saved to the database.
 *
 * It is important to note that a new password is hashed in a validation
 * callback. This gives you a chance to validate the password, and have it
 * be hashed after validation.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_Password extends Jelly_Field_String
{
	/**
	 * @var  callback  A valid callback to use for hashing the password or FALSE to not hash
	 */
	public $hash_with = 'sha1';

	/**
	 * Hashes the password only if it's changed
	 *
	 * @param   Jelly_Validator  $validate 
	 * @param   Jelly_Model      $model 
	 * @return  void
	 */
	public function save($model, $value)
	{
		if ($this->hash_with)
		{
			return call_user_func($this->hash_with, $value);
		}
	}
}
