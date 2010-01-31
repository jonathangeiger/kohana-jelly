<?php defined('SYSPATH') or die('No direct script access.');

abstract class Jelly_Field
{	
	/**
	 * @var string The model's name
	 */
	public $model;
	
	/**
	 * @var string The column's name in the database
	 */
	public $column;
	
	/**
	 * @var string A pretty name for the field
	 */
	public $label;

	/**
	 * @var string The field's name in the form
	 */
	public $name;
	
	/**
	* @var string Description of the field. Default is `''` (an empty string).
	*/
	public $description = '';
	
	/**
	* @var bool A primary key field.
	*/
	public $primary = FALSE;
	
	/**
	* @var bool The column is present in the database table. Default: TRUE
	*/
	public $in_db = TRUE;
	
	/**
	* @var bool Default value
	*/
	public $default = NULL;
	
	/**
	* @var array {@link Kohana_Validate} filters for this field.
	*/
	public $filters = array();

	    /**
	* @var array {@link Kohana_Validate} rules for this field.
	*/
	public $rules = array();

	    /**
	* @var array {@link Kohana_Validate} callbacks for this field.
	*/
	public $callbacks = array();
	
	/**
	 * Sets all options
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	public function __construct($options = array())
	{
		// Assume it's the column name
		if (is_string($options))
		{
			$this->column = $options;
		}
		else if (is_array($options))
		{
			// Just throw them into the class as public variables
			foreach ($options as $name => $value)
			{
				$this->$name = $value;
			}
		}
	}
	
	/**
	 * This is called after construction so that fields can finish 
	 * constructing themselves with a copy of the column it represents.
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	public function initialize($model, $column)
	{
		// This will come in handy for setting complex relationships
		$this->model = $model;
		
		// This is for naming form fields
		$this->name = $column;
		
		if (!$this->column)
		{
			$this->column = $column;
		}
		
		// Check for a name, because we can easily provide a default
		if (!$this->label)
		{
			$this->label = inflector::humanize($column);
		}
	}
	
	/**
	 * Sets a particular value processed according 
	 * to the class's standards.
	 *
	 * @return mixed
	 * @author Jonathan Geiger
	 **/
	public function set($value)
	{
		return (string)$value;
	}
	
	/**
	 * Returns a particular value processed according 
	 * to the class's standards.
	 *
	 * @param object $model A copy of the current model is always passed
	 * @param mixed $value The value as it's currently set in the model
	 * @return mixed
	 * @author Jonathan Geiger
	 **/
	public function get($model, $value)
	{
		return $value;
	}
	
	/**
	 * Called just before saving if the field is $in_db, and just after if it's not.
	 * 
	 * If $in_db, it is expected to return a value suitable for insertion 
	 * into the database. If !$in_db, it is expected to return a status boolean.
	 *
	 * @return mixed
	 * @author Jonathan Geiger
	 */
	public function save($model, $value) 
	{
		return $value;
	}
	
	/**
	 * Displays the particular field as a form item
	 *
	 * @param string $prefix The prefix to put before the filename to be rendered
	 * @return View
	 * @author Jonathan Geiger
	 **/
	public function input($prefix = NULL, $data = array())
	{
		// Determine the view name, which matches the class name
		$file = strtolower(get_class($this));
		
		// Could be prefixed by Jelly_Field, or just Field_
		$file = str_replace(array('jelly_field_', 'field_'), array('', ''), $file);
		
		// Allowing a prefix means inputs can be rendered from different paths
		$view = $prefix.'/'.$file;
		
		// Grant acces to all of the vars plus the field object
		$data = array_merge(get_object_vars($this), $data, array('field' => $this));
		
		// By default, a view object only needs a few defaults to display it properly
		return View::factory($view, $data);
	}	
	
} // END abstract class Resource_Field
