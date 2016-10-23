<?php

namespace NavJobs\Temporal;

class Blueprint extends \Illuminate\Database\Schema\Blueprint
{
    /**
     * Adds the required fields for a Temporal Model.
     */
    public function temporal()
    {
        $this->dateTime('valid_start');
        $this->dateTime('valid_end')->nullable();
    }
}