<?php

namespace Atroxide\PoliceActivity;

use Fetch\Attachment;
use Fetch\Message;
use Fetch\Server;
use Psr\Log\LoggerInterface;

class CallScraper
{

    private $logger;

    private $parserNS = '\\Atroxide\\PoliceActivity\\Parsers';
    private $parserMap = array(
        'application/pdf' => 'PdfParser',
        'application/x-pdf' => 'PdfParser',
        'application/vnd.pdf' => 'PdfParser',
        'text/pdf' => 'PdfParser',
        'application/vnd.ms-excel' => 'XlsParser',
        'application/msexcel' => 'XlsParser',
        'application/x-msexcel' => 'XlsParser',
        'application/x-ms-excel' => 'XlsParser',
        'application/x-excel' => 'XlsParser',
        'application/x-dos_ms_excel' => 'XlsParser',
        'application/xls' => 'XlsParser',
        'application/x-xls' => 'XlsParser',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XlsParser'
    );

    private $calls = array();

    public function __construct($serverPath, $port = 143, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->info("Scraper initialized");

        $this->fetch = new Server($serverPath, $port);
    }

    public function setAuthentication($username, $password)
    {
        $this->fetch->setAuthentication($username, $password);
    }

    public function setMailBox($mailbox = '')
    {
        $success = $this->fetch->setMailBox($mailbox);
        if (!$success) {
            $this->logger->critical("Mailbox '{$mailbox}' unable to be set.");
        }

        return $success;
    }

    public function getMailIds($amount)
    {
        return array_slice(imap_sort($this->fetch->getImapStream(), SORTARRIVAL, 1, SE_UID), 0, (int) $amount);
    }

    public function getCalls($mailIds)
    {
        $attachments = $this->getAttachments($mailIds);

        foreach ($attachments as $mailId => $attachment) {
            /* @var $attachment Attachment */

            if (isset($this->parserMap[$attachment->getMimeType()])) {


                $fileName = $this->saveToFile($attachment->getData());

                /* @var $parser ParserInterface */
                $parser = $this->parserNS . '\\' . $this->parserMap[$attachment->getMimeType()];

                $c = $parser::getCalls($fileName, $mailId, $this->logger);
                $this->calls = array_merge($this->calls, $c);

                $this->deleteFile($fileName);
            } else {
                $this->logger->warning(
                    'Attachment mimetype \'' . $attachment->getMimeType() . '\' not located in $parserMap'
                );
            }
        }

        return $this->calls;
    }

    private function saveToFile($data)
    {
        $tmpName = tempnam(sys_get_temp_dir(), "pa_");

        $handle = fopen($tmpName, "w");
        fwrite($handle, $data);
        fclose($handle);

        return $tmpName;
    }

    private function deleteFile($fileName)
    {
        unlink($fileName);
    }

    private function getAttachments($mailIds)
    {

        $messages = array_map(array($this->fetch, 'getMessageByUid'), $mailIds);

        $attachments = array();
        foreach ($messages as $message) {
            /* @var $message Message */
            $attachment = $message->getAttachments();

            if (isset($attachment) && count($attachment >= 1)) {
                $attachments[$message->getUid()] = $attachment[0];

                if (count($attachment) > 1) {
                    $this->logger->warning(
                        'There are multiple attachments on mail id ' . $message->getUid(),
                        $attachment
                    );
                }
            }
        }

        return $attachments;
    }
}
