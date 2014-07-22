<?php

namespace Atroxide\PoliceActivity;

use Fetch\Attachment;
use Fetch\Message;
use Fetch\Server;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class to scrape Texarkana's police-activity email attachments.
 *
 * @package    Atroxide\PoliceActivity
 * @author     Mark Dowdle <texasmd91@gmail.com>
 */
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

    /**
     * @param string               $serverPath IMAP Server port
     * @param int                  $port       IMAP server port
     * @param LoggerInterface|null $logger     A PSR-3 complaint logger
     */
    public function __construct($serverPath, $port = 143, $logger = null)
    {
        if (!$logger instanceof LoggerInterface) {
            $logger = new NullLogger();
        }

        $this->logger = $logger;
        $this->logger->info("Scraper intiialized");

        $this->fetch = new Server($serverPath, $port);
    }

    /**
     * @param string $username IMAP username
     * @param string $password IMAP password
     *
     */

    public function setAuthentication($username, $password)
    {
        $this->fetch->setAuthentication($username, $password);
    }

    /**
     * @param string $mailbox IMAP mailbox
     *
     * @return bool
     */
    public function setMailBox($mailbox = '')
    {
        $success = $this->fetch->setMailBox($mailbox);
        if (!$success) {
            $this->logger->critical("Mailbox '{$mailbox}' unable to be set.");
        }

        return $success;
    }

    /**
     * @param $amount Number of emails to return the unique identifiers for.
     *
     * @return int[] An array of email unique identifiers. May contain less than the requested amount.
     */
    public function getMailIds($amount)
    {
        return array_slice(imap_sort($this->fetch->getImapStream(), SORTARRIVAL, 1, SE_UID), 0, (int) $amount);
    }

    /**
     * @param int[] $mailIds An array of unique identifiers.
     *
     * @return \Atroxide\PoliceActivity\Call[] An array of Calls.
     */
    public function getCalls($mailIds)
    {
        $attachments = $this->getAttachments($mailIds);

        foreach ($attachments as $mailId => $attachment) {
            /* @var $attachment Attachment */

            if ($attachment instanceof Attachment) {

                if (isset($this->parserMap[$attachment->getMimeType()])) {

                    $fileName = $this->saveToFile($attachment->getData());

                    /* @var $parser ParserInterface */
                    $parserName = $this->parserNS . '\\' . $this->parserMap[$attachment->getMimeType()];

                    $parser = new $parserName($this->logger);
                    $calls  = $parser->getCalls($fileName, $mailId);

                    $this->calls = array_merge($this->calls, $calls);

                    $this->deleteFile($fileName);
                } else {
                    $this->logger->warning(
                        'Attachment mimetype \'' . $attachment->getMimeType() . '\' not located in $parserMap'
                    );
                }
            } else {
                $this->logger->warning(
                    'Unknown non-Attachment object in attachment array',
                    array('mailId' => $mailId, 'attachment' => $attachment)
                );
            }
        }

        $this->logger->info('Scraper returned  ' . count($this->calls) . ' calls total.');

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
