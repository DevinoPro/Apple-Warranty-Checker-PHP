<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use thiagoalessio\TesseractOCR\TesseractOCR;
use PHPHtmlParser\Dom;

class AppleWarrantyChecker
{
    const BASE_URL = 'https://checkcoverage.apple.com/';

    private $client;
    private $jar;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
                'TE' => 'Trailers',
            ],
        ]);
        $this->jar = new CookieJar();
    }

    public function getAppleWarrantyStatus($serialOrImei)
    {
        // Step 1: Get the initial page
        $initial_response = $this->client->get('', ['cookies' => $this->jar]);
        $initial_html = (string)$initial_response->getBody();

        // Step 2: Extract form data and captcha image
        $form_data = $this->extractFormData($initial_html);

        // Step 3: Solve the captcha
        $captcha_solution = $this->solve_captcha($form_data['captcha_data']);

        // Step 4: Submit the form with the serial number and captcha solution
        $check_response = $this->submitWarrantyForm($serialOrImei, $captcha_solution, $form_data['form_action'], $form_data['form_data']);

        // Step 5: Parse the warranty status from the response
        $check_html = (string)$check_response->getBody();
        $dom = new Dom();
        $dom->load($check_html);

        if ($this->isSuccessfulResponse($dom)) {
            $status = $this->parseWarrantyStatus($dom);
            return $status;
        } else {
            throw new Exception('Failed to retrieve warranty status.');
        }
    }

    private function extractFormData($html)
    {
    $dom = new Dom();
    $dom->load($html);

    $form = $dom->find('form', 0);
    $form_action = $form->getAttribute('action');

    $captcha_img = $dom->find('img', 1);
    $captcha_url = self::BASE_URL . $captcha_img->getAttribute('src');

    $response = $this->client->get($captcha_url, ['cookies' => $this->jar]);
    $captcha_data = (string)$response->getBody();

    $form_data = [];
    foreach ($form->find('input[type="hidden"]') as $input) {
        $name = $input->getAttribute('name');
        $value = $input->getAttribute('value');
        $form_data[$name] = $value;
    }

    return [
        'form_action' => $form_action,
        'form_data' => $form_data,
        'captcha_data' => $captcha_data,
    ];
 }


  private function solve_captcha($captcha_data)
  {
      $captcha_blocks = $this->get_captcha_blocks($captcha_data);

      $captcha_solution = '';
      foreach ($captcha_blocks as $block) {
          ob_start();
          imagepng($block);
          $block_data = ob_get_contents();
          ob_end_clean();

          $ocr = new TesseractOCR();
          $ocr->imageData($block_data, strlen($block_data));
          $text = $ocr->run();

          $captcha_solution .= $this->process_captcha_text($text);
      }

      return $captcha_solution;
  }

  private function get_captcha_blocks($image_data)
  {
      $im = imagecreatefromstring($image_data);

      $width = imagesx($im);
      $height = imagesy($im);

      $blocks = [];
      for ($y = 0; $y < $height; $y += 25) {
          for ($x = 0; $x < $width; $x += 25) {
              $block = imagecrop($im, ['x' => $x, 'y' => $y, 'width' => 25, 'height' => 25]);
              $blocks[] = $block;
          }
      }

      return $blocks;
  }

  private function process_captcha_text($text)
  {
      return preg_replace('/\W/', '', $text);
  }

  private function submitWarrantyForm($serialOrImei, $captcha_solution, $form_action, $form_data)
  {
      $form_data['sn'] = $serialOrImei;
      $form_data['captcha_input'] = $captcha_solution;

      $response = $this->client->post($form_action, [
          'form_params' => $form_data,
          'cookies' => $this->jar,
      ]);

      return $response;
  }

  private function isSuccessfulResponse($dom)
  {
      return $dom->find('div[id="Title"] h1', 0)->text === 'Valid Purchase Date';
  }

  private function parseWarrantyStatus($dom)
  {
      $status = [];

      foreach ($dom->find('div[id="section"] div[class="row"]') as $row) {
          $key = trim($row->find('div[class="col-xs-12 col-sm-6"]', 0)->text);
          $value = trim($row->find('div[class="col-xs-12 col-sm-6"]', 1)->text);

          $status[$key] = $value;
      }

      return $status;
  }
   
}
