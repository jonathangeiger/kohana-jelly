<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Handles expression fields, which allow an arbitrary database 
 * expression as the field column.
 * 
 * For example, if you always wanted the field to return a concatenation
 * of two columns in the database, you can do this:
 * 
 * 'field' => new Field_Expression('array(
 *       'column' => DB::expr("CONCAT(`first_name`, ' ', `last_name`)")
 * ))
 * 
 * Keep in mind that aliasing breaks down in Database_Expressions.
 *
 * @package  Jelly
 */
abstract class Jelly_Field_Expression extends Jelly_Field
{
	/**
	 * @var  boolean  Expression fields are not in_db
	 */
	public $in_db = FALSE;
}
