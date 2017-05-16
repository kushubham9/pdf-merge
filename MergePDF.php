<?php

/**
 * Created by PhpStorm.
 * User: Kumar Shubham
 * Date: 16/05/17
 * Time: 7:23 PM
 */

require_once (dirname(__FILE__).'/fpdf/fpdf.php');
require_once  (dirname(__FILE__).'/fpdi/fpdi.php');

class MergePDF
{
    // The Remote files
    private $files = [];
    private $attachmentFolder = '/assets/';
    private $outputFile = 'merged_pdf.pdf';

    /**
     * MergePDF constructor.
     * The configuration array properties the required input.
     * @param array $config
     * @throws Exception
     */
    function __construct(array $config = [])
    {
        // Setting the configuration.
        foreach ($config as $property =>$value) {
            if (property_exists('MergePDF',$property)){
                $this->{$property} = $value;
            }
        }

        //Reads the input
        if (isset($config['inputFile']))
            $this->_readInput($config['inputFile']);

        if (sizeof($this->files) == 0){
            throw new Exception("No files provided.");
        }
        // Checks if the asset folder exists. If not, creates one.
        // Note: The user must have priviledge to create folder in the directory.
        if (!file_exists(__DIR__.$this->attachmentFolder)) {
            mkdir($this->attachmentFolder, 0777, true);
        }
    }

    /**
     * Reads the input file which contains the URL
     * @param $file
     * @throws Exception
     */
    public function _readInput($file){
        if (!file_exists(__DIR__.'/'.$file)){
            throw new Exception("Invalid Input File specified");
        }

        $handle = fopen(__DIR__.'/'.$file, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
//                echo $line."\n";
                array_push($this->files, trim($line));
            }
            fclose($handle);
        } else {
            throw new Exception("Unable to read Input specified");
        }
//        echo sizeof($this->files);
//        die();
    }

    // Remotely fetches all the files through curl and stores in the attachmentFolder.
    /**
     * @return array [The files downloaded successfully]
     */
    private function _fetchFiles(){
        echo "Starting PDF fetching at: ".date("Y-m-d H:i:s")."\n";
        $startTime = microtime(true);
        $readyFiles = [];
        $i=0;
        foreach ($this->files as $fileUrl){
            if ($this->_downloadFile($fileUrl, $this->attachmentFolder.'_file'.$i.'.pdf')){
                $readyFiles[$i] = $this->attachmentFolder.'_file'.$i.'.pdf';
                $i = $i + 1;
            }
        }

        $endTime = microtime(true);
        echo "PDF Fetch completed at: ".date("Y-m-d H:i:s")."\n";
        echo $endTime - $startTime ."ms to fetch ". $i ." files.\n";

        return $readyFiles;
    }

    /**
     * Makes a curl call to fetch the file.
     * @param $fileUrl - The remote URL
     * @param $location - The path where stored.
     * @return mixed - The status of CURL request.
     */
    private function _downloadFile($fileUrl, $location){
        set_time_limit(0);

        $fp = fopen (dirname(__FILE__) . $location, 'w+'); // Output file
        $ch = curl_init($fileUrl); // Input file
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $fileStatus = curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        return $fileStatus;
    }

    /**
     * The merge function.
     *
     * @param $files [The input file list which is to be merged]
     */
    private function _mergeFiles($files){
        echo "Starting PDF merging at: ".date("Y-m-d H:i:s")."\n";
        $startTime = microtime(true);

        $pdf = new FPDI();

        foreach ($files as $file){
            $count = $pdf->setSourceFile(dirname(__FILE__).$file);

            for ($i = 0; $i < $count; $i++) {
                $tpl = $pdf->importPage($i + 1, '/MediaBox'); //Imports each page as a screen.
                $pdf->addPage();
                $pdf->useTemplate($tpl);
            }
        }

        // Create an output file.
        $pdf->Output('F',$this->outputFile);

        $endTime = microtime(true);
        echo "Completed PDF merging at: ".date("Y-m-d H:i:s")."\n";
        echo $endTime - $startTime ."ms to fetch and merge ". sizeof($files) ." pdfs.\n";
    }

    public function execute(){
        $files = $this->_fetchFiles();
        $this->_mergeFiles($files);
    }
}

$t = new MergePDF(['inputFile' => 'inputURL']);
$t->execute();
