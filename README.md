# Apple-Warranty-Checker-PHP
PHP script for checking Apple device warranty.

Requirements:

Before you use this code, make sure you have installed the following dependencies:

    Guzzle, for making HTTP requests: composer require guzzlehttp/guzzle
    PHP Simple HTML DOM Parser, for parsing HTML: composer require paquettg/php-html-parser
    Tesseract OCR for PHP, for solving CAPTCHAs: composer require thiagoalessio/tesseract_ocr
To use this code, create an instance of the `AppleWarrantyChecker` class and call the `getAppleWarrantyStatus` method with a serial number or IMEI as its argument:

```php
$checker = new AppleWarrantyChecker();
$status = $checker->getAppleWarrantyStatus('SERIAL_OR_IMEI');
print_r($status);
