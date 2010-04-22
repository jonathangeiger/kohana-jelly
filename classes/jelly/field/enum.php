<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles enumerated lists.
 *
 * A choices property is required, which is an array of valid options. If you
 * attempt to set a value that isn't a valid choice, the default will be used.
 *
 * @package  Jelly
 */
abstract class Jelly_Field_Enum extends Jelly_Field
{
	/**
	 * @var array An array of valid choices
	 */
	public $choices = array();

	/**
	 * Ensures there is a choices array set
	 *
	 * @param  array $options
	 */
	public function __construct($options = array())
	{
		parent::__construct($options);

		// Ensure we have choices to gather values from
		if (empty($this->choices))
		{
			throw new Kohana_Exception('Field_Enum must have a `choices` property set');
		}
		
		// Convert non-associative values to associative ones
		if (!arr::is_assoc($this->choices))
		{
			$this->choices = array_combine($this->choices, $this->choices);
		}
	}

	/**
	 * If $value is in the $choices array, then it is used, otherwise $default is used
	 *
	 * @param   mixed  $value
	 * @return  mixed
	 */
	public function set($value)
	{
		if ((is_int($value) OR is_string($value)) AND array_key_exists($value, $this->choices))
		{
			return $value;
		}
		else
		{
			if ($this->null AND empty($value))
			{
				return NULL;
			}

			return $this->default;
		}
	}
}
