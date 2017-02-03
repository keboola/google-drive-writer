<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 03/02/2017
 * Time: 13:48
 */

namespace Keboola\GoogleDriveWriter\Writer;

interface WriterInterface
{
    public function process($file);
}
