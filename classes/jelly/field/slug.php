<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Slug extends Field_String
{
	/**
	 * Converts a slug to value valid for a URL.
	 * 
	 * We could validate it by setting a rule, but for the most part, who cares?
	 *
	 * @param string $value 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		// Only allow slashes, dashes, and lowercase letters
		$value = preg_replace('/[^a-z0-9-\/]/', '-', strtolower($value));
		
		// Strip multiple dashes
		$value = preg_replace('/-{2,}/', '-', $value);
		
		// Trim an ending or starting dashes
		$value = trim(strtolower($value), '-');
		
		return $value;
	}
}
