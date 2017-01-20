# FIO-Analyzer
Анализатор строк, содержащих фамилию, имя, отчество

Установка:

1. Прописываем в composer.json:
```json
{
    "require": {
        "mihanentalpo/fio-analyzer": "*"
    }
}
```

2. Устанавливаем (как установить composer.phar можно посмотреть на http://getcomposer.org): 
$ composer.phar install

3. Подключаем класс:
```php
<?php
require_once("./vendor/autoload.php");
```

Использование:

<?php
require_once("./vendor/autoload.php");
$fa = new \Mihanentalpo\FioAnalyzer\FioAnalyzer();
$names = $fa->break_apart("Иваанов иван Ыванович");
print_r($names);


