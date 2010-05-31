<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Interface for a field that can "have" other models.
 *
 * This is used by the Jelly_Model's has() method,
 *
 * @package  Jelly
 */
interface Jelly_Field_Behavior_Haveable
{
	/**
	 * This method should return a boolean that indicates whether or
	 * not the $model passed is related to the $ids passed.
	 *
	 * An array of $ids is always passed. For fields that are a 1:1
	 * mapping, how they deal having more than 1 primary key passed
	 * is undefined, though it is recommended to simply use the first
	 * id in the array.
	 *
	 * The $model should not be modified by the time this method
	 * has finished.
	 *
	 * @param   Jelly_Model  $model
	 * @param   array        $ids
	 * @return  boolean
	 */
	public function has($model, $ids);
}
