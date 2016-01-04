<?php
namespace Grzegab\Wkhtmltopdf;

use App\Controller\AppController;
use Cake\Network\Response;
use Cake\Core\Configure;

/**
 * PDF generator for wkhtmltopdf (requires wkhtmltopdf binary installed)
 */
class PdfGenerator extends AppController
{
    /**
     * @var AppController   Creator used to render html and keep parent values
     */
    private $creatorClass;
    /**
     * @var bool    does pdf should be generated from url
     */
    protected $generateFromUrl = false;
    /**
     * @var bool    does pdf should be generated from html (default option)
     */
    protected $generateFromHtml = true;
    /**
     * @var string  value of url that PDF should be generated from
     */
    protected $url;
    /**
     * @var string  html code that PDF should be generated from
     */
    protected $html;
    /**
     * @var bool    xvfb for run wkhtmltopdf in linux
     */
    protected $enableXvfb = true;
    /**
     * @var string  name of executable for xvfb
     */
    protected $xvfbRunBinary = 'xvfb-run';
    /**
     * @var string  additional options for xvfb
     */
    protected $xvfbRunOptions = '-a -s "-screen 0 1024x678x16"';
    /**
     * @var string  path to wkhtmltopdf binary
     */
    protected $wkhtmltopdfBinary = '/usr/local/bin/wkhtmltopdf';
    /**
     * @var bool    save pdf file in webroot/ folder
     */
    protected $pdfSave = false;
    /**
     * @var string  path for saving in webroot/ folder
     */
    protected $pdfSavePath = 'pdf/';
    /**
     * @var string  name of output file (without ".pdf")
     */
    protected $pdfName = 'output';
    /**
     * @var bool    force to download file (cake response with file not string)
     */
    protected $pdfDownload = true;
    /**
     * @var string  name of view file located in Template/Layout/
     */
    protected $layout;
    /**
     * @var string  name of view file located in Template/ folder
     */
    protected $template;
    /**
     * @var string  encoding for PDF file
     */
    protected $encoding = 'UTF-8';
    /**
     * @var array   wkhtmltopdf options
     */
    protected $advancedConfig = [];
    /**
     * @var string  name of pdf tmp file (set by default)
     */
    private $pdfTmpFile;

    /**
     * @param AppController $creator    Controller object that PDF will be created from
     * @throws \Exception     If there is no object passed while creating
     */
    public function __construct(AppController $creator)
    {
        parent::__construct();
        if(null === $creator) {
            throw new \Exception('PDF class must inject AppController class (preferred $this variable)');
        }
        $this->creatorClass = $creator;
        $this->checkGlobalConfig();
    }

    /**
     * Main class for generating PDF file
     * @return string                   CakePHP response
     * @throws \Exception     if file cannot be saved
     */
    public function generatePDF()
    {
        if($this->generateFromHtml && empty($this->html)){
            $this->html = $this->renderHtml();
        }

        $cmd = $this->buildCommand();

        list($stdout, $stderr, $status) = $this->executeCommand($cmd);

        $this->checkStatus($status, $stdout, $stderr, $cmd);

        $response = new Response();

        if($this->pdfSave) {
            $pdfContent = file_get_contents($this->pdfTmpFile);
            if (!file_exists(WWW_ROOT . $this->pdfSavePath)) {
                mkdir(WWW_ROOT . $this->pdfSavePath, 0777, true);
            }
            $pdfFile = WWW_ROOT . $this->pdfSavePath . $this->pdfName . '.pdf';
            if(!file_put_contents($pdfFile, $pdfContent)) {
                throw new \Exception('File cannot be saved in location: '.WWW_ROOT . $this->pdfSavePath);
            }
            $response->type('txt');
            $response->charset($this->encoding);
            $response->body('webroot/' . $this->pdfSavePath . $this->pdfName . '.pdf');
        }

        if($this->pdfDownload) {
            $response->type('pdf');
            $response->charset($this->encoding);
            $response->file($this->pdfTmpFile, ['download' => true, 'name' => $this->pdfName . '.pdf']);
        }
        return $response;
    }

    private function buildCommand()
    {
        if(!is_executable($this->wkhtmltopdfBinary)) {
            throw new \Exception('Cannot run wkhtmltopdf - check executable');
        }

        $command = '';
        $advancedConfig = '';

        if($this->enableXvfb){
            $command = $this->xvfbRunBinary . ' ' . $this->xvfbRunOptions . ' ';
        }

        $command .= $this->wkhtmltopdfBinary ;

        if($this->advancedConfig){
            foreach($this->advancedConfig as $value) {
                $advancedConfig .= " " . $value;
            }
            $command .= ' ' . ltrim($advancedConfig);
            $command .= ' --encoding ' . $this->encoding;
        }

        if($this->generateFromUrl){
            $command .= ' ' . $this->url . ' ' ;
        } elseif($this->generateFromHtml) {
            $command .= ' - ';
        } else {
            throw new \Exception('Have no source to generate from');
        }
        $this->pdfTmpFile = $this->createTmpFile($this->pdfName, 'pdf');
        $command .= $this->pdfTmpFile;
        return $command;
    }

    /**
     * @return string Created html
     */
    private function renderHtml()
    {
        $layout = ($this->layout)?:$this->creatorClass->layout;
        if(!empty($this->creatorClass->viewBuilder()->template())) {
            $viewFile = $this->creatorClass->viewBuilder()->template();
        } else {
            throw new \Exception('Please set template file');
        }
        return $this->creatorClass->render($viewFile, $layout)->body();
    }

