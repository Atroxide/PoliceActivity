<?php

namespace Atroxide\PoliceActivity\Parsers;

use Atroxide\PoliceActivity\Call;
use Atroxide\PoliceActivity\ParserInterface;
use PHPExcel_IOFactory;
use PHPExcel_Shared_Date;

require dirname(dirname(__dir__)) . '\vendor\autoload.php';

class XlsParser implements ParserInterface
{

    public static function getCalls($fileName, $mailId, $logger)
    {

        $objPHPExcel = PHPExcel_IOFactory::load($fileName);
        $sheetData   = $objPHPExcel->getActiveSheet()->toArray(null, true, false, false);

        $calls = array();
        foreach (array_splice($sheetData, 3, -1) as $row) {
            $call = new Call(Call::XLS);

            $call->setTime(new \DateTime(date('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($row[0])) . ' ' . $row[3]));
            $call->setMailId($mailId);
            $call->setAgency($row[4]);
            $call->setType($row[5]);
            $call->setDisposition($row[6]);
            $call->setAddress($row[7]);
            $calls[] = $call;
        }

        return $calls;
    }
}