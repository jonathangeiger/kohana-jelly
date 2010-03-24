# Loading and Listing Records

Jelly extends Kohana's Query Builder. Since it knows all about your models, it
adds extra features like field and model aliasing and automatic joins of 1:1
relationships.

It is essentially an objectified version of query builder where you query for
Jelly objects from models rather than rows from tables.

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
`execute()` method, which returns a `Jelly_Collection`. A `Jelly_Collection` contains a
collection of records that, when iterated over returns individual models for
you to work with.

[!!] **Note**: A `Jelly_Collection` has the same API as a `Database_Result`, except it returns Jelly models

##### Example - Finding many records

	// Find every single post
	$posts = Jelly::select('post')->execute();

	foreach ($posts as $post)
	{
		echo $post->name;
	}

[!!] **Note**: Whenever you `limit()` a query to 1, `execute()` returns the model instance directly, instead of returning a `Jelly_Collection`

##### *So what's the difference between load() and execute()*

There is a small, but significant difference between load() and execute(). load() implicitly limits the query to 1, and just returns a model directly. load() also accepts an optional unique_key to find the record by.

    // load() is essentially shorthand for the following:
    Jelly::select('post')->where(':unique_key', '=', $value)->limit(1)->execute();
    
Additionally, load() is only useful for SELECTs. It will have no effect on any other query types.

### Counting Records

At any time during a query builder chain, you can call the `count()` method to
find out how many records will be returned.

	$total_posts = Jelly::select('post')->where('published', '=', 1)->count();

### The Query Builder

If you're familiar with Kohana's query builder you already know how to use Jelly's.

	// Find all active posts
	$posts = Jelly::select('post')->where('published', '=', 1)->execute();

	// Load posts with their author in one query
	// This is possible since there is only 1 author for each post
	$posts = Jelly::select('post')->with('author')->execute();

[!!] **Note**: See [Extending the query builder](jelly.extending-builder) for more advanced building options

### Next [Creating, updating and deleting records](jelly.cud)

