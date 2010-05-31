<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Declares a field that can be joined using Jelly_Model's with()
 *
 * The with() method is expected to complete the join() clause.
 *
 * @package  Jelly
 */
interface Jelly_Field_Behavior_Joinable
{
	/**
	 * This method should add a join() and on() clause
	 * to the builder to finish the query.
	 *
	 * For examples, check out belongsTo and hasOne's implementations.
	 *
	 * @param   Jelly_Builder  $builder
	 * @return  void
	 */
	public function with($builder);
}
