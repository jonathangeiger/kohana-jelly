<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Behavior_Interface is a simple interface that all behaviors must implement. 
 * 
 * Since callbacks and custom methods are discovered dynamically, there isn't much
 * to implement other than a constructor.
 * 
 * To write your own behavior, simply implement this class and create any of the
 * callback methods you need to, which are documented in Jelly_Behavior.
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
 * @see      Jelly_Behavior
 * @package  Jelly
 */
interface Jelly_Core_Behavior_Interface
{
	/**
	 * Constructor. To keep behaviors working consistently, the constructor 
	 * should either accept an array of parameters or a single parameter that
	 * configures the most commonly changed property of a behavior.
	 * 
	 * This is similar to how fields can be constructed:
	 * 
	 *     // Sets the column directly
	 *     new Jelly_Field_String('name');
	 * 
	 *     // An array is used since more needs to be configured
	 *     new Jelly_Field_String(array('column' => 'name', 'unique' => TRUE));
	 *
	 * @param  mixed  $params 
	 */
	public function __construct($params = array());
	
	/**
	 * This method is called when the behavior is being added
	 * to the behavior manager. It exists to allow the behavior
	 * to know its name and the name of the model it is attached to.
	 * 
	 * Do be careful when referencing the model; the meta object
	 * is still in the initialization process when this method is
	 * called, so you can't do anything with it yet.
	 *
	 * @param  string $model 
	 * @param  string $name 
	 */
	public function initialize($model, $name);
}
