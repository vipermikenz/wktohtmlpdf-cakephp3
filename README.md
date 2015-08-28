PDF generator for CakePHP3 (wkhtmltopdf)
====================

Installation
--------------------

Install wkhtmltopdf on your system! (http://wkhtmltopdf.org)
do not use apt-get for this, download from website.

## Install by composer:
``` $
composer require grzegab/wkhtmltopdf
```

## Override default config:
In config/boostrap.php write configure:  
Configure::write('wkhtmltopdf.enableXvfb', false);  
Configure::write('wkhtmltopdf.encoding', 'UTF-8');  

Available options are:   
* 'enableXvfb' - boolean if xvfb should be used with wkhtmltopdf,  
* 'xvfbRunBinary' - binary of xvfb - default 'xvfb-run',  
* 'xvfbRunOptions' - options used in xvfb - default '-a -s "-screen 0 1024x678x16"',  
* 'wkhtmltopdfBinary' - path to binary - default '/usr/local/bin/wkhtmltopdf',  
* 'encoding' - name of encoding - default 'UTF-8',  
* 'layout' - name of template (located in Template/Layout/)

Usage
-------------------
Add namespace  on each controller that will use pdf generator:  
use Grzegab\Wkhtmltopdf\PdfGenerator;  
  
Create new action with return of PDF object:  
public function invoicePdf() {};

Build controller, setup view variables as you whis to page look like.  

Init component:  
$pdf = new PdfGenerator($this);  

Return PDF as response from controller:  
return $pdf->save('pdfName')->wkhtmltopdf('-O landscape')->generatePDF();

Can also build PDF file from URL address:  
return $pdf->generateFromUrl('http://www.google.com')->generatePDF();  

If you wish only to save PDF file (without downloading it as response):  
return $pdf->save('pdf_name')->downloadDisabled()->generatePDF();  
Response will be path of saved file.

#### Remember
All assets (images, js etc.) *MUST* have absolute path.  
Name of pdf file should be without extension "pdf".  


## Examples of usage

in src/Controller/InvoiceController.php:  
  
use Grzegab/Wkhtmltopdf;  
  
public function invoicePDF($id)   
{  
    // ... find invoice by id and other logic  
    $pdf = new PDF($this);  
    return $pdf->save('name0123', 'pdf/names')->wkhtmltopdf('-O landscape')->generatePDF();  
}  
  
When invoicePdf/{id} executed will have pdf file saved in webroot/pdf/names with name0123 and forced to download.

## List of avilable commands 
#### Basic Commands
All commands are chainable $pdf->command1()->command2()->command3()->generatePDF();  

Ending commands:  
* $pdf->debugPDF(); will return current settings for PDF generator  
* $pdf->generatePDF(); wll try to generate PDF file

Settings:    
*  $pdf->wkhtmltopdf(*string*);  add wkhtmltopdf option  
*  $pdf->setEncoding(*string*);  sets encoding for PDF (default UTF-8)  
*  $pdf->setTemplate(*string*);  sets view file (the one that should be used to generate PDF)  
*  $pdf->setLayout(*string*);    sets layout file  
*  $pdf->downloadDisabled();     do not force to download file  
*  $pdf->savePdf(*string1*, *string2* [optional]);  save PDF file with name (string1 - without ".pdf") and path (string2 - path is in webroot/)  
*  $pdf->setWkhtmltopdfBinary(*string1*);    path to binary - default '/usr/local/bin/wkhtmltopdf'  
*  $pdf->xvfb(*boolean*, *string1*, *string2*);    first parameter to enable or disable xvfb, second is name of binary (default xvfb-run) and third is additional options for xvfb (default: -a -s "-screen 0 1024x678x16")  
*  $pdf->generateFromHtml(*string*);    enter own html for PDF
*  $pdf->generateFromUrl(*string*);     enter url that PDF should be generated from

#### Wkhtmltopdf commands
List of all commands avilalble for wkhtmltopdf (http://wkhtmltopdf.org/usage/wkhtmltopdf.txt).  
Usage: return $pdf->wkhtmltopdf('-O landscape')->wkhtmltopdf('-l')->generatePDF();

## License
The MIT License (MIT). Please see [License File](https://github.com/grzegab/wktohtmlpdf-cakephp3/blob/master/LICENSE) for more information.











