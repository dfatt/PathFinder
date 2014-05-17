PathFinder
==========

Быстрый и охуенный роутинг для вашего сайта!
- Легко встраивается в любое приложение. Возможна как built-in конфигурация, так и создание адаптера
- Простой и лаконичный синтакис, ни одного лишнего символа
- Информативные сообщения об ошибках при разборе файла конфигурации
- Подсветка синтаксиса конфига в phpstorm и других IDE
- Максимум DRY: шаблоны регулярных выражений для параметров, привязка нескольких URL к одному контроллеру и т.д.

Простой пример:
@category,htmlpage   [A-Za-zА-Яа-яЁё0-9_\.]+        #название категории или страницы
@page                [0-9]+                           #числовой идентификатор
@date                [0-9]{4}\-[0-9]{1,2}\-[0-9]{1,2} #дата в формате 2012-12-12

[Catalog] # т.к. тут не указан action, он по умолчанию будет index
ANY /
ANY /{page}
ANY /{date}/{page}
ANY /{category}/{page}
ANY /{date}/{category}/{page}

[Pages]
GET /{htmlpage}.html

[Users:regform]
GET /register   {name: "Vladimir Makarov", status: "KIA"}  # какие-то дополнительные параметры

[Users:register]
POST /register


Пример генерации URL:
$url = $router->makeUrl('catalog', [
  'page' => 42, 
  'category' => 'news'
]); // "/news/42"

Пример разбора URL: 
$rules = $router->parseUrl('/news/42', 'GET'); // объект с контроллером, действием и т.д. или NoRouteException