<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Email extends Jelly_Field
{
	/**
	 * @var string Validate as an email
	 */
	public $rules = array(
		'email' => NULL
	);
}
