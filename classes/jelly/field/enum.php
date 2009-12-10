<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Enum extends Jelly_Field
{
	protected $choices = array();
	
	public function __construct($options = array())
	{
		parent::__construct($options);
		
		// Ensure we have choices to gather values from
		if (empty($this->choices))
		{
			throw new Kohana_Exception(':model.:column must have a choices property set', array(
					':model' => get_class($this->model),
					':column' => $this->column,
				));
		}
	}
	
	public function set($value)
	{
		if (in_array($value, $this->choices))
		{
			$this->value = $value;
		}
		else
		{
			$this->value = $this->default;
		}
	}
}
