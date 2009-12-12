<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_ManyToMany extends Jelly_Field
{	
	/**
	 * The columns in the through table that this is referencing.
	 * 
	 * The first element is the column for this model, and the second
	 * is the column for the foreign model.
	 *
	 * @var string
	 */
	public $through_columns = array();
	
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
	public $through_model;
	
	/**
	 * The column that represents the foreign model's primary key
	 *
	 * @var string
	 */
	public $foreign_column;
	
	/**
	 * The final foreign model's name.
	 * 
	 * @var string
	 */
	public $foreign_model;
		
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
			$this->foreign_column = 'id';
		}
		
		if (empty($this->through_columns))
		{
			$this->through_columns[0] = inflector::singular($model->model_name()).'_id';
			$this->through_columns[1] = inflector::singular($this->foreign_model).'_id';
		}	
		
		if (empty($this->through_model))
		{
			// Find the join table based on the two model names pluralized, 
			// sorted alphabetically and with an underscore separating them
			$this->through_model = array(
				inflector::plural($this->foreign_model), 
				inflector::plural($model->model_name())
			);
			
			// Sort
			sort($this->through_model);
			
			// Bring them back together
			$this->through_model = implode('_', $this->through_model);
		}
		
		parent::initialize($model, $column);
	}
	
	public function set($value)
	{
		// Can be set in only one go
		$this->value = array();
		
		// Handle Database Results
		if (is_object($value))
		{
			foreach($value as $row)
			{
				$this->value[] = $row->id();
			}
		}
		else
		{
			$this->value = (array)$value;
		}
	}
	
	public function get($object = TRUE)
	{
		// Only return the actual value
		if (!$object)
		{
			return $this->value;
		}
				
		return Jelly::factory($this->foreign_model)
				->where($this->foreign_column, 'IN', $this->in());
	}
	
	public function save($id)
	{
		// Find all current records so that we can calculate what's changed
		$in = $this->in();
		
		// Alias tables and columns
		$through_table = $this->model->alias(NULL, $this->through_model);
		$through_columns = array(
			$this->model->alias($this->through_columns[0]),
			$this->model->alias($this->through_colums[1], $this->through_model),
		);
		
		// Find old relationships that must be deleted
		if ($old = array_diff($in, $this->value))
		{
			DB::delete($through_table)
				->where($through_columns[0], '=', $this->model->id())
				->where($through_columns[1], 'IN', $old)
				->execute($this->model->db());
		}

		// Find new relationships that must be inserted
		if ($new = array_diff($this->value, $in))
		{
			foreach ($new as $new_id)
			{
				DB::insert($through_table, $through_columns)
					->values(array($id, $new_id))
					->execute($this->model->db());
			}
		}
	}
		
	protected function in()
	{
		// Grab all of the actual columns
		$through_table = $this->model->alias(NULL, $this->through_model);
		$through_columns = array(
			$this->model->alias($this->through_columns[0]),
			$this->model->alias($this->through_colums[1], $this->through_model),
		);
		
		return DB::Select()
				->select($through_columns[1])
				->from($through_table)
				->where($through_columns[0], '=', $this->model->id())
				->execute($this->model->db())
				->as_array(NULL, $through_column[1]);
	}
}