<?php
/**
 * Created by PhpStorm.
 * User: yahorosh
 * Date: 08.04.20
 * Time: 4:05
 */

namespace App\Importer1C\Extractor;

use App\Importer1C\Exception\FileNotFoundException;
use App\Importer1C\Interfaces\Extractor;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * Class CsvExtractor
 * @package App\Importer1C\Extractor
 */
class CsvExtractor implements Extractor
{
    /**
     * @var \Illuminate\Support\Collection
     */
    public $rows;
    /**
     * @var
     */
    protected $index;

    /**
     * CsvExtractor constructor.
     * @param $file
     * @throws FileNotFoundException
     * @throws \Box\Spout\Common\Exception\IOException
     * @throws \Box\Spout\Common\Exception\UnsupportedTypeException
     * @throws \Box\Spout\Reader\Exception\ReaderNotOpenedException
     */
    public function __construct($file)
    {
        if (!file_exists($file)) throw new FileNotFoundException($file);
        $this->rows = (new FastExcel)->configureCsv(';')->sheet(1)->import($file);


    }

    public function rewind()
    {
        $this->index = 0;
    }

    /**
     * @return mixed
     */
    public function current()
    {
        return $this->rows[$this->index]; //$var;
    }

    /**
     * @return bool|float|int|string|null
     */
    public function key()
    {
        return $this->index;
    }

    /**
     * @return int|void
     */
    public function next()
    {
        return $this->index++; //$var;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return isset($this->rows[$this->index]);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * @param $key
     * @param $value
     * @return \Illuminate\Support\Collection
     */
    public function where($key, $value)
    {
        return $this->rows->where($key, $value);
    }

    /**
     * @param callable $filter
     * @return \Illuminate\Support\Collection
     */
    public function filter(callable $filter)
    {
        return $this->rows->filter($filter);
    }

}