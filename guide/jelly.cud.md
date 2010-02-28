# Creating, Updating, and Deleting Records

To illustrate the various methods used in manipulating records, we'll create,
save and delete a record.

### Creating and Updating

Both creation and updating is achieved with the `save()` method. Keep in mind
that `save()` may throw a `Validate_Exception` if your model doesn't validate
according to the rules you specify, so you should always test for this. Having said that, 
we won't here just for clarity.

##### Example - Creating a new record

You can pass an array of values to set() or you can set the object members directly.

    Model::factory('post')
         ->set(array(
             'name' => 'A new post',
             'published' => TRUE,
             'body' => $body,
             'author' => $author,
             'tags' => $some_tags,
         ))->save()

##### Example - Updating a record

Because the model is loaded, Jelly knows that you want to update, rather than insert.

    $post = Jelly::select('post')->load(1);
    $post->name = $new_name;
    $post->save();
    
##### Example - Updating a record without having to load it

Notice that we pass a primary key to save(). This updates the record, even if it isn't loaded.

    $post = Model::factory('post');
    $post->name = $new_name;
    $post->save($id);
    
##### Example - Saving a record from $_POST data

Security implications aside, there is a shortcut provided for populating data
in a newly instantiated model, which is useful for form processing.

    Model::factory('post', $_POST)->save();

### Delete

Deleting is also quite simple with Jelly. You simply call the `delete()`
method on a model. The number of affected rows (1 or 0) will be returned.

##### Example - Deleting the currently loaded record

    $post = Jelly::select('post')->load(1);
    $post->delete();

##### Example - Deleting a record without having to load it

    // Notice we specify a unique_key for delete()
    Model::factory('post')->delete($id);


## Next [Accessing and managing relationships](jelly.relationships)

