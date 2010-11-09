# Validation

All validation of Jelly models is performed by setting filters, rules, and callbacks on fields. Fields are then validated whenever the model is saved (or whenever `validate()` is called directly).

### Filters, rules, and callbacks

Understanding the purpose and semantic difference of each of the different validation properties is important to properly validating your model. Here is an explanation and example of each.

[!!] The surrounding class and `initialize` method have been omitted for the sake of brevity

#### `filters`

Filters do not do anything to verify the integrity of data. Instead, you can think of them as custom callbacks that can alter a value before it's validated. For example, it's often useful to trim whitespace off of a string field before validation:

	// ...
	'name' => new Jelly_Field_String(array(
		'filters' => array(
			'trim' => NULL,
		),
	)),
	
#### `rules`

Rules are the most important part of your validation process. They verify the integrity of your data. 

	// ...
	'name' => new Jelly_Field_String(array(
		'rules' => array(
			// Ensure the field is no longer than 128 characters
			array('max_length', array(128))
		),
	)),
	
#### `callbacks`

Callbacks are used for any final processing of a field after all rules have been processed. They can be used to modify the validation object or the model being validated.

In the following example notice that it is possible to pass *extra* parameters to callbacks, unlike `Kohana_Validate`. As an example, here is how `Jelly_Field_File` implements its file saving method:

	// ...
	'name' => new Jelly_Field_File(array(
		'callbacks' => array(
			array(array(':field_object', '_upload'), array(':validate', ':model', ':field'))
		),
	)),

#### A note about the callback syntax

As you may have noticed above, Jelly uses a slightly different format for setting callbacks when compared to Kohana
s `Validate` class. While Jelly remains compatible with `Validate`'s varying styles of setting filters, rules, and callbacks, the following format is preferred because it unifies the interface.

	// If you want to pass parameters, use this:
	array(callback $callback [, array $params])
	
	// If you only want to pass a callback, use this:
	callback $callback

This means that—unlike `Validate`—filters, rules, and callbacks all support any style callback as well as passing arguments to the callback.

[!!] **Note** As noted earlier, the old `Validate` style of setting filters, rules, and callbacks is still supported.

#### Callback contexts

You may have noticed in the example above regarding setting callbacks that `:model` is passed as the object part of the callback. Here's the relevant line in case you missed it:

	array(array(':model', 'callback'), array('arg1'))
	
Any callback passed to a filter, rule, or callback also allows a *context* to be passed as the object, which is converted to an actual object when the callback is actually invoked. Currently, there are two supported contexts:

#### `:model`

This is converted to the actual model instance that is being validated.

	class Model_User extends Jelly_Model
	{
		public static function initialize(Jelly_Meta $meta)
		{
			$meta->fields(array(
				// ...
				'username' => new Jelly_Field_String(array(
					'rules' => array(
						array(':model', 'validate_username')
					),
				))
			))
		}
	
		public function validate_username($value)
		{
			// $this is available
		}
	}

#### `:field`

This is converted to the actual field instance that is being validated. 

	class Jelly_Field_Foo extends Jelly_Field_String
	{
		public $rules = array(
			array(':field', 'bar')
		);
		
		// Upon validation, this method will be invoked on the actual field instance
		public function bar($value)
		{
			// ...do stuff...
		}
	}

### Checking if the model is valid

Validation can be performed at any time by calling the `validate()` method. If the model is `loaded()` or if you pass a key only changed data on the model is validated. Otherwise, all data will be validated:

	if ($model->validate())
	{
		$model->save();
	}

Validation is also performed automatically when calling `save()`. A `Validate_Exception` will be thrown if the model isn't valid when saving, so you should wrap the `save()` in a `try/catch` to ensure you capture any validation errors:

	try
	{
		$model->save();
	} 
	catch (Validate_Exception $e)
	{
		// Get the errors using the Jelly_Validator::errors() method
	    $errors = $e->array->errors('blog/post');
	}