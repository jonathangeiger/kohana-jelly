<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles enumerated lists.
 *
 * A choices property is required, which is an array of valid options. If you
 * attempt to set a value that isn't a valid choice, the default will be used.
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

		// Ensure we have choices to gather values from
		if (empty($this->choices))
		{
			throw new Kohana_Exception(':class must have a `choices` property set', array(
				':class' => get_class($this)));
		}
		
		// Set allow_null to TRUE if we find a NULL value
		if (in_array(NULL, $this->choices))
		{
			$this->allow_null = TRUE;
		}
		// We're allowing NULLs but the value isn't set. Create it so validation won't fail.
		else if ($this->allow_null)
		{
			array_unshift($this->choices, NULL);
		}
		
		reset($this->choices);
		
		// Set the default value from the first choice in the array
		if ( ! array_key_exists('default', $options))
		{
			$this->default = current($this->choices);
		}
		
		// Add a rule to validate that the value is proper
		$this->rules += array('in_array' => array($this->choices));
	}
}
