<?php
namespace Grzegab\Wkhtmltopdf;
class WkhtmltopdfException extends \Exception
{
    public function __construct($problem)
    {
        parent::__construct("Wkhtmltopdf problem: $problem.");
    }
}