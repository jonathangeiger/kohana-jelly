# Getting Started

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

 * They must extend Jelly_Model
 * They must define an initialize() method, which is passed a Jelly_Meta object
 * They must add properties to that define the model to that $meta object

From then on, the model has that specific `$meta` object attached to it.

[!!] Most of the things we're defining here are optional, but we're just putting them there for reference.