<?php defined('SYSPATH') or die('No direct script access.');

abstract class Jelly_Field_ForeignKey extends Jelly_Field
{	
	protected $in_db = FALSE;
	
	/**
	 * @var string The name of the model that this field is referencing
	 */
	protected $foreign_model;
	
	/**
	 * @var string The name of the column in the foreign model that this field is referencing
	 */
	protected $foreign_column;
}
