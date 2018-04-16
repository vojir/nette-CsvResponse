<?php

namespace Vojir\Responses\CsvResponse;

use Nette;
use Nette\SmartObject;

/**
 * Class SimpleCsvResponse
 * @package Vojir\Responses\CsvResponse
 * @author Stanislav Vojíř
 */
class SimpleCsvResponse implements Nette\Application\IResponse{

  use SmartObject;

  /** standard glues */
  const COMMA = ',';
  const SEMICOLON = ';';
  const TAB = ' ';

  /** @var string $bom */
  public $utf8Bom = '';
  /** @var bool $addHeading*/
  protected $addHeading;
  /** @var bool $includeBom */
  protected $includeBom;
  /** @var string $glue */
  protected $glue = self::SEMICOLON;
  /** @var string $enclosure */
  protected $enclosure = '"';
  /** @var string $escapeChar */
  protected $escapeChar = '\\';
  /** @var string $outputCharset*/
  protected $outputCharset = 'utf-8';
  /** @var string $contentType*/
  protected $contentType = 'text/csv';


  /** @var callable */
  protected $headingFormatter = null;


  /** @var callable */
  protected $dataFormatter;


  /** @var array */
  protected $data;


  /** @var string */
  protected $filename;


  /**
   * In accordance with Nette Framework accepts only UTF-8 input. For output @see setOutputCharset().
   *
   * @param array[]|\Traversable $data
   * @param string $filename
   * @param bool $addHeading whether add first row from data array keys (keys are taken from first row)
   * @param bool $includeBom
   * @throws \InvalidArgumentException
   */
  public function __construct($data, $filename = 'output.csv', $addHeading = true, $includeBom = false){
    $this->utf8Bom=chr(0xEF).chr(0xBB).chr(0xBF);
    if ($data instanceof \Traversable){
      $data = iterator_to_array($data);
    }
    if (!is_array($data)){
      throw new \InvalidArgumentException(
        __CLASS__ . ": data must be two dimensional array or instance of Traversable."
      );
    }
    $this->data = $data;
    $this->filename = $filename;
    $this->addHeading = $addHeading;
    $this->includeBom=$includeBom;
  }


  /**
   * Set value separator
   * @param string $glue
   * @return self
   * @throws \InvalidArgumentException
   */
  public function setGlue($glue){
    if (empty($glue) || preg_match('/[\n\r"]/s', $glue)){
      throw new \InvalidArgumentException(
        __CLASS__ . ": glue cannot be an empty or reserved character."
      );
    }
    $this->glue = $glue;
    return $this;
  }

  /**
   * @param string $enclosure
   * @return self
   * @throws \InvalidArgumentException
   */
  public function setEnclosure($enclosure){
    if (empty($glue) || preg_match('/[\n\r]/s', $glue)){
      throw new \InvalidArgumentException(
        __CLASS__ . ": enclosure char cannot be an empty or reserved character."
      );
    }
    $this->enclosure = $enclosure;
    return $this;
  }

  /**
   * @param $escapeChar
   * @return self
   */
  public function setEscapeChar($escapeChar){
    if (empty($glue) || preg_match('/[\n\r]/s', $glue)){
      throw new \InvalidArgumentException(
        __CLASS__ . ": escape char cannot be an empty or reserved character."
      );
    }
    $this->escapeChar = $escapeChar;
    return $this;
  }


  /**
   * @param string $charset
   * @return self
   */
  public function setOutputCharset($charset){
    $this->outputCharset = $charset;
    return $this;
  }


  /**
   * @param string $contentType
   * @return self
   */
  public function setContentType($contentType){
    $this->contentType = $contentType;
    return $this;
  }


  /**
   * If given, every value is formatted by given callback.
   * @param callable $formatter
   * @return self
   * @throws \InvalidArgumentException
   */
  public function setDataFormatter($formatter){
    if ($formatter !== null && !is_callable($formatter)){
      throw new \InvalidArgumentException(
        __CLASS__ . ": data formatter must be callable."
      );
    }
    $this->dataFormatter = $formatter;
    return $this;
  }

  /**
   * Sends response to output.
   *
   * @param Nette\Http\IRequest $httpRequest
   * @param Nette\Http\IResponse $httpResponse
   */
  public function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse){
    $httpResponse->setContentType($this->contentType, $this->outputCharset);
    $attachment = 'attachment';
    if (!empty($this->filename)){
      $attachment .= '; filename="' . $this->filename . '"';
    }
    $httpResponse->setHeader('Content-Disposition', $attachment);
    $data = $this->formatCsv();
    $httpResponse->setHeader('Content-Length', strlen($data));
    print $data;
  }


  protected function formatCsv(){
    if (empty($this->data)){
      return '';
    }
    ob_start();
    $buffer = fopen("php://output", 'w');
    // if output charset is not UTF-8
    $recode = strcasecmp($this->outputCharset, 'utf-8');
    if(!$recode && $this->includeBom){
      fputs($buffer, $this->utf8Bom);
    }
    foreach ($this->data as $n => $row){
      if ($row instanceof \Traversable){
        $row = iterator_to_array($row);
      }
      if (!is_array($row)){
        throw new \InvalidArgumentException(
          __CLASS__ . ": row $n must be array or instance of Traversable, " . gettype(
            $row
          ) . ' given.'
        );
      }
      if ($this->dataFormatter || $recode){
        foreach ($row as &$value){
          if ($this->dataFormatter){
            $value = call_user_func($this->dataFormatter, $value);
          }
          if ($recode){
            $value = iconv(
              'utf-8', "$this->outputCharset//TRANSLIT", $value
            );
          }
        }
      }
      fputcsv($buffer, $row, $this->glue, $this->enclosure, $this->escapeChar);
    }
    fclose($buffer);
    return ob_get_clean();
  }
}
