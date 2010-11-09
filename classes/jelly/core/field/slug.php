<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles "slugs".
 *
 * Slugs are automatically converted.
 *
 * A valid slug consists of lowercase alphanumeric characters, plus
 * underscores, dashes, and forward slashes.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_Slug extends Jelly_Field_String
{
	/**
	 * @see Jelly_Field::value
	 */
	public function value($model, $value)
	{
		list($value, $return) = $this->_default($value);
		
		if ( ! $return)
		{
			// Only allow dashes, and lowercase letters
			$value = preg_replace('/[^a-z0-9-]/', '-', strtolower($value));

			// Strip multiple dashes
			$value = preg_replace('/-{2,}/', '-', $value);

			// Trim an ending or starting dashes
			$value = trim($value, '-');
		}

		return $value;
	}
}
