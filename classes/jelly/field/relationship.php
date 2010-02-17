<?php defined('SYSPATH') or die('No direct script access.');

/**
 * An abstract class that is useful for identifying which 
 * fields that are relationships.
 *
 * @package Jelly
 * @author  Jonathan Geiger
 */
abstract class Jelly_Field_Relationship extends Jelly_Field
{	
	/**
	 * Most relationship are in fact not part of the model's table
	 *
	 * @var string
	 */
	public $in_db = FALSE;
	
	/**
	 * Generally contains details of the field's relationship
	 *
	 * @var string
	 */
	public $foreign = array();
}
