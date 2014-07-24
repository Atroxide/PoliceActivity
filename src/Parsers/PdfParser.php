<?php

namespace Atroxide\PoliceActivity\Parsers;

use Atroxide\PoliceActivity\Call;
use Atroxide\PoliceActivity\ParserInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Font;
use Smalot\PdfParser\Object;
use Smalot\PdfParser\Page;

/**
 * Call scraper for the PDF file-format provided by Miller County Police.
 *
 * @implements ParserInterface
 * @package    Atroxide\PoliceActivity\Parsers
 * @author     Mark Dowdle <texasmd91@gmail.com>
 */
class PdfParser implements ParserInterface
{

    private $logger;

    public function __construct($logger = null)
    {
        if (!$logger instanceof LoggerInterface) {
            $logger = new NullLogger();
        }

        $this->logger = $logger;
    }

    public function getCalls($fileName, $mailId)
    {

        $parser = new \Smalot\PdfParser\Parser();
        try {

            $pdf = $parser->parseFile($fileName);

            $objects = $pdf->getObjects();

            $calls = array();
            foreach ($objects as $object) {
                /* @var $object Object */
                //echo 'Content: ' . $object->getContent() . '<br />';
                $text = self::getText($object, null, $pdf);
                if (strlen(trim($text)) > 0) {

                    $re = "/([0-9]{1,2})\\/([0-9]{1,2})\\/([0-9]{4}) ([0-9]{1,2}):([0-9]{1,2}):([0-9]{1,2}) (AM|PM)\\t([^\\t]+)\\t([^\\t]+)\\t([^\\n]+)\\n/";
                    if (!preg_match_all($re, $text, $matches, PREG_SET_ORDER)) {
                        $this->logger->error('PdfParser unsuccessful: preg_match_all returned false/null');
                        continue;
                    }

                    foreach ($matches as $match) {
                        $call = new Call(Call::PDF);

                        $date = new \DateTime();
                        $date->setDate($match[3], $match[1], $match[2]);
                        $date->setTime(($match[7] == 'PM' ? $match[4] + 12 : $match[4]), $match[5], $match[6]);
                        $call->setTime($date);

                        $call->setMailId($mailId);
                        $call->setAgency('MILLER COUNTY');
                        $call->setType($match[9]);
                        $call->setAddress($match[10]);
                        $calls[] = $call;
                    }
                }
            }

            $this->logger->info('PdfParser found ' . count($calls) . ' calls from mailId ' . $mailId);

            return $calls;
        } catch (\Exception $e) {
            $this->logger->error(
                'PdfParser exception from mailId ' . $mailId . ': ' . $e->getMessage(),
                (array) $e
            );

            return false;
        }
    }

    public static function getText(Object $object, Page $page = null, Document $pdf)
    {
        $text                = '';
        $sections            = $object->getSectionsText($object->getContent());
        $current_font        = new Font($pdf);
        $current_position_td = array('x' => false, 'y' => false);
        $current_position_tm = array('x' => false, 'y' => false);

        foreach ($sections as $section) {

            $commands = $object->getCommandsText($section);

            foreach ($commands as $command) {

                switch ($command[Object::OPERATOR]) {
                    // set character spacing
                    case 'Tc':
                        break;

                    // move text current point
                    case 'Td':
                        $args = preg_split('/\s/s', $command[Object::COMMAND]);
                        $y    = array_pop($args);
                        $x    = array_pop($args);
                        if ((floatval($x) <= 0) || ($current_position_td['y'] !== false && floatval($y) < floatval(
                                    $current_position_td['y']
                                ))
                        ) {
                            // vertical offset
                            $text .= "\n";
                        } elseif ($current_position_td['x'] !== false && floatval($x) > floatval(
                                $current_position_td['x']
                            )
                        ) {
                            // horizontal offset
                            $text .= ' ';
                        }
                        $current_position_td = array('x' => $x, 'y' => $y);
                        break;

                    // move text current point and set leading
                    case 'TD':
                        $args = preg_split('/\s/s', $command[Object::COMMAND]);
                        $y    = array_pop($args);
                        $x    = array_pop($args);
                        if (floatval($y) < 0) {
                            $text .= "\n";
                        } elseif (floatval($x) <= 0) {
                            $text .= ' ';
                        }
                        break;

                    case 'Tf':
                        list($id,) = preg_split('/\s/s', $command[Object::COMMAND]);
                        $id = trim($id, '/');
                        //$current_font = $page->getFont($id);
                        break;

                    case "'":
                    case 'Tj':
                        $command[Object::COMMAND] = array($command);
                    // no break
                    case 'TJ':
                        // Skip if not previously defined, should never happened.
                        if (is_null($current_font)) {
                            // Fallback
                            // TODO : Improve
                            $text .= $command[Object::COMMAND][0][Object::COMMAND];
                            continue;
                        }

                        $sub_text = $current_font->decodeText($command[Object::COMMAND]);
                        $text .= $sub_text;
                        break;

                    // set leading
                    case 'TL':
                        $text .= ' ';
                        break;

                    case 'Tm':
                        $args = preg_split('/\s/s', $command[Object::COMMAND]);
                        $y    = array_pop($args);
                        $x    = array_pop($args);
                        if ($current_position_tm['y'] !== false) {
                            $delta = abs(floatval($y) - floatval($current_position_tm['y']));
                            if ($delta > 10) {
                                $text .= "\n";
                            }
                        }
                        if ($current_position_tm['x'] !== false) {
                            $delta = abs(floatval($x) - floatval($current_position_tm['x']));
                            if ($delta > 10) {
                                $text .= "\t";
                            }
                        }
                        $current_position_tm = array('x' => $x, 'y' => $y);
                        break;

                    // set super/subscripting text rise
                    case 'Ts':
                        break;

                    // set word spacing
                    case 'Tw':
                        break;

                    // set horizontal scaling
                    case 'Tz':
                        $text .= "\n";
                        break;

                    // move to start of next line
                    case 'T*':
                        $text .= "\n";
                        break;

                    case 'Da':
                        break;

                    case 'Do':
                        if (!is_null($page)) {
                            $args = preg_split('/\s/s', $command[Object::COMMAND]);
                            $id   = trim(array_pop($args), '/ ');
                            if ($xobject = $page->getXObject($id)) {
                                $text .= $xobject->getText($page);
                            }
                        }
                        break;

                    case 'rg':
                    case 'RG':
                        break;

                    case 're':
                        break;

                    case 'co':
                        break;

                    case 'cs':
                        break;

                    case 'gs':
                        break;

                    case 'en':
                        break;

                    case 'sc':
                    case 'SC':
                        break;

                    case 'g':
                    case 'G':
                        break;

                    case 'V':
                        break;

                    case 'vo':
                    case 'Vo':
                        break;

                    default:
                }
            }
        }

        return $text . ' ';
    }
}