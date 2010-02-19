# Jelly Meta Data

The Jelly_Meta class serves two purposes. 

 1. **As an object:** Whenever a model is registered with Jelly_Meta (such as
   when a model is instantiated for the first time, or when calling
   `Jelly_Meta::get('some-model')`) a new Jelly_Meta object is created and
   passed to the model's `initialize` method which can set properties on the
   object. After that, the meta object becomes read-only and is associated
   with all instances of that model for the lifetime of the script.
 2. **As a class:** Jelly_Meta also has several methods for statically retrieving
   meta data about any particular model. Model's are never instantiated to
   retrieve this information, so it is relatively quick.

------------------------------------------------------------------------------

#### Properties

[db](#meta-db) | [table](#meta-table) | [fields](#meta-fields) |
[sorting](#meta-sorting) | [load_with](#meta-load_with) |
[input_view](#meta-input_view) | [primary_key](#meta-primary_key) |
[name_key](#meta-name_key) | [validate_on_save](#meta-validate_on_save)

#### Static methods

[get()](#meta-method-get) | [class_name()](#meta-method-class_name) |
[model_name()](#meta-method-model_name) | [table()](#meta-method-table) |
[column()](#meta-method-column)

## Example

Below is an example initialize method with all properties being set explicitly:

    class Model_Post extends Jelly
    {
        public static function initialize(Jelly_Meta $meta)
        {
            $meta->db = 'default';
            $meta->table = 'posts';
            $meta->fields += array(
                'id' => new Field_Primary,
                'name' => new Field_String,
            );
            $meta->sorting = array('id' => 'ASC', 'name' => 'DESC');
            $meta->load_with = array('author:role', 'some-other-1-to-1-relationship');
            $meta->validate_on_save = TRUE;
            $meta->primary_key = 'id';
            $meta->name_key = 'name';
        }
    }

## Properties

These properties can be set in your `initialize()` method. However, every
attempt is made to ensure they're set to sensible defaults.

<h4 id="meta-db">$meta->db</h4>

**Default:** 'default'

The database configuration group to use for this model.

------------------------------------------------------------------------------

<h4 id="meta-table">$meta->table </h4>

**Default:** The name of the model pluralized and lowercased

The name of the table that the model references.

------------------------------------------------------------------------------

<h4 id="meta-fields">$meta->fields </h4>

**Default:** array()

An array of fields to use for the model, where the key is the alias you want
to use to reference the field, and the value is a new `Field_*` object.

If the value is a string, it will be assumed that you are declaring an alias
to another field. You can read more about 
[field aliases](jelly.other-features#field-aliases) in the [other features](jelly.other-features) section.

------------------------------------------------------------------------------

<h4 id="meta-load_with">$meta->load_with</h4>

**Default:** array();

An array of arguments to pass to `with()` for every `load()`

------------------------------------------------------------------------------

<h4 id="meta-sorting">$meta->sorting</h4>

**Default:** array();

An array of initial sorting options applied to multi-row `load()`'s. 

------------------------------------------------------------------------------

<h4 id="meta-input_view">$meta->input_view</h4>

**Default:** 'jelly/field'

Any calls to `input()` will use this prefix as the default location to load
views from. This allows you to specify different views for fields on a
per-model basis. Of course, this can still be overridden by setting `$prefix`
when calling `input()`.

------------------------------------------------------------------------------

<h4 id="meta-primary_key">$meta->primary_key</h4>

**Default:** The name of the first primary field found

The name of the field you want to use as your primary key.

------------------------------------------------------------------------------

<h4 id="meta-name_key">$meta->name_key</h4>

**Default:** 'name'

The name of the field you want to use as your name key. This is entirely
optional, but is useful if you want to output a human readable name for a row
by calling `$row->name()`.

------------------------------------------------------------------------------

<h4 id="meta-validate_on_save">$meta->validate_on_save</h4>

**Default:** TRUE

Whether or not to automatically validate data when saving it.

## Static Methods

<h4 id="meta-method-get">Jelly_Meta::get($model [,$property = NULL])</h4>

Returns the meta object for the `$model` passed, which can be a string (the
model's name, without the leading "model_") or an actually Jelly object.

If the `$model`'s meta object hasn't been registered, it will be. If the model
cannot be registered, FALSE is returned.

**Examples**

    Jelly_Meta::get('post');
    Jelly_Meta::get(new Model_Post);
    => Both return the post model's meta object

    Jelly_Meta::get('post')->table;
    Jelly_Meta::get('post', 'table');
    => Both return "posts"

    Jelly_Meta::get('non-existent-model');
    => FALSE is returned

    Jelly_Meta::get('post')->non_existent_property;
    Jelly_Meta::get('post', 'non_existent_property');
    => NULL is returned

    Jelly_Meta::get('post')->table = 'some other table';
    => Sorry charlie, meta data is read-only
    
------------------------------------------------------------------------------

<h4 id="meta-method-class_name">Jelly_Meta::class_name($model);</h4>

Returns the class name of a model. This method _does not_ register the model
passed.

**Examples**

    Jelly_Meta::class_name('post');
    Jelly_Meta::class_name(new Model_Post); // Silly, but it works
    => "model_post"

    Jelly_Meta::class_name('fake_model');
    => "model_fake_model";

    Jelly_Meta::class_name('model_post');
    => "model_model_post";
    
------------------------------------------------------------------------------

<h4 id="meta-method-model_name">Jelly_Meta::model_name($model);</h4>

Returns the model name of a model. This method _does not_ register the model
passed.

**Examples**

    Jelly_Meta::model_name('model_post');
    Jelly_Meta::model_name(new Model_Post); // Silly, but it works
    Jelly_Meta::model_name('post'); // Doesn't chomp
    => "post"

    Jelly_Meta::model_name('model_fake_model');
    => "fake_model";
    
------------------------------------------------------------------------------

<h4 id="meta-method-table">Jelly_Meta::table($model);</h4>

Returns the table name of the model passed. If the model doesn't exist, the input is returned.

**Examples**

    Jelly_Meta::table('post');
    Jelly_Meta::table(new Model_Post);
    => "posts"

    // The "posts" model doesn't exist, so the input is returned
    // This way you can pass tables or models and not have to
    // worry about what's coming back.
    Jelly_Meta::table('posts');
    => "posts";
    
------------------------------------------------------------------------------

<h4 id="meta-method-column">Jelly_Meta::column($model [,$field = FALSE [,$join = FALSE]]);</h4>

Returns the column name for a particular field. This method can take arguments in three separate ways:

 1. $model, $field [, $join = FALSE]
 2. $model_name, $field [, $join = FALSE]
 3. $model\_plus\_field [, $join = FALSE]
 
And this is how these different method signatures behave:
 
 1. In the first case, $model is a Jelly model and $field is a string.
 2. In the second case, $model is a string, and $field is a string.
 3. In the third case, $model\_plus\_field is a string in the format of 'model.field'.

If the model cannot be found in the registry (or registered), the method will
make every reasonable attempt to return something valid. This allows you to
pass tables and fields and still expect something reasonable back.

**Example One** Normal behavior

    Jelly_Meta::column('post', 'name');
    Jelly_Meta::column(new Model_Post, 'name');
    Jelly_Meta::column('post.name')
    => postName

    Jelly_Meta::column('post', 'name', TRUE);
    Jelly_Meta::column(new Model_Post, 'name', TRUE);
    Jelly_Meta::column('post.name', TRUE)
    => posts.postName

**Example One** Handling models and fields that can't be found

    // 'posts' is not a model, but a table
    Jelly_Meta::column('posts', 'name');
    => name

    // Neither can be found but they are still joined
    Jelly_Meta::column('posts', 'name', TRUE);
    => posts.name

    // 'post' is aliased because it's found, but the field is not
    Jelly_Meta::column('post', 'unknown_field', TRUE);
    => posts.unknown_field
