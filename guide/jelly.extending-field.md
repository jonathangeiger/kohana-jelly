# Extending Fields

Any custom field behavior can be added by defining your own field objects that
extend from `Jelly_Field` or one of it's derivatives.

Since relationships are all handled in fields, this effectively gives you the
flexibility of defining your own custom relationship logic. Note that for
this, you will need to look at the `Jelly_Field_Behavior_*` interfaces which
allow fields to specify that they can be used by methods like `with()`,
`has()`, or `add()` and `remove()`.

More detailed in for now can be found in the [API docs](api/Jelly_Field_Behavior).