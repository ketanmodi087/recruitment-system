<?php

namespace App\Listeners;

use App\Events\AgencyDisabled;
use App\Models\Agency;
use Illuminate\Support\Facades\Auth;

class LogoutUsersAssociatedWithAgency
{
    public function handle(AgencyDisabled $event)
    {
        // Log out users associated with the disabled agency

        $agency = Agency::where('id', $event->agency->id)->get();
        Auth::guard('web')->logoutOtherDevices($agency[0]->password);
    }
}
