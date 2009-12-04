<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_ManyToMany extends Jelly_Field_ForeignKey
{	
	/**
	 * The name of the model or table to go through. If empty it is 
	 * the pluralized names of the two model's names combined 
	 * alphabetically with an underscore.
	 * 
	 * If the name is a valid model, the model's table name will be
	 * used, otherwise it will be assumed to be a table.
	 * 
	 * @var string
	 */
	protected $through;
	
	/**
	 * Overrides the initialize to automatically provide the column name
	 *
	 * @param string $model 
	 * @param string $column 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function initialize($model, $column)
	{
		if (empty($this->foreign_model))
		{
			$this->foreign_model = inflector::singular($column);
		}
		
		if (empty($this->foreign_column))
		{
			$this->foreign_column = $this->foreign_model.'_id';
		}
		
		if (empty($this->through))
		{
			$this->through = array(inflector::plural($this->foreign_model), inflector::plural($this->model->name));
			sort($this->through);
			$this->through = implode('_', $this->through);
		}
		else
		{
			// Check to see if this is a model
			if (Kohana::autoload($this->through))
			{
				$this->through = Jelly::factory($this->through)->table_name();
			}
		}
		
		// Column is set and won't be overridden
		parent::initialize($model, $column);
	}
	
	public function get($object = TRUE)
	{
		// Only return the actual value
		if (!$object)
		{
			return NULL;
		}
		
		// Return a real object
		$in = DB::Select()
				->from($this->through)
				->where($this->model->alias($this->foreign_column))
		return Jelly::factory($this->foreign_model)
				->where($this->foreign_column, '=', $this->model->id())
				->load();
	}
}