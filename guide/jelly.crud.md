# Basic CRUD with Jelly

Jelly attempts to smooth over all of the tedium of CRUD. 

## Create and Update

Both creation and updating is achieved with the `save()` method. If the model
isn't `loaded()` then it is INSERTed into the database, otherwise, it's
UPDATEd.

For setting relationships, Jelly is extremely flexible. For the most part, you can set:

  * A primary key 
  * Another Jelly model

For "n:many" relationships the above is allowed, plus:

  * An iterable collection of primary keys or Jelly models, such as an array or Database_Result

**Example** - Using dynamic setting

    $post->name = 'A new post';
    $post->body = $body;
    $post->published = TRUE;
    $post->author = new Model_Author(1);
    $post->tags = $some_tags;
    $post->save();
    
**Example** - Setting an array of values in one go

    // You can also accomplish this with...
    $post = new Post;
    $post->set(array(
        'name' => 'A new post',
        'published' => TRUE,
        'body' => $body,
        'author' => new Model_Author(1),
        'tags' => $some_tags,
    ))->save();
    
## Read

Reading data is extremely flexible and simple. All data is ready by chaining
query builder statements and calling `load()`. `load()` will either return a
database result or load the result directly into the model, depending on what
you specify.

**Example** - Loading a single record on instantiation

    $post = new Model_Post(1);
    $post = new Model_Post(array('name' => 'A good post'));
        
**Example** - Loading a single record after instantiation

    $post = new Model_Post;
    $post->load(1);
    
    // The following two statements are equivalent
    // Notice that we have to specify 1, for the $limit argument
    $post->load(array('name' => 'A good post'))->load(NULL, 1);
    $post->where('name', '=', 'A good post')->load(NULL, 1);
    
    // If you pass TRUE for the second argument of limit, the subsequent load()
    // call will load the record into the model, even if $limit isn't specified
    $post->where('name', '=', 'A good post')
         ->limit(1, TRUE)
         ->load();
         
**Example** - Loading many records

    $post = new Model_Post;
    
    // Load everything
    $posts = $post->load();
    
    // Limit to 10 records
    $posts = $post->load(NULL, 10);
    
    // Load only the id column where they match $some_ids
    $posts = $post->select('id')
                  ->where('id', 'IN', $some_ids)
                  ->load();
    
## Delete

Deleting is also quite simple with Jelly. It's possible to delete multiple
rows, as well as the currently loaded record:

**Example** - Deleting the currently loaded record

    $post = new Model_Post(1);
    
    if ($post->loaded())
    {
        $post->delete();
    }
    
**Example** - Deleting multiple records

    $post = new Model_Post;
    $post->where('category', '=', $some_category)->delete();
    
