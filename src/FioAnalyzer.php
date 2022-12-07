<?php

/**
 * Компонент анализа ФИО.
 * Позволяет определить фамилию, имя, отчества из набора слов
 *
 * Для своей работы требует пакета из composer mihanentalpo/fast-fuzzy-search
 * запускаем ./composer.phar install
 */

declare(strict_types=1);

namespace Mihanentalpo\FioAnalyzer;

use Mihanentalpo\FastFuzzySearch\FastFuzzySearch;

class FioAnalyzer
{
    public const FIO_PART_FILE_TYPE = [
        'first_names',
        'second_names',
        'last_names',
    ];

    /**
     * @var string[] Массив имён
     */ 
    public array $first_names = [];

    /**
     * @var string[] Массив отчеств
     */ 
    public array $second_names = [];
    /**
     * @var string[] Массив фамилий
     */ 
    public array $last_names = [];

    /**
     * @var FastFuzzySearch|null Объект быстрого поиска для имён
     */     
    public ?FastFuzzySearch $first_names_ffs = null;

    /**
     * @var FastFuzzySearch|null Объект быстрого поиска для отчеств
     */     
    public ?FastFuzzySearch $second_names_ffs = null;

    /**
     * @var FastFuzzySearch|null Объект быстрого поиска для фамилий
     */     
    public ?FastFuzzySearch $last_names_ffs = null;

    /**
     * Конструктор, загружает имена, фамилии и отчества и инициализирует объекты быстрого поиска
     */
    public function __construct()
    {
        foreach (self::FIO_PART_FILE_TYPE as $n) {
            $this->{$n} = require __DIR__ . '/' . $n . '.php';
            $this->load_dictionary($n, __DIR__ . '/' . $n . '.php');
        }        
    }

    /**
     * @param value-of<\Mihanentalpo\FioAnalyzer\FioAnalyzer::FIO_PART_FILE_TYPE> $type
     * @param string                                                    $filename
     */
    protected function load_dictionary(string $type, string $filename): void
    {
        $ffs_var = $type . '_ffs';
        $ffs = new FastFuzzySearch();
        if (file_exists($filename . '.index')) {
            if (false !== ($content = file_get_contents($filename . '.index'))) {
                $ffs->unserializeIndex($content);
            }
        } else {
            $ffs->init(require($filename));
            file_put_contents($filename . '.index', $ffs->serializeIndex());
        }
        $this->{$ffs_var} = $ffs;
    }

    /**
     * Функция разбивающая строку на массив с именем, фамилией и отчеством.
     * Например, для строки "Директор компании: Иванов Иван Иванович" вернёт массив
     * array (
     *            'first_name' => array   (
     *                                    'src' => 'Иван',
     *                                    'found' => 'иван',
     *                                    'percent' => 1,
     *                                ),
     *            'second_name' => array  (
     *                                    'src' => 'Иванович',
     *                                    'found' => 'иванович',
     *                                    'percent' => 1,
     *                                ),
     *            'last_name' => array    (
     *                                    'src' => 'Иванов',
     *                                    'found' => 'иванов',
     *                                    'percent' => 1,
     *                                )
     * )
     * В котором src - это то слово, которое было взято из исходной фразы,
     * found - это реальная фамилия/имя/отчество, соответствующее этому слову,
     * а percent - это величина совпадения, дробное число от 0 до 1 (обычно от $edge до 1)
     * Если какой-то из компонентов найти не удалось - он будет отсутствовать
     *
     * @param string $fio           Строка с ФИО
     * @param float $edge           Граница совпадния имени, фамилии, и отчества. Если не найдено ФИО,
     *                              совпадающее хотя-бы на столько, то они не будут добавлены в массив.
     * @param string $cacheFileName Имя файла для кэширования. Нужно для того, чтобы быстрее работать
     *                              с большим количеством строк ФИО.
     *
     * @return array<'first_name'|'last_name'|'second_name', array{src: non-falsy-string, found: int, percent: float}>
     */
    public function break_apart(string $fio, ?float $edge = 0.75, ?string $cacheFileName = null): array
    {
        $typeNames = ['first_name', 'second_name', 'last_name'];
        mb_internal_encoding('utf-8');

        $cached = null;

        $parts_unclean = explode(' ', trim($fio));
        $parts = [];

        foreach ($parts_unclean as $key => $value) {
            if (trim($value)) {
                $parts[] = trim($value);
            }
        }

        if ($cacheFileName && file_exists($cacheFileName)) {
            $cached = require($cacheFileName);
            $key = implode(' ', $parts);
            if (isset($cached['full'][$key])) {
                return $cached['full'][$key];
            }
        }

        $variants = $this->getNof3combinations(count($parts));

        $min = 10000000;
        $max = -10000000;

        $partsFound = [];
        foreach ($parts as $key => $p) {
            $found = [];
            $p = trim($p);
            if (strlen($p) >= 2) {
                $found[0] = $this->searchIn('first_names', $p, $min, $max, $edge);
                $found[1] = $this->searchIn('second_names', $p, $min, $max, $edge);
                $found[2] = $this->searchIn('last_names', $p, $min, $max, $edge);

                $partsFound[$key] = $found;
            }
        }
        $max = 0.0;
        $maxVar = -1;
        $coefs = [1, 0.8, 1];
        foreach ($variants as $vnum => $variant) {
            $sum = 0.0;
            foreach ($variant as $pos => $num) {
                if ($num > 0) {
                    $perc = $partsFound[$pos][$num - 1]['percent'] ?? 0;//TODO: Check this
                    $sum += $coefs[$num - 1] * $perc * $perc;
                }
            }
            if ($max < $sum) {
                $max = $sum;
                $maxVar = $vnum;
            }
        }
        $result = [];
        if ($maxVar === -1) {
            return [];
        }
        foreach ($variants[$maxVar] as $k => $v) {
            if ($v > 0 && $partsFound[$k][$v - 1]['percent'] > 0) {
                if (!isset($partsFound[$k][$v - 1]['value'])) {
                    $partsFound[$k][$v - 1]['value'] = 0;
                }
                $result[$typeNames[$v - 1]] = [
                    'src'     => $parts[$k],
                    'found'   => $partsFound[$k][$v - 1]['value'],
                    'percent' => $partsFound[$k][$v - 1]['percent'],
                ];
            }
        }

        if ($cacheFileName && is_writable(dirname($cacheFileName))) {
            if (is_null($cached)) {
                $cached = ['full' => [], 'partial' => []];
            }
            $key = implode(' ', $parts);
            $cached['full'][$key] = $result;

            file_put_contents($cacheFileName, '<?php return ' . var_export($cached,true) . ';');
        }

        return $result;
    }

