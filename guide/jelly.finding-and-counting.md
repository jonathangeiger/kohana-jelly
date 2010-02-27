# Finding and Counting Records

Jelly implements the same query builder that Kohana uses. Since it knows all
about your models, it adds extra special features like field aliasing and
automatic joins of 1:1 relationships.

### Finding a single record

Most of your time is spent working with a single record, but to do that you
have to first locate it!

##### Example - Loading a single record

    $post = Jelly::select('post', 1);
    $post->loaded(); // TRUE
    $post->saved(); // TRUE
    
    // The above is shorthand for the following
    $post = Jelly::select('post')->load(1);
    
    // And the above is shorthand for
    $post = Jelly::select('post')
                 ->where(':primary_key', '=', 1)
                 ->limit(1)
                 ->execute();
                 
### Finding many records

If you want to load many records, you end your query building with the
`execute()` method, which returns a `Jelly_Collection`. A Jelly_Collection contains a
collection of records that, when iterated over returns individual models for
you to work with.

[!!] **Note**: A Jelly_Collection has the same API as a Database\_Result, except it returns Jelly models

##### Example - Finding many records

    // Find every single post
    $posts = Jelly::select('post')->execute();
    
    foreach ($posts as $post)
    {
        echo $post->name;
    }
    

[!!] **Note**: Whenever you limit() to 1, execute() returns the model directly, instead of returning a Jelly_Collection

### Counting records

At anytime during a query builder chain, you can call the `count()` method to
find out how many records will be returned.

    $total_posts = Jelly::select('post')->where('published', '=', 1)->count();
