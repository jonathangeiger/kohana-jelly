<?php defined('SYSPATH') or die('No direct script access.');

abstract class Jelly_Core_Field
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
	 * @var boolean Whether or not the field should be unique
	 */
	public $unique = FALSE;
	
	/**
	 * @var boolean Whether or not the field should display an input
	 */
	public $editable = TRUE;
	
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
		
		// Check as to whether we need to add 
		// some callbacks for shortcut properties
		if ($this->unique === TRUE)
		{
			$this->callbacks[] = array($this, '_is_unique');
		}
	}
	
	/**
	 * This is called after construction so that fields can finish 
	 * constructing themselves with a copy of the column it represents.
	 *
	 * @param  string  $model
	 * @param  string  $column
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
	 * @param  mixed
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
	 * @param  object $model  A copy of the current model is always passed
	 * @param  mixed  $value  The value as it's currently set in the model
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
	 * into the database.
	 *
	 * @param  Jelly  $model
	 * @param  mixed  $value
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
	public function input($prefix = 'jelly/field', $data = array())
	{
		if (!$this->editable) 
		{
			return FALSE;
		}
		
		// Get the view name
		$view = $this->_input_view($prefix);
		
		// Grant acces to all of the vars plus the field object
		$data = array_merge(get_object_vars($this), $data, array('field' => $this));
		
		// By default, a view object only needs a few defaults to display it properly
		return View::factory($view, $data);
	}	
	
	/**
	 * Used internally to allow fields to inherit input views from parent classes
	 * 
	 * @param	Jelly_Field	$class [optional]
	 * @return	string
	 */
	protected function _input_view($prefix, $field_class = NULL)
	{
		if (is_null($field_class))
		{
			$field_class = get_class($this);
		}
		
		// Determine the view name, which matches the class name
		$file = strtolower($field_class);
		
		// Could be prefixed by Jelly_Field, or just Field_
		$file = str_replace(array('jelly_field_', 'field_'), array('', ''), $file);
		
		// Allowing a prefix means inputs can be rendered from different paths
		$view = $prefix.'/'.$file;
		
		// Check we can find a view for this field type, if not inherit view from parent
		if ( ! Kohana::find_file('views', $view) 
			// Don't try going beyond this base Jelly_Field class!
			AND get_parent_class($this) !== __CLASS__)
		{
			return $this->_input_view($prefix, get_parent_class($field_class));
		}
		
		// Either we've found a suitable view or there is no suitable one so just return what it should be
		return $view;
	}
	
	/**
	 * Callback for validating that a field is unique.
	 *
	 * @param  Validate $data 
	 * @param  string $field 
	 * @return void
	 * @author Jonathan Geiger
	 * @author Woody Gilk
	 */
	public function _is_unique(Validate $data, $field)
	{
		if ($data[$field])
		{
			$count = Model::factory($this->model)
						->where($field, '=', $data[$field])
						->count();

			if ($count)
			{
				$data->error($field, 'unique');
			}
		}
	}
}
