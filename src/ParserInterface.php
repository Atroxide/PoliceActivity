<?php

namespace Atroxide\PoliceActivity;

interface ParserInterface
{

    public static function getCalls($fileName, $mailId, $logger);
}