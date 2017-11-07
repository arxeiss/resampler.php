<?php
/**
 * Part of Resampler.php - simple image resampler
 * 
 * @author Pavel@kutac.cz
 * @version 1.0
 */

namespace Resampler;

/**
 * Resampler file exception class
 */
class FileException extends Exception
{
    protected $filePath;

    public function __construct($message, $filePath = null)
    {
        parent::__construct($message.": ".$filePath);
        $this->filePath = $filePath;
    }

    public function getFilePath()
    {
        return $this->filePath;
    }
}
