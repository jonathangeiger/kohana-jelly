<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles email addresses.
 *
 * No special processing is added for this field other
 * than a validation rule that ensures the email address is valid.
 *
 * @package  Jelly
 */
abstract class Jelly_Field_Email extends Field_String
{
	/**
	 * Adds an email validation rule if it doesn't already exist.
	 *
	 * @param   string  $model
	 * @param   string  $column
	 * @return  void
	 **/
	public function initialize($model, $column)
	{
		parent::initialize($model, $column);

		$this->rules += array('email' => NULL);
	}
}
