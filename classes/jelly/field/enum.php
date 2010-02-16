<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Enum extends Jelly_Field
{
	/**
	 * @var array An array of valid choices
	 */
	public $choices = array();
	
	/**
	 * Ensures there is a choices array set
	 *
	 * @param  array $options 
	 * @author Jonathan Geiger
	 */
	public function __construct($options = array())
	{
		parent::__construct($options);
		
		// Ensure we have choices to gather values from
		if (empty($this->choices))
		{
			throw new Kohana_Exception('Field_Enum must have a `choices` property set');
		}
	}
	
	/**
	 * If $value is in the $choices array, then it is used, otherwise $default is used
	 *
	 * @param  string $value 
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function set($value)
	{
		if (in_array($value, $this->choices))
		{
			return $value;
		}
		else
		{
			return $this->default;
		}
	}
}
