# Understanding Aliases and Meta-Aliases

Jelly uses model and field aliases throughout. Generally, when naming a field in your model the column in the database has the same name, but Jelly allows most fields to reference a column with a different name. This means you can effectively decouple your database and model schema.

Jelly also has a meta-alias system that allows you to reference specific fields that are common to all models, such as it's primary key.

### Aliases

When defining your fields, you can specify a column that this field represents:

    class Model_Post extends Jelly_Model
    {
        public static function initialize($meta)
        {
            $meta->fields(array(
                'id' => new Field_Primary(array(
                    'column' => 'PostId')),
            ));
        }
    }

Anywhere you reference the 'id' field, it will be mapped to the 'PostId' column. For example:

    $post->where('id', 'IN', array(1, 2, 3));
    
    // The following will work, but ties your logic to 
    // your database schema so it's frowned upon
    $post->where('PostId', 'IN', array(1, 2, 3));
    
Anywhere you're referencing a model or field, you should be using the name of a model or field, *not* the name of a table or column.

### Meta-Aliases

Meta-aliases are a syntactic shortcut for referencing a particular field in a model. There are currently four meta-aliases defined:

  * **:primary_key** - references the model's primary key
  * **:name_key** - references the model's name key 
  * **:unique_key** - references the model's unique key 
  * **:foreign_key** - references the model's foreign key 
  
##### Example - Using a meta-alias

    $post->where(':primary_key', '=', $value);
    $post->where(':name_key', '=', $value);
    
    // In this case, value is passed to the unique_key() method in 
    // your builder class, which returns the proper field to use
    // based on the value
    $post->where(':unique_key', '=', $value);
    
##### Example - Using the :foreign_key meta-alias

Generally, you want to be able to reference another model's foreign key, so there is a special syntax for doing such a thing. 

    // Assume a post belongs_to an author
    $post->where('post.author:foreign_key', '=', $value);
    
    // This is also possible, though not really useful or practical
    $post->where('author:primary_key', '=', $value);
    
In this case, you specify a model before the meta-alias to pull it from.

[!!] **Note**: Meta-aliases can only be used in the query builder or with the as_array() method in Jelly\_Collection.

## Changing your meta-aliases

Your model's primary\_key, name\_key, and foreign\_key are all defined in your initialize() method. More information can be found in the [API documentation for Jelly_Meta](api/Jelly_Meta).

The unique\_key is a special case since a value is passed to it so that it can determine the proper field to use. To change its behaviour you must [create your model specific Jelly\_Builder](jelly.extending-builder).