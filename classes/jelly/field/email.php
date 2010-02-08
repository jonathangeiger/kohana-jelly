<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Email extends Field_String
{
	/**
	 * @var string Validate as an email
	 */
	public $rules = array(
		'email' => NULL
	);
}
