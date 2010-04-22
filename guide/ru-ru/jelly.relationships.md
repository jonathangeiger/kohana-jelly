# Связи в Jelly

Jelly поддерживает стандартные связи типа один-к-одному, один-ко-многим и многие-ко-многим,
использую специальные поля.

### Определение связей

Связи определются через поля Jelly при инициализации модели. Стандартные связи используют поля 
`Field_BelongsTo`, `Field_HasOne`, `Field_HasMany` и `Field_ManyToMany`.

Так как Jelly поддерживает псевдонимы полей в своих моделях, то связи могут быть определны не только
в текущей модели (для отношения belongs to) и в модели, на которую связь ссылается, но и в
соответствующем поле связи другой модели. Это позволяет выстраивать однозначные связи с использованием
нестандартных внешних ключей.

Для примера приведено определение нескольких моделей. Поля, не относящиеся к теме опущены.

**Небольшое отступление по поводу "мета-алиасов"**

Query builder в Jelly реализует то, что мы зовём "мета-алиасы". Это ссылки на специальные поля в
модели, такие как первичный или внешний ключ. Использование их позволяет писать менее громоздкие
конструкции запросов, которые можно наблюдать при ручном формировании мета-объектов модели.
Большинство связей по умолчанию используют мета-алиас ':foreign_key', когда он определён в модели.

**Пример для иллюстрации:**

	Jelly::select('post')->join('author')->on('post.author:foreign_key', '=', 'author.:primary_key');
	=> SELECT * FROM `posts` JOIN `authors` ON (`posts`.`author_id` = `authors`.`id`);

Стоит заметить, что для модели определено значение `:foreign_key`. Это может быть сделано со
всеми мета-алиасами, но особенно полезно для получения связи с другой моделью в рамках вызова
другой.

#### Пример: определение связи

	// Каждый автор принадлежит к редакторам, имеет много постов и проверенных постов
	// имеет один адрес и обладает отношением многие-ко-многим с ролями
	class Model_Author extends Jelly_Model
	{
		public static function initialize($meta)
		{
			$meta->fields(array(
				'editor' => new Field_BelongsTo(
					// We can specify the foreign connection to ours
					'foreign' => 'editor.id',

					// Since BelongsTo has a column in the table, we can specify that
					// However, this would default to editor_id anyway.
					'column' => 'editor_id',
				),
				'posts' => new Field_HasMany(array(
					// If not set, this would default to post.author:foreign_key
					// And would expand in the query builder to posts.author_id
					'foreign' => 'post.author_id',
				)),
				'approved_posts' => new Field_HasMany(array(
					// Note a non-standard column can be used to make
					// multiple relationships between the same column possible
					'foreign' => 'post.approved_by',
				)),
				'address' => new Field_HasOne(array(
					// It's also possible to specify only a model.
					// This defaults to address.author:foreign_key
					'foreign' => 'address',
				)),
				'roles' => new Field_ManyToMany(array(
					// Once again, we're only specifying the model.
					// The user's foreign key is added automatically.
					'foreign' => 'role',

					// Through can be a model or table by itself
					'through' => 'author_roles',

					// Or if you need to specify the columns in the pivot table:
					'through' => array(
						'model'   => 'author_roles',
						'columns' => array('author_id', 'role_id'),
					),
				)),
			));
		}
	}

[!!] **Заметка**: За исключением `through` в отношениях ManyToMany, всегда необходимо определять
действующие модели. Однако, указывать поля модели не является обязательным. До тех пор, пока в
базе данных существует соответствующий столбец, всё будет работать нормально.

### Доступ до связанных таблиц

Используя определённую выше модель, мы можем сделать следующее:

	$user = Jelly::select('user', 1);

	// Показываем индекс
	echo $user->address->postal_code;

	// Получаем Jelly_Result для всех постов
	$posts = $user->posts;

	// Получаем подтверждённые посты
	$approved = $user->get('posts')->where('approved', '=', 1)->execute();

### Управление связями

Для связей n:1, отношения устанавливаются как свойства

	$user = Jelly::factory('user');

	// Установка по первичному ключу
	$user->address = 1;

	// Установка по экземпляру модели
	$user->address = Jelly::select('address', 1);

	// Удаление связей (убедитесь, что это разрешено правилами валидации в модели)
	$user->address = NULL;

	// Сохранение изменений в связях в базу данных
	$user->save();

Для n:many отношений используются методы `add()` и `remove()`:

	// Добавление одного поста по значению первичного ключа
	$user->add('posts', 1);

	// Добавление поста посредствам присвоение экземпляра модели
	$user->add('posts', $post);

	// Добавление нескольких отношений со смесью значений первичного ключа и экземпляров модели
	$user->add('posts', array(1, 2, $post));

	// Принимает те же аргументы ,что и add()
	$user->remove('posts', 1);

Добавление уже имеющихся связей или удаление отсутствующих не будет иметь эффекта и не вызовет ошибок.

[!!] **Замечание**: В настоящее время метод `save()` сохраняет только изменения *связей*, а не сами
модели. Это означает, что отношения устанавливаются только у существующего объекта модели.