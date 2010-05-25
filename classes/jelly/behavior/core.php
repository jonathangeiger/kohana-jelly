<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Behavior allows mixin-style plugins to be written for models.
 * 
 * To write your own behavior, simply extend this class and override the
 * callback methods you need to. 
 * 
 * You can also write custom methods that are automatically made available 
 * to models and builders. If you want the method to be available to models,
 * simply prefix the method with 'model_'. For builders, use 'builder_'. The 
 * method can then be called like any other. For example: 
 * 
 *     $model->foo($bar);
 * 
 * Would call:
 * 
 *     $your_behavior->model_foo($sender, $bar);
 * 
 * As you can see, the object that called method is automatically sent as 
 * the first argument to your method.
 *
 * @package  Jelly
 */
abstract class Jelly_Behavior_Core
{
	/**
	 * @var  string  The name given to the behavior
	 */
	protected $name = NULL;
	
	/**
	 * Constructor.
	 *
	 * @param  string  $params 
	 */
	public function __construct($params = NULL)
	{
		// Just throw them into the class
		foreach ($options as $key => $value)
		{
			$this->$key = $value;
		}
	}
	
	/**
	 * Called when the model is first initialized, so 
	 * that the behavior can modify the metadata of 
	 * the model.
	 *
	 * @param   Jelly_Meta  $meta 
	 * @return  void
	 */
	public function initialize(Jelly_Meta $meta) { }
	
	/**
	 * Called just before executing a select query so that 
	 * the behavior can add additional clauses to the query.
	 *
	 * @param   Jelly_Builder  $query 
	 * @return  void
	 */
	public function before_select(Jelly_Builder $query) { }
	
	/**
	 * Called just after executing a select query so that 
	 * the behavior can modify the result if necessary.
	 * 
	 * Note that when limited to 1, such as when load() is
	 * called, you will receive a Jelly_Model for $result.
	 * Otherwise, you'll receive a Jelly_Collection.
	 *
	 * @param   Jelly_Builder     $query 
	 * @param   Jelly_Collection|Jelly_Model  $result
	 * @return  void
	 */
	public function after_select(Jelly_Builder $query, $result) { }
	
	/**
	 * Called before validating when the data is in its raw form
	 * in the model. Fields have not had a chance to process
	 * it with their save() method.
	 *
	 * @param   Jelly_Model  $model 
	 * @param   Validate     $data
	 * @return  void
	 */
	public function before_validate(Jelly_Model $model, Validate $data) { }
	
	/**
	 * Called before saving, giving the behavior a chance
	 * to modify data before it's saved.
	 * 
	 * $key is the primary key the model is about to be 
	 * saved to. If it is NULL, it's safe to assume that
	 * the record is about to be inserted, otherwise it's
	 * an update.
	 * 
	 * Return FALSE to cancel the save and any further 
	 * processing by behaviors.
	 *
	 * @param   Jelly_Model  $model 
	 * @param   mixed        $key
	 * @return  boolean
	 */
	public function before_save(Jelly_Model $model, $key) { }
	
	/**
	 * Called after saving, giving the behavior a chance
	 * to modify data after it's saved.
	 *
	 * @param   Jelly_Model  $model 
	 * @return  void
	 */
	public function after_save(Jelly_Model $model) { }
	
	/**
	 * Called whenever a model is deleted.
	 * 
	 * $key is the primary key that is about to be 
	 * deleted. 
	 * 
	 * Return FALSE to cancel the delete and any 
	 * further processing by behaviors.
	 *
	 * @param   Jelly_Model  $model 
	 * @param   mixed        $key
	 */
	public function before_delete(Jelly_Model $model, $key) { }
	
	/**
	 * Called after deletion.
	 * 
	 * Note, this is only called if the record was actually deleted.
	 *
	 * @param   Jelly_Model   $model 
	 * @return  void
	 */
	public function after_delete(Jelly_Model $model) { }
}
