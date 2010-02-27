# The Query Builder

If you're familiar with Kohana's query builder you already know how to use
Jelly's. However, there are often times where you need to extend the query
builder to provide custom logic for a specific model. Jelly provides
easy-to-use facilities for this.

### Custom Query Building

Say you have a query that you execute at different points throughout your
application. To keep it DRY, you can create a custom query builder method
to use.

To do this, you can define a class called `Model_Builder_ModelName` which
extends `Jelly_Builder`. You can then implement whatever specialist logic you
like. You could even modify the way normal queries are built to take into
account specific quirks in one model.

Without this feature, every place you wanted to list active users, you would
have to do this:

    Jelly::select('user')->where('last_login', '>', strtotime('- 3 month'))->execute();

However, with this feature you can now do this:

    Jelly::select('user')->active()->execute();

##### Example - Defining your model's custom builder class

Say we have our `Model_User` class and we need custom query building
capabilities. We can do this:

    class Model_Builder_User extends Jelly_Builder
    {
        public function active()
        {
            return $this->where('last_login', '>', strtotime('- 3 month'));
        }
    }

### The `unique_key()` method

`Jelly_Builder` also implements a special method that you are encouraged to
override. It is expected to return a field name to use for loading a unique
record. By default, it returns the model's primary key.

##### Example - Overriding unique_key()

    class Model_Builder_User extends Jelly_Builder
    {
        public function unique_key($value)
        {
            // If the value is numeric, we're searching for the primary key
            if (is_numeric($value))
            {
                return 'id';
            }
            else if (is_string($value))
            {
                return 'username';
            }
        }
    }

Since we know that the username column is also unique, we can use that for
finding individual users:

    Jelly::select('user')->load('some-username');
    
It will also work for saving and updating records, as well:

    Jelly::factory('user')->delete('some-username');
    
### Meta-aliases

Jelly has three built-in "meta-aliases" that can be used as shortcuts in query
building. They are automatically expanded to their actual column when built.

    // The :primary_key
    Jelly::select('user')->where(':primary_key', 'IN', array(1, 5 7))->execute();
    
    // The :name_key
    Jelly::select('user')->where(':name_key', '=', 'some-username)->execute();
    
    // The :unique_key
    Jelly::select('user')->where(':unique_key', =, $some_value)->execute();
    
It is also possible to use these *other* models:

    Jelly::select('post')
         ->join('author', 'LEFT')
         ->on('author.:primary_key', '=', 'post.author_id')
         ->execute();
         
[!!] **Note**: The aliases are expanded with the _meta\_alias() method. Feel free to override that to create custom meta-aliases.