    /**
     * @param string $cmd               the command which will be executed to generate the pdf
     * @return array
     *
     * @throws \Exception     if any error
     */
    public function executeCommand($cmd)
    {
        $stdout = null;
        $stderr = null;
        $status = null;
        $pipes = [];

        //0 => stdin (writable), 1 => stdour (readable), 2 => stderr (readable)
        $descriptorspec = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptorspec, $pipes);

        if (!is_resource($proc)) {
            throw new \Exception('No resource found while executing command');
        }
        fwrite($pipes[0], $this->html);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $status = proc_close($proc);

        return array($stdout, $stderr, $status);
    }

    /**
     * @param integer $status           Current status
     * @param string $stdout            Output pipe
     * @param string $stderr            Error pipe
     * @param string $cmd               Executed command
     * @throws \Exception     if any error
     */
    private function checkStatus($status, $stdout, $stderr, $cmd)
    {
        if (0 !== $status) {
            throw new \Exception(sprintf(
                'Problem while executing command (%s):' . "\n"
                . 'stderr: "%s"' . "\n" . 'stdout: "%s"' . "\n",
                $cmd, $stderr, $stdout));
        }
    }

    /**
     * @param string $filename
     * @param string $extension in this case pdf would be good
     * @param string $content   of files
     * @return string           location of file (with content)
     */
    private function createTmpFile($filename, $extension, $content = null)
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid($filename) . '.' . $extension;

        if (null !== $content) {
            file_put_contents($file, $content);
        }

        return $file;
    }

    private function checkGlobalConfig()
    {
        $availableOptions = [
            'enableXvfb',
            'xvfbRunBinary',
            'xvfbRunOptions',
            'wkhtmltopdfBinary',
            'encoding',
            'layout'
        ];
        $globalConfig = Configure::read('wkhtmltopdf');
        if(!is_null($globalConfig)){
            foreach ($globalConfig as $k => $v) {
                if(in_array($k, $availableOptions)) {
                    $this->{$k} = $v;
                }
            }
        }
    }

    /**
     * @return string   list all settings and return as a string
     */
    public function debugPDF()
    {
        $toReturn = '<pre>';
        $toReturn .= 'xvfb is ';
        $toReturn .= ($this->enableXvfb)?'enabled: '.$this->xvfbRunBinary." ".$this->xvfbRunOptions:'disabled';
        $toReturn .= "\n";
        $toReturn .= 'wkhtmltopdf binary path is: '.$this->wkhtmltopdfBinary."\r\n";
        $toReturn .= 'PDF save on disk: ';
        $toReturn .= ($this->pdfSave)?'Yes: '.$this->pdfSavePath.$this->pdfName:'NO';
        $toReturn .= "\n";
        $toReturn .= 'PDF force download: ';
        $toReturn .= ($this->pdfDownload)?'Yes:':'NO';
        $toReturn .= "\n";
        $toReturn .= 'Additional settings for wkhtmltopdf: ';
        foreach ($this->advancedConfig as $setting) {
            $toReturn .= $setting;
            $toReturn .= "\n";
        }
        $toReturn .= '</pre>';
        return (string)$toReturn;
    }

    /**
     * @param string $url   Url for PDF to be generated from
     * @return $this
     */
    public function generateFromUrl($url)
    {
        $this->generateFromUrl = true;
        $this->generateFromHtml = false;
        $this->url = $url;
        return $this;
    }

    /**
     * @param null $html    optional HTML that PDF should be generated form
     * @return $this
     */
    public function generateFromHtml($html = null)
    {
        if(!is_null($html)){
            $this->html = $html;
        }
        $this->generateFromUrl = false;
        $this->generateFromHtml = true;
        return $this;
    }

    /**
     * @param boolean $boolean  enable xvfb
     * @param string $exec        name of xvfb executable in system (default: xvfb-run)
     * @param string $options     xvfb additional options
     * @return $this
     */
    public function xvfb($boolean, $exec = null, $options = null)
    {
        $this->enableXvfb = $boolean;
        if(true === $boolean){
            if(!is_null($exec)) {
                $this->xvfbRunBinary = $exec;
            }
            if(!is_null($options)) {
                $this->xvfbRunOptions = $options;
            }
        }
        return $this;
    }

    /**
     * @param string $path     path for wkhtmltopdf exectutable (default
     * @return $this
     */
    public function setWkhtmltopdfBinary($path)
    {
        $this->wkhtmltopdfBinary = $path;
        return $this;
    }

    /**
     * @param string $name  of PDF file withou ".pdf"
     * @param string $path  if default "pdf/" want to be changed
     * @return $this
     */
    public function savePdf($name, $path = null)
    {
        $this->pdfSave = true;
        $this->pdfName = $name;
        if(!is_null($path)) {
            $this->pdfSavePath = $path;
        }
        return $this;
    }

    /**
     * Does pdf should be downloaded
     * @return $this
     */
    public function downloadDisabled()
    {
        $this->pdfDownload = false;
        return $this;
    }

    /**
     * @param string $name  of layout file (can be path)
     * @return $this
     */
    public function setLayout($name)
    {
        $this->layout = $name;
        return $this;
    }

    /**
     * @param string $name  of tempalte file (can be path)
     * @return $this
     */
    public function setTemplate($name)
    {
        $this->template = $name;
        return $this;
    }

    /**
     * @param string $encoding  set encoding for pdf (defailt id UTF-8)
     * @return $this
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
        return $this;
    }

    /**
     * @param string $value The config for wkhtmltopdf
     *
     * @return object $this
     */
    public function wkhtmltopdf($value)
    {
        $this->advancedConfig[] = $value;
        return $this;
    }

    /**
     * @param string $name  of PDF file
     * @return $this
     */
    public function setName($name)
    {
        $this->pdfName = $name;
        return $this;
    }

}
