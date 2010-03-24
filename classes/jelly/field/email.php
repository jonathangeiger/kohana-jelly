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
	 * @var  string  Validate as an email
	 */
	public $rules = array(
		'email' => NULL
	);
}