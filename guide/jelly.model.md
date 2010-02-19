# Jelly Models

The abstract Jelly class is your main interface to your models. All models
must subclass it. Jelly implements most of the methods provided by Kohana's
Database Query Builder. In addition to this, the following methods are made
available to all models.

------------------------------------------------------------------------------

#### Methods

[get()](#jelly-get) | [set()](#jelly-set) | [load()](#jelly-load) | [save()](#jelly-save) |
[count()](#jelly-count) | [delete()](#jelly-delete) | [has()](#jelly-has) | 
[add() and remove()](#jelly-add) | [with()](#jelly-with) | [execute()](#jelly-execute) |
[input()](#jelly-input) | [alias()](#jelly-alias) | [all others](#jelly-others)

------------------------------------------------------------------------------

<h4 id="jelly-get">__get($name) <span style='color:#999'>and</span> get($name)</h4>

`__get()` is is a magic method that allows you to dynamically retrieve members
from the object. `get()`, on the other hand, is a public method that allows
more flexible retrieval of data. If `$name` does not correspond to a field, so
called "unmapped" data will be searched (this is data that was set without
having a corresponding field) and returned if found, otherwise NULL is
returned.

Both methods usage differ slightly from each other, particularly in regards to
relations, so it is important to understand the differences:

 * **__get()** returns whichever is found to exist first: changes, then
   lazy-loaded values, and finally the original data.
 * **__get()** returns relations loaded(), so you cannot build extra clauses on
   relationships retrieved with __get().
 * **__get()** caches all the values that are retrieved from it including related
   objects. The cache is cleared whenever a value is changed.

On the other hand:

 * **get()** has the option of returning original data or going through the
   normal chain of data.
 * **get()** returns relations unloaded, so you *can* build extra clauses
   on top of the queries. 
 * **get()** does not cache any retrieved values.
 * **get()** can return multiple values if you pass an array of keys or TRUE
   for the first argument.

**Example One** - The difference between changed and original data

Notice how specifying FALSE for the second parameter of get() returns the data
as it came from the database. The same concept applies to relationships; by
default you'll retrieve whatever the model says its related to, but passing
FALSE will return what the database says the model is related to.

    // Assume $post->name is 'Post One' in the database echo $post->name; =>
    'Post One'

    // Change the post's name
    $post->name = 'A different name';

    // __get() returns changed values
    echo $post->name;
    => 'A different name'

    // Passing FALSE for the second parameter returns the original data over changes
    echo $post->get('name', FALSE);
    => 'Post One'

    // Passing TRUE (which is the default value, so the argument can be
    // omitted) behaves exactly like __get()
    echo $post->get('name', TRUE);
    => 'A different name'

**Example Two** - Building extra clauses on get()

    // Assume $author has_many posts
    $author->posts->loaded();
    => TRUE

    // Equivalent to the above statement
    $author->get('posts')->load()->loaded();
    => TRUE

    // This is possible only with get()
    $author->get('posts')->where('post.date', '>', $some_date)->load();
    => Returns the author's posts filtered with the where clause


**Example Three** - Using get() to return multiple fields

Keep in mind that relationships will be returned as loaded() Jelly objects if
you pass TRUE for the second parameter (which is the default), and Jelly
objects that are ready to be load()ed if you pass FALSE.

    // Return multiple columns 
    $post->get(array('id, 'name')); 
    => array('id' => 1, 'name' => 'Some post')

    // Return all columns
    $post->get(TRUE);
    => array('id' => 1, 'name' => 'Some name', 'category' => ... );
    
------------------------------------------------------------------------------

<h4 id="jelly-set">__set($name, $value) <span style='color:#999'>and</span> set($values [, $alias = FALSE [, $original = FALSE]])</h4>

These two methods are nearly identical, and they can be used for setting any
data in your model. If `$name` does not correspond to a field that is
registered with model, the data will still be saved (and retrievable with
__get() and get()). This means that you do not necessarily need to make a
field for each column that you want to access. This "unmapped" data cannot be
saved or updated, however.

Like the aforementioned `__get()` and `get()`, `__set()` and `set()` differ
slightly in their usage. Here are the differences in a simple unordered list:

 * **__set()** is only used for setting members one at a time. 
 * **__set()** always treats the values passed as changes.
 * **__set()** always assumes you are using the field's name, not the database column's name.
 
On the other hand:

 * **set()** can set multiple values at once.
 * **set()** allows control over where the data is placed (as changed data, or original data).
 * **set()** also allows you to specify whether or not to alias the key(s) passed.
 
Some things to note that apply to *both* `__set()` and `set()`:

 * The value(s) passed can be nearly anything. Each field type makes every
   attempt to convert what is passed into usable data, and will not discard
   the data if it can't be converted to something usable (though other
   problems may crop up down the line).
 * Relations are also quite flexible; most relations accept NULL, a primary
   key, an array of primary keys, a Jelly Model, an array of Jelly Models, or
   a Database_Result as valid data.

**Example One** - Setting by member versus setting by an array

    $post->name = 'A new name!';
    $post->category = $some_category;

    // Equivalent to the above
    $post->set(array(
        'name' => 'A new name!',
        'category' => $some_category,
    ));

**Example Two** - Setting original data and aliasing

    // Pass TRUE for the second argument to treat array keys
    // as if they were the database column names.
    // 'User_UserName' is the column name in the database, 
    // while in the model it's name 'username'
    $post->set(array(
        'User_UserName' => 'some-name',
    ), TRUE);

    // Pass TRUE for the third argument to set the values as 
    // original data (as if they came from the database).
    // This is really only used internally to set Database_Results
    $post->set($_POST, FALSE, TRUE);

**A note on settings relationships**

All relationships can be set using these methods and Jelly is quite flexible
in what it allows to be set:

 * A primary key 
 * Another Jelly model

For "n:many" relationships the above is allowed, plus:

 * An iterable collection of primary keys or Jelly models, such as an array or Database_Result

Keep in mind that setting relationships this way will overwrite the old
relationship. If you need more fine-grained control over setting n:many
relationships, check out `add()` and `remove()` below.

------------------------------------------------------------------------------

<h4 id="jelly-load">load([$where = NULL[, $limit = NULL]])</h4>

Returns `$this` or a Database_Result, depending on a few factors:

* If `$where` is an integer or string, it is assumed to represent a single
  record with a primary_key equal to the value specified.
* If `$where` is an array, a simple where clause will be constructed from
  each item in the array, where the key is the field and the value is what the
  field should equal. 
  
**Example**

    $model->load(1) // Loads a single record, returns $this
    $model->load(array('category' => 1, 'author' => 'Jon') // Loads many records, returns a Database_Result
    $model->load(NULL, 10) // Returns a Database_Result of 10 records
    
------------------------------------------------------------------------------

<h4 id="jelly-save">save([$save_relations = TRUE])</h4>

Saves the current model to the database, updating it if it's `loaded()` and
inserting it if it's not. Only columns that have changed will be saved and
validated.

`$save_relations` specifies whether or not relations will be updated. If so,
any set relations will be updated based on what you set. This does NOT save
unsaved records, but rather refreshes the model's relations to other records.

Validation will be performed automatically unless you set `validate_on_save`
to false in your [meta declaration](jelly.meta)

**Example One**

    $post->name = 'A new name!';
    $post->category = $some_category;
    $post->save();

**Example Two**

    // In the following, whatever's in '$an_array_of_posts' 
    // will be updated to refer to this author
    $author->posts = $an_array_of_posts;
    $author->save();

**Example Three**

    $category = new Model_Category;
    $category->name = 'Some Category';

    // The post's category will be NULL or 0 when saved
    // because the above $category has not been saved!
    $post->category = $category;
    $post->save();

------------------------------------------------------------------------------

<h4 id="jelly-count">count([$where = NULL])</h4>

Returns an integer of the number of records found with the current chain of
query builds.

* If `$where` is an array, a simple where clause will be constructed from each
item in the array, where the key is the field and the value is what the field
should equal.

**Example**

    $model->count(array('author' => 'Jon'); // WHERE author = 'Jon'

------------------------------------------------------------------------------

<h4 id="jelly-delete">delete([$where = NULL])</h4>

Deletes the records found with the current chain of query builds. If the model
is loaded(), `where(table's id, '=', model's id)` will be appended to the
query chain and executed as part of the delete statement.

* If `$where` is an array, a simple where clause will be constructed from each
item in the array, where the key is the field and the value is what the field
should equal.

**Examples**

    // Deletes all records where the author = 'Jon'
    $model->delete(array('author' => 'Jon'); 

    // Deletes the record $post refers to
    $post->delete(); // Assume $post is loaded

    // Deletes where author = 'Jon' and 'id' = $post's id (if $post is loaded)
    $post->where('author', '=', 'Jon')->delete(); 
    
------------------------------------------------------------------------------

<h4 id="jelly-has">has($field, $models);</h4>

Returns a boolean as to whether or not the field has the particular models
passed. `$models` can be any of the following:

 * A primary key 
 * Another Jelly model 
 * An iterable collection of primary keys or Jelly models, such as an array or Database_Result

This can only be used on fields that implement `Jelly\_Field\_Interface\_Haveable`. 

    $category->has('posts', 1);
    $category->has('posts', $a\_database\_result);
    $category->has('posts', $a_model);
    $category->has('posts', array(1, 5, $a_model));

[!!] **Note**: currently, has() does not take into account changed data. It is the programmer's responsibility to know what the model has after changing (but not saving) a relationship.

    $category->posts = array(1, 5, 7);
    $category->has('posts', array(1, 5, 7));
    // Will only return TRUE if the category actually has those posts in the database

    $category->posts = array(1, 5, 7);
    $category->save();
    $category->has('posts', array(1, 5, 7));
    // Returns TRUE since it was saved to the database
    
------------------------------------------------------------------------------

<h4 id="jelly-add">add($field, $models) <span style='color:#999'>and</span> remove($field, $models);</h4>

These methods allow more fine-grained control over n:many relationships.
`$models` can be any of the valid values you would use for setting
relationships with `set()`. Adding relationships that the model is already
related to or removing relationships that the model is not related to will not
cause any problems.

You must call `save()` after setting the relationships to solidify them in the
database.

    $category->add('posts', $some_post);
    $category->remove('posts', $some_other_posts);
    $category->save();

    // This isn't the ideal way to remove all posts, but it does work
    $category->remove('posts', Jelly::factory('post')->load());
    $category->save();
    
------------------------------------------------------------------------------

<h4 id="jelly-with">with($relations);</h4>

Allows lazy-loading 1:1 relations. Any unlimited number of relations (as well
as those relation's relations) can be loaded this way.

`$relations` can be either a string, or an array. Additionally, calls can be
chained.

[!!]**Note for the lazy**: It's possible to automatically load specific relations whenever `load()` is called by setting `load_with` in the [model's meta data.](jelly.meta)

**Example One**

    $post->with('author:role');
    $post->load(1);
    echo $post->author->id();
    echo $post->author->role->id();

    // Both the post's author and the post's author's role are loaded with
    only one query

**Example Two**

    $post->with(array(
        'some-relationship', 
        'and-another', 
        'and-one-more:plus-its-child:plus-its-childs-child'
    ));
        
    // Equivalent to
    $post->with('some-relationship')
             ->with('and-another')
             ->with('and-one-more:plus-its-child:plus-its-childs-child');


[!!] **Note:** This method breaks on SQLite if you do not set `Kohana_Database_PDO::$_identifier` to `"` (a string containing a single double-quote). You can do this by overriding `Kohana_Database_PDO`.

------------------------------------------------------------------------------

<h4 id="jelly-execute">execute([$type = Database::SELECT [, $as_object = TRUE]]); <br> execute($type = Database::UPDATE|Database::INSERT, $data = array());</h4>

Executes a query based on the previous query builder calls. The return value
is the same as what DB::query() returns; it is dependent on the value passed
for $type.

The method signature is slightly different when `Database::UPDATE` or
`Database::INSERT` is passed as the query type. For backwards compatibility
purposes, if `$data` is a boolean, or a string, it will be used in place of
the value for `$as_object`, otherwise, if it is an array, it will be taken as
data that will be applied to all of the effective rows.

If `$as_object` is TRUE and the `$type` is a `Database::SELECT`, your model
will be used as the object inside the Database_Result iterator. Otherwise, you
can specify a string to use as the class, or FALSE to return an array. Keep in
mind that column aliasing will be broken if you specify a class other than the
model.

[!!] **Note**: This is a rather powerful feature of Jelly. If you're interested in seeing some examples, take a look at the [other features](jelly.other-features#complex-queries) section of this guide.

------------------------------------------------------------------------------

<h4 id="jelly-input">input($name [,$prefix = NULL [,$data = array()]]);</h4>

Returns a View object for the field `$name`. `$prefix` denotes a folder in
your views to use as the place to load the views from and it defaults to
whatever is set for `input_view` in your [meta declaration](jelly.meta)
`$data` is any extra data you'd like to pass to the view. By default, the view
is instantiated with all of the data provided to the field, plus the current
value for the column, and the model itself.

If `$prefix` is an array, it is assumed to be the value for `$data` and
`$prefix` is set to its default

**Examples**

    // Spits out a form field for the name
    $post->input('name')->render();

    // Setting the prefix allows you create your own, custom views
    $post->input('name', 'my/jelly/fields');

    // Data can be set in two ways. Assume $data is an array of data
    $post->input('name', 'some/prefix', $data);

    // ...or if you don't need to specify $prefix, but do need to specify $data
    $post->input('name', $data);

------------------------------------------------------------------------------

<h4 id="jelly-alias">alias([$field = NULL [, $join = NULL]]);</h4>

Returns an the actual table and column name for an aliased column, or whatever
was passed in if the alias can't be found. If `$join` is TRUE the format
`table.column` will be returned.

**Examples**

    // For illustration's sake, assume author references the column AuthorName
    $post->alias('author'); 
    => AuthorName
    $post->alias('author', TRUE); 
    => posts.AuthorName

    // Pass nothing to return the table name
    $post->alias();
    => posts

------------------------------------------------------------------------------

<h4 id="jelly-alias">field($name [, $name_only = FALSE]]);</h4>

Returns the canonical field for the `$name` passed. This resolves any aliased
fields to their actual implementation. If `$name_only` is TRUE, then only   
the canonical name of the field will be returned.

    // Assume 'username' is an alias for the 'name' field
    $user->field('username');
    $user->field('name');
    => Returns the name field object
    
    $user->field('username', TRUE);
    $user->field('name', TRUE);
    => 'name'
    
    $user->field('non-existent');
    => FALSE

------------------------------------------------------------------------------

<h4 id="jelly-others">Other useful methods</h4>

* **loaded()**—Returns a `Boolean` indicating whether or not the model is loaded.
* **saved()**—Returns a `Boolean` indicating whether or not the model is saved.
* **meta()**—Returns the model's meta object
* **reset()**—Resets the model as if it were freshly instantiated and empty
* **end()**—Resets the query builder to an empty state
* **id()**—The ID of the current loaded record
* **name()**—The name of the current loaded record, based on the value of name_key();
* **validate()**—Validates the model. If the model is not `loaded()` the original data, plus any changed data will be validated, otherwise only changed data is validated.