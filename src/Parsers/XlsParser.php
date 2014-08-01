<?php

namespace Atroxide\PoliceActivity\Parsers;

use Atroxide\PoliceActivity\Call;
use Atroxide\PoliceActivity\ParserInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Call scraper for the XLS file-format provided by Texarkana City Police.
 *
 * @implements ParserInterface
 * @package    Atroxide\PoliceActivity\Parsers
 * @author     Mark Dowdle <texasmd91@gmail.com>
 */
class XlsParser implements ParserInterface
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
        try {
            $objPHPExcel = \PHPExcel_IOFactory::load($fileName);
            $sheetData   = $objPHPExcel->getActiveSheet()->toArray(null, true, false, false);

            $calls = array();
            foreach (array_splice($sheetData, 3, -1) as $row) {

                $call = new Call(Call::XLS);

                $date  = \PHPExcel_Shared_Date::ExcelToPHPObject($row[0]);
                $times = explode(':', $row[3]);
                $date->setTime($times[0], $times[1], $times[2]);

                $call->setTime($date);
                $call->setMailId($mailId);
                $call->setAgency($row[4]);
                $call->setType($row[5]);
                $call->setDisposition($row[6]);
                $call->setAddress($row[7]);
                $calls[] = $call;
            }

            $this->logger->info('XlsParser found ' . count($calls) . ' calls from mailId ' . $mailId);

            return $calls;
        } catch (\PHPExcel_Reader_Exception $e) {
            $this->logger->error(
                'XlsParser exception from mailId ' . $mailId . ': ' . $e->getMessage(), (array) $e
            );

            return false;
        }
    }
}