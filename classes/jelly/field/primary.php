<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles primary keys.
 *
 * Currently, a primary key can be an integer or a string.
 *
 * @package  Jelly
 */
abstract class Jelly_Field_Primary extends Jelly_Field
{
	/**
	 * @var  boolean  Defaults primary keys to primary
	 */
	public $primary = TRUE;

	/**
	 * Converts numeric IDs to ints
	 *
	 * @param   mixed  $value
	 * @return  int|string
	 */
	public function set($value)
	{
		if ($value)
		{
			if (is_numeric($value))
			{
				return (int)$value;
			}
			else
			{
				return (string)$value;
			}
		}
		else
		{
			// Empty values should be null so
			// they are auto-incremented properly
			return NULL;
		}
	}
}
