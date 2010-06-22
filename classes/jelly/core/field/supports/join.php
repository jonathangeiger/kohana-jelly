<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Declares a field that can be joined using Jelly_Builder's auto_join()
 *
 * The join() method is expected to add a join() and on() clause to the
 * builder to complete the join.
 *
 * @package  Jelly
 */
interface Jelly_Core_Field_Supports_Join
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
	public function join(Jelly_Builder $builder);
}
