<?php

namespace App\Importer1C\Import;

use App\Importer1C\Interfaces\Extractor;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Storage;
use Psr\Log\NullLogger;
use SebastianBergmann\FileIterator\Iterator;

/**
 * Class AbstractImporter
 * @package App\Importer1C\Import
 */
abstract class AbstractImporter
{


    const ERROR_COMMON = 1;
    const ERROR_LOCKED = 2;
    const IMPORT_ROW_UPDATED = -1;
    const IMPORT_ROW_CREATED = 1;
    const IMPORT_ROW_UNCHANGED = -2;
    const IMPORT_ROW_SKIP = -3;
    const IMPORT_ROW_ERROR = 0;

    /**
     * @var Extractor
     */
    protected $data;
    /**
     * @var
     */
    protected $fileDesc;

    /**
     * @var array
     */
    protected $workedIndex = [];

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var null|string
     */
    public $lockDir = null;
    /**
     * @var int
     */
    public $countTotal = 0;
    /**
     * @var int
     */
    public $countSuccess = 0;
    /**
     * @var int
     */
    public $countFails = 0;
    /**
     * @var int
     */
    public $countUpdated = 0;
    /**
     * @var int
     */
    public $countUnchanged = 0;
    /**
     * @var int
     */
    public $countCreated = 0;
    /**
     * @var int
     */
    public $countSkip = 0;
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var callable
     */
    public $afterImportRowCallback;
    /**
     * @var callable
     */
    public $beforeImportRowCallback;
    /**
     * @var callable
     */
    public $beforeCallback;
    /**
     * @var callable
     */
    public $afterCallback;
    /**
     * @var string
     */
    public $rowTitleField = "Наименование";

    /**
     * AbstractImporter constructor.
     * @param Extractor $data
     */
    function __construct(Extractor $data)
    {
        $this->lockDir = Storage::path('importlock/');
        $this->data = $data;
        $this->logger = new NullLogger();
    }

    /**
     * @return string|string[]
     */
    public function getImportingDataName()
    {
        $path = explode('\\', get_class($this));
        return str_replace('Importer', '', array_pop($path));
    }

    /**
     * @return bool
     */
    protected function lock()
    {

        $fileName = $this->getLockFileName();

        !file_exists($fileName) && touch($fileName);

        if (($this->fileDesc = fopen($fileName, "r+")) && flock($this->fileDesc, LOCK_EX | LOCK_NB)) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function unLock()
    {

        if ($this->fileDesc) {
            flock($this->fileDesc, LOCK_UN);
            fclose($this->fileDesc);
        }

        @unlink($this->getLockFileName());

        return true;
    }

    /**
     * @return bool
     */
    function run()
    {
        $this->reset();

        $this->logStart();

        if (!$this->lock()) return $this->addError(self::ERROR_LOCKED);

        if ($this->before() !== false) {
            $this->doImport($this->data);
        }

        $this->after();

        $this->unLock();

        $this->logEnd();
        return true;
    }

    /**
     * @param iterable $data
     */
    protected function doImport(iterable $data)
    {
        $record_id = null;

        foreach ($data as $index => $row) {
            $this->countTotal++;
            $result = null;
            if (!$this->isWorked($index, $row) && (!is_callable($this->beforeImportRowCallback) || call_user_func($this->beforeImportRowCallback, [$this, $index, $row]) !== false)) {
                if ($depends = $this->getDepends($index, $row)) {
                    $this->doImport($depends);
                }
                if ($result = $this->importRow($index, $row, $record_id)) {
                    $this->logImportRowSuccess($index, $row, $record_id, $result);
                    $this->countSuccess++;
                    $result == self::IMPORT_ROW_CREATED && $this->countCreated++;
                    $result == self::IMPORT_ROW_UPDATED && $this->countUpdated++;
                    $result == self::IMPORT_ROW_UNCHANGED && $this->countUnchanged++;
                    $this->workedIndex[] = $index;

                } else {
                    $this->logImportRowFail($index, $row);
                    $this->countFails++;
                }
            } else {
                $this->logImportRowSkip($index, $row, $this->isWorked($index, $row));
                $this->countSkip++;
            }
            if (is_callable($this->afterImportRowCallback)) {
                call_user_func($this->afterImportRowCallback, [$this, $index, $row, $record_id, $result]);
            }
        }
    }

    /**
     * @param $index
     * @param $row
     * @return bool
     */
    public function isWorked($index, $row)
    {
        return in_array($index, $this->workedIndex);
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param $code
     * @param string $message
     * @return bool
     */
    protected function addError($code, $message = "")
    {
        $this->errors[] = ['code' => $code, "message" => $message];
        return false;
    }

    protected function resetErrors()
    {
        $this->errors = [];
    }

    /**
     * @param $index
     * @param array $row
     * @param $record_id
     * @return mixed
     */
    abstract function importRow($index, array $row, &$record_id);

    /**
     *
     * @return bool
     */
    protected function before()
    {
        if (is_callable($this->beforeCallback)) {
            return call_user_func($this->beforeCallback, [$this]);
        }

        return true;
    }

    /**
     * @param $index
     * @param $row
     * @return iterable|null
     */
    protected function getDepends($index, $row): ?iterable
    {
        return null;
    }

    protected function after()
    {
        if (is_callable($this->afterCallback)) {
            call_user_func($this->afterCallback, [$this]);
        }
    }

    /**
     * @return string
     */
    protected function getLockFileName()
    {


        if (!is_dir($this->lockDir)) {
            mkdir($this->lockDir, 0777, true);
        }
        return rtrim($this->lockDir, "/") . "/" . str_replace("\\", "_", get_class($this));

    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param $index
     * @param $row
     * @param $id
     * @param $result
     */
    protected function logImportRowSuccess($index, $row, $id, $result)
    {

        $name = $row[$this->rowTitleField];
        $this->logger->info("Запись №$index с наименованием '$name' (ID: $id) была "
            . ($result == self::IMPORT_ROW_CREATED ? "создана" : ($result = self::IMPORT_ROW_UPDATED ? "обновлена" : "без изменений")));
    }

    /**
     * @param $index
     * @param $row
     */
    protected function logImportRowFail($index, $row)
    {
        $this->logger->info("Запись №" . ($index) . " не удалось импортировать");
    }

    /**
     * @param $index
     * @param null $row
     * @param $worked
     */
    protected function logImportRowSkip($index, $row = null, $worked = flase)
    {
        $this->logger->info("Запись №" . ($index) . " пропущена" . ($worked ? ", т.к. импортирована ранее" : "."));
    }

    /**
     *
     */
    protected function reset()
    {
        $this->workedIndex = [];
        $this->resetErrors();
        $this->countSuccess = 0;
        $this->countFails = 0;
        $this->countUpdated = 0;
        $this->countCreated = 0;
    }

    protected function logStart()
    {
        $this->logger->info("==СТАРТ ИМПОРТА " . str_replace("\\", "_", get_class($this)) . str_repeat("=", 16));
        $this->logger->info("Строк для импорта:" . $this->data->count());
    }

    protected function logStat()
    {
        $this->logger->info("Строк обработано:" . $this->countTotal);
        $this->logger->info("Строк создано:" . $this->countCreated);
        $this->logger->info("Строк обновлено:" . $this->countUpdated);
        $this->logger->info("Строк без изменений:" . $this->countUnchanged);
    }

    protected function logEnd()
    {
        $this->logger->info("СТАТИСТИКА");
        $this->logStat();
        $this->logger->info("--СТОП ИМПОРТА " . str_replace("\\", "_", get_class($this)) . str_repeat("-", 16));
    }

}