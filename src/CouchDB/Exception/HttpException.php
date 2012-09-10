<?php

namespace CouchDB\Exception;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class HttpException extends \Exception
{
    public static function connectFailure($host, $port, $errno, $errstr)
    {
        return new static(sprintf(
            'Unable to connect to %s:%d: [%s] %s',
            $host, $port, $errno, $errstr
        ));
    }
}
