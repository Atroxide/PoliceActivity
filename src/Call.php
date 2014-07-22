<?php

namespace Atroxide\PoliceActivity;

class Call
{

    const   XLS = 0, PDF = 1;

    private $sourceType;
    private $mailId;
    private $time;
    private $agency;
    private $type;
    private $disposition;
    private $address;

    /**
     * @param int $callType
     */
    public function __construct($sourceType)
    {
        $this->sourceType = $sourceType;
    }

    /**
     * @return string|null
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param string|null $address
     */
    public function setAddress($address)
    {
        $this->address = $address;
    }

    /**
     * @return string|null
     */
    public function getDisposition()
    {
        return $this->disposition;
    }

    /**
     * @param string|null $disposition
     */
    public function setDisposition($disposition)
    {
        $this->disposition = $disposition;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getSourceType()
    {
        return $this->sourceType;
    }

    /**
     * @return string|null
     */
    public function getAgency()
    {
        return $this->agency;
    }

    /**
     * @param string $agency
     */
    public function setAgency($agency)
    {
        $this->agency = $agency;
    }

    /**
     * @return int
     */
    public function getMailId()
    {
        return $this->mailId;
    }

    /**
     * @param int $attachId
     */
    public function setMailId($mailId)
    {
        $this->mailId = $mailId;
    }

    /**
     * @return \DateTime
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * @param \DateTime $time
     */
    public function setTime(\DateTime $time)
    {
        $this->time = $time;
    }
}