# Working With Models

Models are the most important part of Jelly. Each model represents a single row in the database but extend the database features to allow working with your data much simpler.

For the following examples, let's use this very simple `Book` model:

	class Book extends Jelly_Model
	{
		public static function initialize(Jelly_Meta $meta)
		{
			$meta->fields += array(
				'id'      => new Jelly_Field_Primary,
				'title'   => new Jelly_Field_String,
				'contents => new Jelly_Field_Text,
				'author'  => new Jelly_Field_BelongsTo
			);
		}
	}
 
We'll use this model to take a look at the core API of a Jelly Model. 

## Data Manipulation

Much of working with models centres around manipulating the data that they encapsulate. Jelly makes the following data manipulation methods available:

   * __get() - Magic method that returns the value for a field
   * __set() - Magic method that sets the value for a field
   * __isset() - Magic method that returns TRUE if the field exists
   * __unset() - Magic method that sets the field back to its default value
   * get() - Returns the value(s) for a field or fields
   * set() - Sets the value(s) for a field or fields
   * revert() - Reverts a field's value back to its initial value
   * initial() - The initial value of a field as it came from the database
   * changed() - Returns whether or not the field(s) passed have changed 

Let's find a book and start reading:

	$book = Jelly::factory('book', array('title' => 'Moby-Dick'));
	
	$book->title;
	// => 'Moby-Dick'
	
	$book->get(array('id', 'title'));
	// => array('id' => 1, 'title' => 'Moby-Dick')
	
	// Equivalent to $book->set('title', 'Moby-Dick or The Whale');
	$book->title = 'Moby-Dick or The Whale';
	
	$book->initial('title');
	// => 'Moby-Dick'
	
	$book->changed(array('id', 'title'));
	// => array('title')
	
	$book->changed('id');
	// => FALSE
	
	$book->revert('title');
	$book->title;
	// => 'Moby-Dick'
	
	$book->changed();
	// => FALSE