<?php

declare(strict_types=1);

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../src/FioAnalyzer.php');

$t = microtime(true);
$fa = new \Mihanentalpo\FioAnalyzer\FioAnalyzer();
$t = microtime(true) - $t;
echo 'Time for FioAnalyzer initialization: ' . $t . "\n";


echo "Анализ строки на наличие ФИО:\n";
print_r($fa->break_apart('Главный инженер Иванов Иван Иванович'));

$ffs = new \Mihanentalpo\FastFuzzySearch\FastFuzzySearch();
