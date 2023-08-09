# FIO-Analyzer

Это клон проекта "mihanentalpo/fio-analyzer" с доработками и оптимизациями для PHP 8.

Анализатор строк, содержащих фамилию, имя, отчество

Подробная статья: https://mihanentalpo.me/2017/03/как-разбить-фио-на-имя-фамилию-отчеств/


Установка:

1. Прописываем в composer.json:
```json
{
    "require": {
        "ekhlakov/fio-analyzer": "*"
    }
}
```

2. Устанавливаем (как установить composer.phar можно посмотреть на http://getcomposer.org): 
```bash
$ composer.phar install
```

3. Подключаем класс:
```php
<?php
require_once("./vendor/autoload.php");
```

Использование:

```php
<?php
require_once("./vendor/autoload.php");
$fa = new \Mihanentalpo\FioAnalyzer\FioAnalyzer();
$names = $fa->break_apart("Иваанов иван Ыванович");
print_r($names);
```
