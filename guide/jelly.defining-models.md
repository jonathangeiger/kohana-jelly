# Defining Models

The first place to start with any ORM is defining your models. Jelly splits up
models into a few separate components that make the API more coherent and
extensible.

First, let's start with a sample model:

	class Model_Post extends Jelly_Model
	{
		public static function initialize(Jelly_Meta $meta)
		{
			$meta->table('posts')
				 ->fields(array(
					 'id' => new Field_Primary,
					 'name' => new Field_String,
					 'body' => new Field_Text,
					 'status' => new Field_Enum(array(
						 'choices' => array('draft', 'review', 'published'))),
					 'author' => new Field_BelongsTo,
					 'tags' => new Field_ManyToMany,
				 ));
		}
	}

As you can see all models must do a few things to be registered with Jelly:

 * They must extend `Jelly_Model`
 * They must define an `initialize()` method, which is passed a `Jelly_Meta` object
 * They must add properties to that `$meta` object to define the fields, table, keys and a number of other things

The `initialize()` method is only called once per execution for each model and the model's meta object is stored
statically. If you need to find out anything about a particular model, you can use `Jelly::meta('model')`.

[!!] Most of the things we're defining here are optional and have sensible defaults, but we're just putting them there for reference.

## Jelly Fields

Jelly defines [many field objects](jelly.field-types) that cover the most common types of columns used in database tables.

In Jelly, the field objects contain all the logic for retrieving, setting and saving database values.

Since all relationships are handled through relationship fields, it is possible to implement custom, complex relationship
logic in a model by [defining a custom field](jelly.extending-field).

### Next [Loading and listing records](jelly.loading-and-listing)