<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles enumerated lists.
 *
 * A choices property is required, which is an array of valid options. 
 * It is perfectly acceptable to set a value that isn't in your choices
 * array. If you don't wish to, however, you should set a validation rule
 * to ensure it doesn't happen.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_Enum extends Jelly_Field_String
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

		if (empty($this->choices))
		{
			throw new Kohana_Exception(':class must have a `choices` property set', array(
				':class' => get_class($this)));
		}
		
		if (in_array(NULL, $this->choices))
		{
			$this->allow_null = TRUE;
		}
		else if ($this->allow_null)
		{
			array_unshift($this->choices, NULL);
		}
		
		reset($this->choices);
		
		if ( ! array_key_exists('default', $options))
		{
			$this->default = current($this->choices);
		}
		
		if ( ! arr::is_assoc($this->choices))
		{
			$this->choices = array_combine($this->choices, $this->choices);
		}
	}
}