    /**
     * @param array{
     *     'first_name'?: array{'src': string, 'found': string, 'percent': float},
     *     'second_name'?: array{'src': string, 'found': string, 'percent': float},
     *     'last_name'?: array{'src': string, 'found': string, 'percent': float}
     * } $arr
     *
     * @return string
     */
    public function toString(array $arr): string
    {
        $s = [];
        foreach ($arr as $item) {
            if (!empty($item['found'])) {
                $s[] = mb_convert_case($item['found'], MB_CASE_TITLE, 'UTF-8');
            }
        }

        return implode(' ', $s);
    }

    /**
     * @param string     $names "first_names" или "second_names" или "last_names"
     * @param string     $p
     * @param int        $min
     * @param int        $max
     * @param float|null $edge
     *
     * @return array{'word': string, 'percent': float}|null
     */
    public function searchIn(string $names, string $p, int $min, int $max, ?float $edge = 0.8): ?array
    {
        $p = mb_strtolower($p);
        $p = str_replace('ё', 'е', $p);

        $names_ffs = $names . '_ffs';

        $result = $this->{$names_ffs}->find($p, 1);

        return $result[0] ?? null;
    }

    /**
     * Получить набор комбинаций цифр 0(может повторяться) и 1,2,3 (повторяться не могут) в множестве из N цифр
     * Суть: если у нас есть число из N знаков, например из 5,
     * то какими способами в этом числе могут встречаться не повторяющиеся 1,2,3,
     * при том, что остальные кроме них места будут заполнены нулями?
     * Примеры таких вариантов: 12300, 12030, 12003, 10203, 10023, 01023, 00123, 32100, 3201 и так далее.
     * Суть в том, что 1, 2, 3 - это имя, отчество, фамилия, а 0 - это незначащее слово.
     *
     * @return array<mixed>
     * @throws \RuntimeException
     */
    public function getNof3combinations(int $n = 3): array
    {
        $result = [];
        if ($n === 1) {
            return [[1], [2], [3]];
        }
        if ($n === 2) {
            return [[1, 2], [1, 3], [2, 1], [2, 3], [3, 1], [3, 2]];
        }
        if ($n < 1) {
            return [];
        }

        $summNum = $n * ($n - 1) * ($n - 2);
        if ($summNum > 1000000) {
            throw new \RuntimeException('Too many variants, n=' . $n);
        }

        $initArray = static function ($n) {
            $arr = [];
            for ($i = 0; $i < $n; $i++) {
                $arr[] = 0;
            }

            return $arr;
        };

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n - 1; $j++) {
                $rj = $j;
                if ($rj >= $i) {
                    $rj++;
                }

                for ($k = 0; $k < $n - 2; $k++) {
                    $arr = $initArray($n);
                    $free = $initArray($n);
                    $arr[$i] = 1;
                    unset($free[$i]);
                    $arr[$rj] = 2;
                    unset($free[$rj]);
                    $pos = $k;
                    $num = 0;
                    foreach ($free as $q => $v) {
                        if ($pos === $num) {
                            $arr[$q] = 3;
                            break;
                        }
                        $num++;
                    }                    
                    $result[] = $arr;
                    unset( $arr );
                }
            }

        }

        return $result;
    }
}
