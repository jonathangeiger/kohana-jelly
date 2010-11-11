<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles "slugs", typically used in URLs and other identifiers. 
 *
 * A valid slug consists of lowercase alphanumeric characters, plus
 * underscores, dashes, and forward slashes.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_Slug extends Jelly_Field_String
{
	/**
	 * @var  string  An array of regexps passed to preg_replace
	 */
	public $replace = array(
		// Replace everything but dashes and alphanumeric characters with a dash
		array('/[^a-zA-Z0-9-]/', '-'),
		// Strip multiple dashes
		array('/-{2,}/', ''),
	);
	
	/**
	 * Automatically converts the value to a slug.
	 * 
	 * @return   string
	 */
	public function set($model, $value)
	{
		list($value, $return) = $this->_default($value);
		
		if ( ! $return)
		{
			foreach ($this->replace as $regexp)
			{
				$value = preg_replace($regexp[0], $regexp[1], $value);
			}

			// Trim any ending or starting dashes
			$value = trim($value, '-');
		}

		return $value;
	}
}
