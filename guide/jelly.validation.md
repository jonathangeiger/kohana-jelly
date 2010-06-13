# Validation

All validation of Jelly models is performed by setting filters, rules, and callbacks on fields. Fields are then filtered whenever data is set on them, and validated with rules and callbacks whenever the model is saved (or whenever `validate()` is called directly).

### Filters, rules, and callbacks

Understanding the purpose and semantic difference of each of the different validation properties is important to properly validating your model. Here is an explanation and example of each.

#### `filters`

Unlike previous versions of Jelly, filters are no longer part of the validation process. Instead, you can think of them as custom callbacks that can alter a value whenever it changes. 

	class Model_Post extends Jelly_Model
	{
		public static function initialize(Jelly_Meta $meta)
		{
			$meta->fields(array(
				// ...
				'name' => new Jelly_Field_String(array(
					'filters' => array(
						// $model->set_name($value) will be called whenever 
						// a value is set on the name field
						array(':model', 'set_name'),
					),
				))
			))
		}
		
		public function set_name($value)
		{
			// ...do stuff to $value...
			return $value;
		}
	}
	
#### `rules`

Rules are the most important part of your validation process. They verify the integrity of your data. 

	class Model_Post extends Jelly_Model
	{
		public static function initialize(Jelly_Meta $meta)
		{
			$meta->fields(array(
				// ...
				'name' => new Jelly_Field_String(array(
					'rules' => array(
						// Ensure the field is no longer than 128 characters
						array('max_length', array(128))
					),
				))
			))
		}
	}
	
#### `callbacks`

Callbacks are used for any final processing of a field after all rules have been processed. They can be used to modify the validation object or the model being validated.

Callbacks are passed three parameters before any other parameters:

 * The validation object, which can be used to add errors.
 * The field being validated.
 * The model being validated.

In the following example notice that it is possible to pass *extra* parameters to callbacks, unlike `Kohana_Validate`.

	class Model_Post extends Jelly_Model
	{
		public static function initialize(Jelly_Meta $meta)
		{
			$meta->fields(array(
				// ...
				'name' => new Jelly_Field_String(array(
					'callbacks' => array(
						array(array(':model', 'callback'), array('arg1'))
					),
				))
			))
		}
		
		public function callback(Jelly_Validator $array, $field, Jelly_Model $model, $arg1)
		{
			// ...do stuff...
		}
	}

### Setting filters, rules, and callbacks

Each of these properties is set using the following format:

	// If you want to pass parameters, use this:
	array(callback $callback [, array $params])
	
	// If you only want to pass a callback, use this:
	callback $callback

This means that—unlike `Kohana_Validate`—filters, rules, and callbacks all support any style callback as well as passing arguments to the callback.

[!!] **Note** The old `Kohana_Validate` style of setting filters, rules, and callbacks is still supported.

#### The `:model` context

Any callback passed to a filter, rule, or callback also allows `:model` to be passed as the object. This will be converted to the model instance that is being filtered or validated.

	array(array(':model', 'method'), $params)

#### A complete example

Here is an example that 

	class Model_Post extends Jelly_Model
	{
		public static function initialize(Jelly_Meta $meta)
		{
			$meta->fields(array(
				'id'   => new Jelly_Field_Primary,
				'name' => new Jelly_Field_String(array(
					'filters' => array(
						// $model->set_name($value) will be called whenever 
						// a value is set on the name field
						array(':model', 'set_name'),
					),
					'rules' => array(
						// Ensure the field is no longer than 128 characters
						array('max_length', array(128))
					),
					'callbacks' => array(
						'
					),
				))
			))
		}
	}



### Creating and Updating

Both creation and updating is achieved with the `save()` method. Keep in mind
that `save()` may throw a `Validate_Exception` if your model doesn't validate
according to the rules you specify, so you should always test for this. Having
said that, we won't here just for clarity.

##### Example - Creating a new record

You can pass an array of values to set() or you can set the object members directly.

	Jelly::factory('post')
		 ->set(array(
			 'name'      => 'A new post',
			 'published' => TRUE,
			 'body'      => $body,
			 'author'    => $author,
			 'tags'      => $some_tags,
		 ))->save();

##### Example - Updating a record

Because the mosdel is loaded, Jelly knows that you want to update, rather than insert.

	$post = Jelly::query('post', 1)->select();
	$post->name = 'A new name!';
	$post->save();

##### Example - Updating a record without having to load it

Notice that we pass a primary key to save(). This updates the record, even if it isn't loaded.

	$post = Jelly::factory('post');
	$post->name = $new_name;
	$post->save($id);

##### Example - Saving a record from $_POST data

There is a shortcut provided for populating data in a newly instantiated model, which is useful for form processing:

	Jelly::factory('post', $_POST)->save();

However, one must take care to only insert the keys that are wanted, otherwise
there are significant security implications. For example, inserting the POST
data directly would allow an attacker to update fields that weren't
necessarily in the form.

	// Extract the useful keys first
	$model->set(Arr::extract($_POST, array('keys', 'to', 'use')));

### Delete

Deleting is also quite simple with Jelly. You simply call the `delete()`
method on a model. The number of affected rows (1 or 0) will be returned.

##### Example - Deleting the currently loaded record

	$post = Jelly::query('post', 1)->select();
	$post->delete();

##### Example - Deleting a record without having to load it

	// Notice we specify a unique_key for delete()
	Jelly::factory('post')->delete($id);


## Next [Accessing and managing relationships](jelly.relationships)