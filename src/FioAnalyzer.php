<?php
namespace Mihanentalpo\FioAnalyzer;
/**
 * Компонент анализа ФИО.
 * Позволяет определить фамилию, имя, отчества из набора слов
 * 
 * Для своей работы требует пакета из composer mihanentalpo/fast-fuzzy-search
 * запускаем ./composer.phar install
 */
class FioAnalyzer
{
    /**
     * @var array Массив имён
     */ 
	public $first_names;
	/**
     * @var array Массив отчеств
     */ 
	public $second_names;
	/**
     * @var array Массив фамилий
     */ 
	public $last_names;

    /**
     * @var FastFuzzySearch Объект быстрого поиска для имён
     */ 	
    public $first_names_ffs;
    /**
     * @var FastFuzzySearch Объект быстрого поиска для отчеств
     */ 	
    public $second_names_ffs;
    /**
     * @var FastFuzzySearch Объект быстрого поиска для фамилий
     */ 	
	public $last_names_ffs;

	/**
	 * Конструктор, загружает имена, фамилии и отчества и инициализирует объекты быстрого поиска
	 */
	function __construct()
	{
        foreach(array("first_names", "second_names", "last_names") as $n)
        {
            $this->{$n} = require(__DIR__ . "/" . $n . ".php");
            $this->load_dictionary($n, __DIR__ . "/" . $n . ".php");
        }        
	}
    
    protected function load_dictionary($type, $filename)
    {
        $ffs_var = $type . "_ffs";
        $ffs = new \Mihanentalpo\FastFuzzySearch\FastFuzzySearch();// Mihanentalpo\FastFuzzySearch\FastFuzzySearch();
        if (file_exists($filename . ".index"))
        {
            $ffs->unserializeIndex(file_get_contents($filename . ".index"));
        }
        else
        {
            $ffs->init(require($filename));
            file_put_contents($filename . ".index", $ffs->serializeIndex());
        }
        $this->{$ffs_var} = $ffs;
    }

	/**
	 * Функция разбивающая строку на массив с именем, фамилией и отчеством.
	 * Например, для строки "Директор компании: Иванов Иван Иванович" вернёт массив
	 * array (
	 *			'first_name' => array   (
     *									'src' => 'Иван',
     *									'found' => 'иван',
     *									'percent' => 1,
     *								),
	 *			'second_name' => array  (
     *									'src' => 'Иванович',
     *									'found' => 'иванович',
     *									'percent' => 1,
     *								),
	 *			'last_name' => array    (
     *									'src' => 'Иванов',
     *									'found' => 'иванов',
     *									'percent' => 1,
     *								)
	 * )
	 * В котором src - это то слово, которое было взято из исходной фразы, found - это реальная фамилия/имя/отчество, соответствующее этому слову
	 * а percent - это величина совпадения, дробное число от 0 до 1 (обычно от $edge до 1)
	 * Если какой-то из компонентов найти не удалось - он будет отсутствовать
	 *
	 * @param string $fio			Строка с ФИО
	 * @param float $edge			граница совпадния имени, фамилии, и отчества. Если не найдено ФИО,
	 *								совпадающее хотя-бы на столько, то они не будут добавлены в массив
	 * @param string $cacheFileName имя файла для кэширования. Нужно для того, чтобы быстрее работать
	 *								с большим количеством строк ФИО
	 * @return type
	 */
	function break_apart ( $fio, $edge = 0.75, $cacheFileName=null ) {
		$typeNames = array( 'first_name', 'second_name', 'last_name' );
		mb_internal_encoding( 'utf-8' );

		$cached = null;

		$parts_unclean = explode( " ", trim($fio) );
		$parts = array();

		foreach($parts_unclean as $key=>$value)
		{
			if (trim($value))
			{
				$parts[] = trim($value);
			}
		}

		if ($cacheFileName && file_exists($cacheFileName))
		{
			$cached = require($cacheFileName);
			$key = implode(" ",$parts);
			if (isset($cached['full'][$key]))
			{
				return $cached['full'][$key];
			}
		}

		$variants = $this->getNof3combinations( count( $parts ) );
    
        	$min = 10000000;
        	$max = -10000000;
    
		$partsFound = array();
		foreach ( $parts as $key => $p )
		{
			$found = array();
			$p = trim( $p );
			if ( strlen( $p ) >= 2 )
			{
				//echo $p . "/" . count($parts) . "\n";
                
                		$found[0] = $this->searchIn( "first_names", $p, $min, $max, $edge );
				$found[1] = $this->searchIn( "second_names", $p, $min, $max, $edge );
				$found[2] = $this->searchIn( "last_names", $p, $min, $max, $edge );

				//print_r($found[0]);

				$partsFound[$key] = $found;

			}

		}
		$max = 0;
		$maxVar = -1;
		$coefs = array( 1, 0.8, 1 );
		foreach ( $variants as $vnum => $variant )
		{
			$sum = 0;
			foreach ( $variant as $pos => $num )
			{
				if ( $num > 0 )
				{
					if (!isset($partsFound[$pos]))
					{
						$xxx = true;
					}
					elseif (!isset($partsFound[$pos][$num - 1]))
					{
						$yyy = true;
					}

					$perc = $partsFound[$pos][$num - 1]['percent'];
					$sum += $coefs[$num - 1] * $perc * $perc;

				}
			}
			if ( $max < $sum )
			{
				$max = $sum;
				$maxVar = $vnum;
			}
		}
		$result = array();
		if ( $maxVar == -1 )
		{
			return array();
		}
		foreach ( $variants[$maxVar] as $k => $v )
		{
			if ( $v > 0 && $partsFound[$k][$v - 1]['percent'] > 0 )
			{
                if (!isset($partsFound[$k][$v - 1]['value']))
                {
                    $partsFound[$k][$v - 1]['value'] = 0;
                }
				$result[$typeNames[$v - 1]] = array('src'=>$parts[$k],'found'=>$partsFound[$k][$v - 1]['value'],'percent'=>$partsFound[$k][$v - 1]['percent']);
			}
		}

		if ($cacheFileName && is_writable(dirname($cacheFileName)))
		{
			if (is_null($cached))
			{
				$cached = array('full'=>array(), 'partial'=>array());
			}
			$key = implode(" ",$parts);
			$cached['full'][$key] = $result;

			file_put_contents($cacheFileName,"<?php return " . var_export($cached,true) . ";");
		}

		return $result;
	}

