<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Handles date.
 *
 * @package    Jelly
 * @author     Roman Shamritskiy
 */
class Jelly_Field_Date extends Field_String {

	/**
	 * Adds an date validation rule if it doesn't already exist.
	 *
	 * @param   string  model
	 * @param   string  column
	 * @return  void
	 **/
	public function initialize($model, $column)
	{
		parent::initialize($model, $column);

		if ( ! isset($this->rules['date']))
		{
			$this->rules[] = array('date', array(':value'));
		}
	}
	
	/**
	 * Casts to a date string, preserving NULLs along the way.
	 *
	 * @param   mixed   value
	 * @return  string
	 */
	public function set($value)
	{
	    if ( ! empty($value))
	    {
	        $value = Date::formatted_time($value, 'Y-m-d');
	    }
	    return parent::set($value);
	}

} // End Jelly_Core_Field_Email