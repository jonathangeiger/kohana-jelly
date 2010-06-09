<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jelly_Behavior allows mixin-style plugins to be written for models.
 * 
 * To write your own behavior, simply extend this class and override the
 * callback methods you need to. The callbacks are documented in 
 * Jelly_Behavior_Collection.
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
 * @see      Jelly_Behavior_Collection
 * @package  Jelly
 */
abstract class Jelly_Core_Behavior
{
	/**
	 * @var  string  The name given to the behavior
	 */
	protected $_name = NULL;
	
	/**
	 * Constructor.
	 *
	 * @param  string  $params 
	 */
	public function __construct($params = array())
	{
		// Just throw them into the class
		foreach ($params as $key => $value)
		{
			$this->{'_'.$key} = $value;
		}
	}
}
