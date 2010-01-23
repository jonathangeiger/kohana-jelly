<?php defined('SYSPATH') or die('No direct script access.');

abstract class Jelly_Field
{
	/**
	 * @var object The model this field is attached to
	 */
	public $model;
	
	/**
	 * @var string The column's name in the database
	 */
	public $column;
	
	/**
	 * @var mixed The internally represented value
	 */
	public $value;
	
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
	 * constructing themselves with a copy of the model and column 
	 * it represents.
	 * 
	 * Requiring all _map declarations to pass $this, and the column name 
	 * to the constructor of each field is just repetitive. The application
	 * can do this by itself, even if it is a bit unorthodox.
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	public function initialize($model, $column)
	{
		// This will come in handy for setting complex relationships
		$this->model = $model;
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
		$this->value = (string)$value;
	}
	
	/**
	 * Returns a particular value processed according 
	 * to the class's standards.
	 *
	 * @return mixed
	 * @author Jonathan Geiger
	 **/
	public function get()
	{
		return $this->value;
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
	public function save($loaded) 
	{
		return $this->value;
	}
	
	/**
	 * Displays the particular field as a form item
	 *
	 * @param string $prefix The prefix to put before the filename to be rendered
	 * @return View
	 * @author Jonathan Geiger
	 **/
	public function input($prefix = 'jelly/field', $data = array())
	{
		// Determine the view name, which matches the class name
		$file = strtolower(get_class($this));
		
		// Could be prefixed by Jelly_Field, or just Field_
		$file = str_replace(array('jelly_field_', 'field_'), array('', ''), $file);
		
		// Allowing a prefix means inputs can be rendered from different paths
		$view = $prefix.'/'.$file;
		
		// Grant acces to all of the vars plus the field object
		$data = array_merge($data, get_object_vars($this), array('field' => $this));
		
		// By default, a view object only needs a few defaults to display it properly
		return View::factory($view, $data);
	}	
	
} // END abstract class Resource_Field
