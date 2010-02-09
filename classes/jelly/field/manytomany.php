<?php defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_ManyToMany extends Jelly_Field implements Jelly_Field_Relationship
{	
	public $in_db = FALSE;
	
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
			$this->through_columns[0] = inflector::singular($model).'_id';
			$this->through_columns[1] = inflector::singular($this->foreign_model).'_id';
		}	
		
		if (empty($this->through_model))
		{
			// Find the join table based on the two model names pluralized, 
			// sorted alphabetically and with an underscore separating them
			$this->through_model = array(
				inflector::plural($this->foreign_model), 
				inflector::plural($model)
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
		$return = array();
		
		// Handle Database Results
		if (is_object($value))
		{
			foreach($value as $row)
			{
				$return[] = $row->id();
			}
		}
		else
		{
			$return = (array)$value;
		}
		
		return $return;
	}
	
	public function get($model, $value)
	{
		return Jelly::factory($this->foreign_model)
				->where($this->foreign_column, 'IN', $this->in($model));
	}
	
	public function save($model, $value)
	{
		// Find all current records so that we can calculate what's changed
		$in = $this->in($model, TRUE);
				
		// Grab all of the actual columns
		$through_table = Jelly_Meta::table($this->through_model);
		$through_columns = array(
			Jelly_Meta::column($this->through_model.'.'.$this->through_columns[0], TRUE),
			Jelly_Meta::column($this->through_model.'.'.$this->through_columns[1], TRUE),
		);
		
		// Find old relationships that must be deleted
		if ($old = array_diff($in, $value))
		{
			DB::delete($through_table)
				->where($through_columns[0], '=', $model->id())
				->where($through_columns[1], 'IN', $old)
				->execute(Jelly_Meta::get($model, 'db'));
		}

		// Find new relationships that must be inserted
		if ($new = array_diff($value, $in))
		{
			foreach ($new as $new_id)
			{
				DB::insert($through_table, $through_columns)
					->values(array($model->id(), $new_id))
					->execute(Jelly_Meta::get($model, 'db'));
			}
		}
	}
	
	/**
	 * Returns whether or not this field has another model
	 *
	 * @param string $model 
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function has($model, $ids)
	{
		$in = $this->in($model, TRUE);
		
		foreach ($ids as $id)
		{
			if (!in_array($id, $in))
			{
				return FALSE;
			}
		}
		
		return TRUE;
	}
		
	protected function in($model, $as_array = FALSE)
	{
		// Grab all of the actual columns
		$through_table = Jelly_Meta::table($this->through_model);
		$through_columns = array(
			Jelly_Meta::column($this->through_model.'.'.$this->through_columns[0], FALSE),
			Jelly_Meta::column($this->through_model.'.'.$this->through_columns[1], FALSE),
		);
						
		if (!$as_array)
		{
			return DB::Select()
					->select($through_columns[1])
					->from($through_table)
					->where($through_columns[0], '=', $model->id());
		}
		else
		{
			return DB::Select()
					->select($through_columns[1])
					->from($through_table)
					->where($through_columns[0], '=', $model->id())
					->execute(Jelly_Meta::get($model, 'db'))
					->as_array(NULL, $through_columns[1]);
		}
	}
	
	/**
	 * Adds the "ids" variable to the view data
	 *
	 * @param string $prefix
	 * @param string $data
	 * @return void
	 * @author Jonathan Geiger
	 */
	public function input($prefix = 'jelly/field', $data = array())
	{
		$data['ids'] = $this->in($data['model'], TRUE);
		return parent::input($prefix, $data);
	}
}