<?php

namespace Atroxide\PoliceActivity;

interface ParserInterface
{

    public function __construct($logger);

    public function getCalls($fileName, $mailId);
}