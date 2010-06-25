<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Interface for a field that can "have" other models.
 *
 * This is used by the Jelly_Model's has() method,
 *
 * @package  Jelly
 */
interface Jelly_Core_Field_Supports_Has
{
	/**
	 * This method should return a boolean that indicates whether or
	 * not the $model passed is related to the $models passed.
	 *
	 * $models can be any number of values, including a Jelly_Collection,
	 * an array of models, or a single model. The implementation should
	 * handle all forms.
	 *
	 * @param   Jelly_Model  $model
	 * @param   mixed        $models
	 * @return  boolean
	 */
	public function has($model, $models);
}
