# Jelly - Conceptual Overview

Conceptually, a Jelly model is divided into three separate parts that
interact with each other to form a usable model:

**[The Jelly Model](jelly.model)**

 * The abstract class `Jelly` which all models extend is only concerned
   with managing data and state. It is the main interface to accessing
   your data. It binds together the following two items.
 
**[The Jelly Fields](jelly.fields)**  

 * Each field of the model is represented by a subclass of `Jelly_Field`,
   such as `Field_String`, `Field_BelongsTo`, or `Field_File`. Each field
   class has special features that allow it to handle the data coming 
   from the database and from your application code in a specific way.
 
**[The Jelly Meta](jelly.meta)**  

 * A `Jelly_Meta` object holds various meta data about the model, such as
   the table the model represents, the default sorting options, and the
   fields. The `Jelly_Meta` class itself also contains several static 
   methods that can return meta data for any model without having to
   actually instantiate a model.
   
## How it all ties together

The first (and only the first) time your model is instantiated, a
static method called `initialize` is invoked. This method is passed a
single parameter, a `Jelly_Meta` object, which you can use to define
the various parts of your model. This is different from, for example,
Sprig, where the fields are instantiated *along with* your model whenever
it's instantiated. The result is a significant performance boost.

That said, here is a sample declaration of a model that represents
blog posts in a database. Each post belongs to a single author,
and can be part of many categories, and has a published status, among
other things:

    class Model_Post extends Jelly
    {
        public static function initialize(Jelly_Meta $meta)
        {
            $meta->fields += array(
                'id' => new Field_Primary,
                'name' => new Field_String,
                'author' => new Field_BelongsTo,
                'categories' => new Field_ManyToMany,
                'status' => new Field_Enum(array(
                    'choices' => array('draft', 'review', 'published')))
            );
        }
    }

##  Benefits to the architecture

There are several reasons for the architecture of a Jelly model being the
way it is, and they were chosen after looking at Kohana's other ORM
(namely the native ORM, and Sprig):

* In both Sprig and Kohana's ORM, logic for specific relationships is
  handled within the model, which makes extensibility very difficult. If
  you have a complex relationship that you need your model to have, you
  can create a Field class that implements a couple of methods and have
  it manage your logic. There is no need to override anything.

* Kohana's ORM has little concept of fields as Sprig and Jelly do. This
  makes aliasing columns rather cumbersome. 

* While Sprig does have Fields, they are newly instantiated each time the
  model is instantiated which negatively affects performance. Since Jelly
  manages state in the model, and uses the same field objects throughout
  the lifetime of a model, performance is greatly improved, especially
  when looping through hundreds of records.

* Kohana's ORM defines model metadata, validation rules, etc in the class
  declaration. While this is relatively clean, there are limitations with
  what you can put in a class declaration. You cannot, for example, call
  functions.

* Having statically accessible Meta data allows model classes to be
  declared abstract allowing for model inheritance and polymorphism
  without having to hack Jelly core. This is possible because with Jelly,
  you don't need an instance to access the meta data to be able to build
  the necessary queries for a model. Polymorphic model support is planned
  to be incorporated as a separate extension module to the Jelly Core.
