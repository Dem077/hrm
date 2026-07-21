<?php

namespace App\Services\Adms;

class AdmsDoorCommandBuilder
{
    /**
     * Build ADMS payload to trigger door unlock relay.
     *
     * Tries AC_UNLOCK first (Push SDK). Some firmware accepts CONTROL DEVICE OP=29 instead.
     */
    public function unlockPayload(): string
    {
        return 'AC_UNLOCK';
    }

    public function unlockPayloadAlternative(): string
    {
        return 'CONTROL DEVICE PIN=0'.chr(9).'OP=29';
    }
}
