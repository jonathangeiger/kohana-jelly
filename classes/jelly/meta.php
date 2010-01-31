<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Defines meta data for a particular model
 *
 * @package default
 * @author Jonathan Geiger
 */
class Jelly_Meta
{			
	/**
	 * @var string The database key to use for connection
	 */
	public $db = 'default';
	
	/**
	 * @var string The table this model represents
	 */
	public $table = '';
	
	/**
	 * @var string The name of the model
	 */
	public $model = '';
	
	/**
	 * @var string The primary key
	 */
	public $primary_key = '';
	
	/**
	 * @var string The title key
	 */
	public $name_key = 'name';
	
	/**
	 * @var array An array of ordering options for selects
	 */
	public $sorting = array();
	
	/**
	 * @var array A map to the resource's data and how to process each column.
	 */
	public $fields = array();
	
	/**
	 * Constructor
	 *
	 * @param string $model 
	 * @author Jonathan Geiger
	 */
	public function __construct($model)
	{
		$this->model = $model;
		
		// Table should be a sensible default
		if (empty($this->table))
		{
			$this->table = inflector::plural($model);
		}
	}	
}