<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles "slugs".
 *
 * Slugs are not validated, but rather automatically converted to a valid slug.
 *
 * A valid slug consists of lowercase alphanumeric characters, plus
 * underscores, dashes, and forward slashes.
 *
 * @package  Jelly
 */
abstract class Jelly_Field_Slug extends Field_String
{
	/**
	 * Converts a slug to value valid for a URL.
	 *
	 * We could validate it by setting a rule, but for the most part, who cares?
	 *
	 * @param   mixed  $value
	 * @return  mixed
	 */
	public function set($value)
	{
		if ($value === NULL OR ($this->null AND empty($value)))
		{
			return NULL;
		}

		// Only allow slashes, dashes, and lowercase letters
		$value = preg_replace('/[^a-z0-9-\/]/', '-', strtolower($value));

		// Strip multiple dashes
		$value = preg_replace('/-{2,}/', '-', $value);

		// Trim an ending or starting dashes
		$value = trim($value, '-');

		return $value;
	}
}
