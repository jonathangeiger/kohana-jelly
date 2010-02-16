Jelly is a nice little ORM for Kohana. It is currently in beta and not quite ready for production.

That said, the ORM is [unit tested](http://github.com/jonathangeiger/jelly-tests) and [very well-documented](http://wiki.github.com/jonathangeiger/kohana-jelly/).

## Notable Features

* **Standard support for all of the common relationships** — This includes `belongs_to`, `has_many`, and `many_to_many`. Pretty much standard these days.

* **Top-to-bottom table column aliasing** – All references to database columns and tables are made via their aliased names and converted transparently, on the fly. 

* **A built-in query builder** — This features is a near direct port from Kohana's native ORM. I find its usage much simpler than Sprig's.

* **Extensible field architecture** — All fields in a model are represented by a `Field_*` class, which can be easily overridden and created for custom needs. Additionally, fields can implement behaviors that let the model know it has special ways of doing things.

* **No circular references** — Fields are well-designed to prevent the infinite loop problem that sometimes plagues Sprig. It's even possible to have same-table child/parent references out of the box without intermediate models.
