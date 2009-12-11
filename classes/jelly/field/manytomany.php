<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_ManyToMany extends Jelly_Field_ForeignKey
{	
	/**
	 * The column that represents this field's model's primary key in the 'through' or 'join' table
	 *
	 * @var string
	 */
	protected $column;
	
	/**
	 * The column that represents the foreign model's primary key in the 'through' or 'join' table
	 * 
	 * @var string
	 */
	protected $through_column;
	
	/**
	 * The column that represents the foreign model's primary key
	 *
	 * @var string
	 */
	protected $foreign_column;
	
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
	protected $through_model;
	
	/**
	 * The final foreign model's name.
	 * 
	 * @var string
	 */
	protected $foreign_model;
		
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
		if (empty($this->column))
		{
			$this->column = inflector::singular($model->model_name()).'_id';
		}
		
		if (empty($this->foreign_model))
		{
			$this->foreign_model = inflector::singular($column);
		}
		
		if (empty($this->foreign_column))
		{
			$this->foreign_column = 'id';
		}
		
		if (empty($this->through_column))
		{
			$this->through_column = $this->foreign_model.'_id';
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
		else
		{
			// Check to see if this is a model
			if (Kohana::auto_load('model/'.str_replace('_', '/', $this->through_model)))
			{
				$through = Jelly::factory($this->through_model);
				$this->through_model = $through->table_name();
				$this->column = $through->alias($this->column);
				$this->through_column = $through->alias($this->through_column);
			}
		}
		
		// Column is set and won't be overridden
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
		
		$foreign_model = Jelly::Factory($this->foreign_model);
		
		// Return a real object
		$in = DB::Select()
				->select($this->through_column)
				->from($this->through_model)
				->where($this->model->alias($this->column), '=', $this->model->id());
				
		return $foreign_model
				->where($this->foreign_column, 'IN', $in);
	}
	
	public function save($id)
	{
		$in = DB::Select()
				->select($this->through_column)
				->from($this->through_model)
				->where($this->model->alias($this->column), '=', $this->model->id())
				->execute($this->model->db())
				->as_array(NULL, $this->through_column);
				
		// Find old relationships that must be deleted
		if ($old = array_diff($in, $this->value))
		{
			DB::delete($this->through_model)
				->where($this->column, '=', $this->model->id())
				->where($this->through_column, 'IN', $old)
				->execute($this->model->db());
		}

		// Find new relationships that must be inserted
		if ($new = array_diff($this->value, $in))
		{
			foreach ($new as $new_id)
			{
				DB::insert($this->through_model, array($this->column, $this->through_column))
					->values(array($id, $new_id))
					->execute($this->model->db());
			}
		}
	}
}