<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Boolean extends Jelly_Field
{	
	/**
	 * Values for true and false in the database
	 *
	 * @var string
	 */
	protected $true_in_db = 1;
	protected $false_in_db = 0;
	
	public function set($value)
	{
		$this->value = (bool)$value;
	}
	
	public function create()
	{
		return ($this->value) ? $this->true_in_db : $this->false_in_db;
	}
}
