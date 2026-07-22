<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgencyDisabled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $agency;

    public function __construct($agency)
    {
        $this->agency = $agency;
    }
}
