<?php

namespace App\Jobs;


use App\Attribute;
use App\Image;
use App\Importer1C\Exception\FileNotFoundException;
use App\Importer1C\Import\AttributeImporter;
use App\Importer1C\Import\CategoryImporter;
use App\ImportFile;
use App\Product;
use Chumper\Zipper\Zipper;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imtigger\LaravelJobStatus\Trackable;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Importer1C\Import\ProductImporter;
use App\Importer1C\Extractor\CsvExtractor;
use Symfony\Component\Process\Process;

class Import1CDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable;

    /**
     * @var false|string
     */
    protected $filePath;

    /**
     * @var string
     */
    protected $unzippedDirName;

    /**
     * @var string
     */
    protected $unzippedDirPath;

    /**
     * @var bool
     */
    protected $saveUnzipped;

    /**
     * Create a new job instance.
     *
     * @param string $filePath
     * @param bool $saveUnzipped
     * @throws Exception
     */
    public function __construct($filePath, $saveUnzipped = false)
    {
        set_time_limit(0);

        $filePath = realpath($filePath);
        $this->filePath = $filePath;
        $this->saveUnzipped = (bool)$saveUnzipped;

        $this->prepareStatus();

        if ($filePath == '') {
            throw new Exception('File argument is empty');
        }

        if (!file_exists($filePath) || !is_file($filePath)) {
            throw new Exception('File ' . $filePath . ' does not exists');
        }

        $info = pathinfo($filePath);

        $this->unzippedDirName = $info['filename'];
        $this->unzippedDirPath = Storage::path('import') . '/' . $info['filename'];
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception|FileNotFoundException
     */
    public function handle()
    {

        //$id = $this->job->getJobId();
        //echo 'Import started'."\n\n";
        //echo 'Id:' . $this->job->getJobId();
// set quantity = 0;
        Log::channel('import')->info("Reset quantity for each product (set 0)");
        $this->resetRests();

        Log::channel('import')->info("New import start! Unziping {$this->filePath}");

        $this->unzip($this->filePath);

        Log::channel('import')->info('Unziping end');

        if (file_exists($this->unzippedDirPath . '/Properties.csv')) {
            $attributeExtractor = new CsvExtractor($this->unzippedDirPath . '/Properties.csv');
            $attributeImporter = new AttributeImporter($attributeExtractor);
            $attributeImporter->setLogger(Log::channel('import'));
            $attributeImporter->run();
        }

        if (file_exists($this->unzippedDirPath . '/Groups.csv')) {
            $categoryExtractor = new CsvExtractor($this->unzippedDirPath . '/Groups.csv');
            $categoryImporter = new CategoryImporter($categoryExtractor);
            $categoryImporter->setLogger(Log::channel('import'));
            $categoryImporter->run();
        }

        if (file_exists($this->unzippedDirPath . '/Offers.csv')) {
            $productExtractor = new CsvExtractor($this->unzippedDirPath . '/Offers.csv');
            $productImporter = new ProductImporter($productExtractor, "import/{$this->unzippedDirName}");
            $productImporter->setLogger(Log::channel('import'));
            $productImporter->run();
        }

        if (!$this->saveUnzipped) {
            $process = Process::fromShellCommandline("rm -rf {$this->unzippedDirPath}");
            $process->setTimeout(0);
            $process->run();


            $process = Process::fromShellCommandline("rm {$this->filePath}");
            $process->setTimeout(0);
            $process->run();
        }

        \Artisan::call('Products:assignCategory');
    }

    /**
     * @param $filePath
     * @throws Exception
     */
    function unzip($filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception('File ' . $filePath . ' does not exists');
        }

        $info = pathinfo($filePath);
//        $zipper = new Zipper();

        try {
            $process = Process::fromShellCommandline("unzip -u {$filePath} -d {$this->unzippedDirPath}");
            $process->setTimeout(0);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            }
//            $zipper->zip($filePath)->extractTo(Storage::path('import') . '/' . $info['filename']);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw new Exception('Extracting error');
        }
    }

    function getJobId()
    {
        return $this->job->getJobId();
    }

    function resetRests()
    {
        \DB::table('product_size')->update(['quantity' =>0]);
    }
}


