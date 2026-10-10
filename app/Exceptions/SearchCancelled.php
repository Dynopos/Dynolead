<?php

namespace App\Exceptions;

use RuntimeException;

/** The user cancelled the search while a job was working on it. Not an error. */
class SearchCancelled extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Carian dibatalkan.');
    }
}