	function toString($arr)
	{
		$s = array();
		foreach($arr as $item)
		{
			$s[] = mb_convert_case($item['found'], MB_CASE_TITLE, "UTF-8");
		}
		return implode(" ", $s);
	}

	/**
	 * 
	 * @param type $names
	 * @param type $p
	 * @param type $min
	 * @param type $max
	 * @param type $edge
	 * @return type
	 */
	function searchIn ( $names, $p, $min, $max, $edge = 0.8 ) {
		$p = mb_strtolower( $p );
		$p = str_replace( "ё", "е", $p );
        
        $names_ffs = $names . "_ffs";
        
        $result = $this->{$names_ffs}->find($p, 1);
        
        $found = $result[0];
        
		return $found;
	}

	
	
	/**
	* Получить набор комбинаций цифр 0(может повторяться) и 1,2,3 (повторяться не могут) в множестве из N цифр
    * Суть: если у нас есть число из N знаков, например из 5, то какими способами в этом числе могут встречаться не повторяющиеся 1,2,3,
    * при том, что остальные кроме них места будут заполнены нулями?
    * Примеры таких вариантов: 12300, 12030, 12003, 10203, 10023, 01023, 00123, 32100, 3201 и так далее.
    * суть в том, что 1, 2, 3 - это имя, отчество, фамилия, а 0 - это незначащее слово.
	*/
	function getNof3combinations ( $n = 3 ) {
		$result = array();
		if ( $n == 1 )
		{
			return array( array( 1 ), array( 2 ), array( 3 ) );
		}
		if ( $n == 2 )
		{
			return array( array( 1, 2 ), array( 1, 3 ), array( 2, 1 ), array( 2, 3 ), array( 3, 1 ), array( 3, 2 ) );
		}
		if ( $n < 1 )
		{
			return array();
		}

		$summNum = $n * ( $n - 1 ) * ( $n - 2 );
		if ( $summNum > 1000000 )
		{
			throw new RuntimeException( "Too many variants, n=" . $n );
		}

		$initArray = function ( $n )
		{
			$arr = array();
			for ( $i = 0; $i < $n; $i++ )
			{
				$arr[] = 0;
			}

			return $arr;
		};

		$print_array = function ( $arr )
		{
			foreach ( $arr as $a )
			{
				echo $a;
			}
			echo "\n";
		};

		for ( $i = 0; $i < $n; $i++ )
		{
			for ( $j = 0; $j < $n - 1; $j++ )
			{
				$rj = $j;
				if ( $rj >= $i )
				{
					$rj++;
				}

				for ( $k = 0; $k < $n - 2; $k++ )
				{
					$arr = $initArray( $n );
					$free = $initArray( $n );
					$arr[$i] = 1;
					unset( $free[$i] );
					$arr[$rj] = 2;
					unset( $free[$rj] );
					$pos = $k;
					$num = 0;
					foreach ( $free as $q => $v )
					{
						if ( $pos == $num )
						{
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


