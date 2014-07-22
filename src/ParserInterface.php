<?php

namespace Atroxide\PoliceActivity;

/**
 * Interface ParserInterface
 *
 * @package    Atroxide\PoliceActivity
 * @author     Mark Dowdle <texasmd91@gmail.com>
 */
interface ParserInterface
{

    public function __construct($logger);

    public function getCalls($fileName, $mailId);
}