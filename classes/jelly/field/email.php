<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles email addresses
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