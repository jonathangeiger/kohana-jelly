# Upgrading 

We try to keep track of what backward-incompatible changes are made to the API
for those who want to keep up with development. Generally, Jelly tries to
remain backward compatible between maintenance releases, however major and
minor releases generally need not be.

## 1.0

#### All field classes have been renamed

All fields are prefixed with `Jelly_Field_` instead of only `Field_`. For future releases, it is recommended to use `Jelly::field()` or `$meta->field()` for declaring fields:

	class Model_Post extends Jelly_Model
	{
		public static function initialize($meta)
		{
			// ...
			$meta->field('name', 'type', $options);
			
			// OR
			
			$meta->fields(array(
				'name' => Jelly::field('string', $options),
			));
		}
	}

#### Jelly::query() is the new query builder interface

The static Jelly query builder methods have been removed in favor of `Jelly::query()`. Instead of `Jelly::select($model)->execute()` use this (the same goes for delete, insert, and update.):

	Jelly::query($model)->select();
	
We've done this because it allows transposable queries, which is especially useful for relationships:

	// Select all of the post's comments:
	$post->get('comments')->select();
	
	// Or delete them:
	$post->get('comments')->delete();
	
	// Or update them:
	$post->get('comments')->set('approved', 1)->update();

#### Inserting a Record

       // Insert a new comment
       Jelly::query('comments')
           ->columns(array('content'))
           ->values(array('This is the content of my comment.'))
           ->insert();
	
#### The filters, rules, and callbacks declaration syntax has changed slightly

There is a new declaration syntax for filters, rules, and callbacks. This change should actually be *backwards-compatible* since every attempt has been made to ensure the old `Kohana_Validate` style of declaration still works.

The new style is unified across filters, rules, and callbacks, which means callbacks now accept extra parameters, and filters can be called on objects.

	// New way for filters, rules, and callbacks
	'field' => array(
		array(callback $callback [, array $params])
	)
	
	// The following is also acceptable:
	'field' => array(
		callback $callback
	)

As you can see, any valid callback is accept for the first part of the array and an optional array of params can be passed.

For reference here is the old style that remains acceptable:

	// Old way for filters and rules
	'field' => array(
		'Class::method' => NULL,
		'function'      => array('arg1', 'arg2'),
	)
	
	// Old way for callbacks
	'field' => array(
		($object, $method),
		'Class::method',
	)
	
#### Jelly_Model->validate() has changed slightly

`Jelly_Model->validate()` used to accept an array of data to validate. It now only accepts a primary key as all of the other methods do.

    // Validate this model assuming it's loaded, so that only changed data is validated
	$model->validate($id);
	
If you need to perform validation of arbitrary bits of data, you now have direct access to the validator used:

	$validator = $model->validator($data);

	if ($data->check())
	{
		// Do stuff
	}
	
#### Jelly_Builder->load() has been removed

This is also because of `Jelly::query()`. You can pass a second argument to `Jelly::query()` which effectively duplicates the functionality, except it works on selects, deletes, and updates:

	// This is the replacement for load()
	Jelly::query('post', 1)->select();
	
	// Passing a key to the query effectively does this:
	Jelly::query('post')->where(':unique_key', '=' 1)->limit(1);
	
	// But since the query type isn't determined until the end now, we can also do:
	Jelly::query('post', 1)->delete();
	Jelly::query('post', 1)->update();
	
#### Jelly_Builder's behavior has changed slightly

Previously, `Jelly_Builder` allowed you to pass a field name as the model and it would expand it if it was a field that supported `with()`. This feature has been modified just slightly.

	// Previously
	Jelly::select('post')->with('author')->where('author.name', '=', 'FooBar');
	
	// Now:
	Jelly::query('post')->with('author')->where(':author.name', '=', 'FooBar')->select();
	
Notice the `:` in front of the field. this is necessary to resolve ambiguity as to what you're referring to, as it's very likely that `author` is both a field *and* model name. 

Conveniently, you can now do this with `has_many` and `many_to_many` or with completely different models:

	Jelly::query('post')
		->with('author:role')
		->where('author:role.type', '=', 'admin');
	
#### All of the input() methods have been removed

Jelly no longer supports generating views from fields since we've decided
this is a job for a form library and not an ORM.

Take a look at [Formo](http://github.com/bmidget/kohana-formo) if you're interested in a form library that is designed to work with Jelly.
	
#### Jelly_Builder->select() has been renamed to Jelly_Builder->select_column()

Since we're using `select()` to find records now, the old `select()` has been renamed to `select_column()`:

	// Select one at a time
	$query->select_column('id')->select_column('name')->select();
	
	// Or many at a time
	$query->select_column(array('id', 'name', 'body'))->select();
	
#### Jelly_Collection->as_array() no longer accepts meta-aliases

This means you can no longer do this:

	$collection->as_array(':primary_key');

As far as I know, this feature was rarely—if ever–used. It's better to alias a column if you need to bring it back as a specific name.

#### Jelly_Model->as_array() has changed slightly

`Jelly_Model->as_array()` used to operate like this:

	// Return the data as an array for the id, name, and body fields
	$data = $model->as_array('id', 'name', 'body');
	
It now works like this to allow dynamically calling it:

	// Return the data as an array for the id, name, and body fields
	$data = $model->as_array(array('id', 'name', 'body'));
	
#### Jelly_Meta->fields($field) no longer returns a field

Internally, this method was used heavily to find a particular field object by its name. It also resolves aliased fields, which is nice.

Since we added the `field()` method, it made sense to split these apart.

	// Returns all fields
	$meta->fields();
	
	// Returns a specific field
	$meta->field($field);
	
	// No longer works
	$meta->fields($field);