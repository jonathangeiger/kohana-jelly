<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Identifier for relations
 *
 * @package  Jelly
 */
abstract class Jelly_Core_Field_Relation extends Jelly_Field
{
	/**
	 * @var  bool  Used for telling whether this returns 1 or many records
	 */
	protected $_singular = FALSE;
	
	/**
	 * Returns the ID of valid models.
	 *
	 * @param   string $value 
	 * @return  mixed
	 */
	protected function _ids($value)
	{
		if ( ! is_array($value) OR ! )
		
		foreach ($value as $model)
		{
			
		}
		
		if ($value instanceof Jelly_Model)
		{
			$value = $value->id();
		}
		
		list($value, $return) = $this->_default($value);

		// Allow only strings and integers as primary keys
		if ( ! $return)
		{
			$value = is_numeric($value) ? (int) $value : (string) $value;
		}

		return $value;
	}
	
	/**
	 * Returns the ID of valid models.
	 *
	 * @param   string $value 
	 * @return  mixed
	 */
	protected function _id($value)
	{
		if ($value instanceof Jelly_Model)
		{
			$value = $value->id();
		}
		
		list($value, $return) = $this->_default($value);

		// Allow only strings and integers as primary keys
		if ( ! $return)
		{
			$value = is_numeric($value) ? (int) $value : (string) $value;
		}

		return $value;
	}
}
