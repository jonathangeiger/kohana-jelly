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
`execute()` method, which returns a `Jelly_Result`. A Jelly_Result contains a
collection of records that, when iterated over returns individual models for
you to work with.

[!!] **Note**: A Jelly_Result has the same API as a Database\_Result, except it returns Jelly models

##### Example - Finding many records

    // Find every single post
    $posts = Jelly::select('post')->execute();
    
    foreach ($posts as $post)
    {
        echo $post->name;
    }
    

[!!] **Note**: Whenever you limit() to 1, execute() returns the model directly, instead of returning a Jelly_Result

### Counting records

At anytime during a query builder chain, you can call the `count()` method to
find out how many records will be returned.

    $total_posts = Jelly::select('post')->where('published', '=', 1)->count();
    
### The Query Builder

If you're familiar with Kohana's query builder you already know how to use Jelly's.

    // Find all active posts
    $posts = Jelly::select('post')->where('published', '=', 1)->execute();
    
    // Load posts with their author, since posts belong to an author
    $posts = Jelly::select('post')->with('author')->execute();

### Custom Query Building

Say you have a query that you execute at different points throughout your
application. To keep it DRY, you can create a custom query builder method
to use.

If you need specialist listing logic for a model, you can define a class
called `Model\_Builder\_ModelName` which extends `Jelly_Builder`. You can then
implement whatever specialist logic you like. You could even modify the way
normal queries are built to take into account specific quirks in one model.

Without this feature, every place you wanted to list active users, you would
have to do this:

    Jelly::select('user')->where('last_login', '>', strtotime('- 3 month'))->execute();

However, with this feature you can now do this:

    Jelly::select('user')->active()->execute();

##### Example - Defining your model's custom builder class

    class Model_Builder_User extends Jelly_Builder
    {
        public function active()
        {
            return $this->where('last_login', '>', strtotime('- 3 month'));
        }
    }

