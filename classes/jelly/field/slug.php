<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Slug extends Jelly_Field
{
	public $filters = array(
		'Field_Slug::format' => NULL
	);
	
	/**
	 * Converts a slug to value valid for a URL.
	 * 
	 * We could validate it by setting a rule, but for the most part, who cares?
	 *
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public static function format($value)
	{
		// Only allow slashes, dashes, and lowercase letters
		$value = trim(strtolower($value));
		$value = str_replace(' ', '-', $value);
		$value = preg_replace('/[^a-z0-9-\/]/', '', $value);
		
		// Strip multiple dashes
		$value = preg_replace('/-{2,}/', '-', $value);
		
		return $value;
	}
}
