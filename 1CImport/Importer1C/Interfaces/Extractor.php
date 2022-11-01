<?php
/**
 *  * Created by PhpStorm.
 * User: YaHorosh ( mail@yahorosh.ru )
 * Date: 11.04.20
 * Time: 1:26
 */

namespace App\Importer1C\Interfaces;


interface Extractor extends \Iterator
{

    public function count(): int;

    public function where($key, $value);

    public function filter(callable $filter);

}