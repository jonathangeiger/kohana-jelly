<?php defined('SYSPATH') or die('No direct script access.');


abstract class Jelly_Field
{
	/**
	 * @var object The model this field is related to
	 */
	protected $model;
	
	/**
	 * @var string The column's name in the database
	 */
	protected $column;
	
	/**
	 * @var mixed The internally represented value
	 */
	protected $value;
	
	/**
	 * @var string A pretty name for the field
	 */
	protected $label;
	
	/**
	* @var string Description of the field. Default is `''` (an empty string).
	*/
	protected $description = '';
	
	/**
	* @var bool A primary key field. Multiple primary keys (composite key) can be specified. Default is `FALSE`.
	*/
	protected $primary = FALSE;
	
	/**
	* @var bool The column is present in the database table. Default: TRUE
	*/
	protected $in_db = TRUE;
	
	/**
	* @var bool Allow `empty()` values to be used. Default is `FALSE`.
	*/
	protected $empty = FALSE;
	
	/**
	* @var array {@link Kohana_Validate} filters for this field.
	*/
	protected $filters = array();

	    /**
	* @var array {@link Kohana_Validate} rules for this field.
	*/
	protected $rules = array();

	    /**
	* @var array {@link Kohana_Validate} callbacks for this field.
	*/
	protected $callbacks = array();
	
	/**
	 * Sets all options
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	public function __construct($options = array())
	{
		// Just throw them into the class as public variables
		foreach ($options as $name => $value)
		{
			$this->$name = $value;
		}
	}
	
	/**
	 * Retrieves protected properties as methods
	 *
	 * @return void
	 * @author Jonathan Geiger
	 **/
	public function __call($method, $args)
	{
		if (isset($this->$method))
		{
			return $this->$method;
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
	 * Displays the particular field as a form item
	 *
	 * @return View
	 * @author Jonathan Geiger
	 **/
	public function input()
	{
		// Determine the view name, which matches the class name
		$view = 'fields/' . str_replace('field_', '', strtolower(get_class($this)));
		
		// By default, a view object only needs a few defaults to display it properly
		return View::factory($view, get_object_vars($this));
	}	
	
} // END abstract class Resource_Field
