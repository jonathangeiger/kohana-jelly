<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles poylmorphic columns, which is typically a string that specifies
 * the model to use for the row.
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_Polymorphic extends Jelly_Field_String
{
	/**
	 * @var  boolean  This is a polymorphic field
	 */
	public $polymorphic = TRUE;
	
	/**
	 * Sets the default for the field to the model.
	 *
	 * @param   string $model 
	 * @param   string $column 
	 * @return  void
	 */
	public function initialize($model, $column)
	{
		$this->default = $model;
	}
	
	/**
	 * Casts to a string, preserving NULLs along the way
	 *
	 * @param  mixed   $value
	 * @return string
	 */
	public function set($value)
	{
		list($value, $return) = $this->_default($value);
		
		if ( ! $return)
		{
			$value = (string) $value;
		}
		
		return $value;
	}
}
