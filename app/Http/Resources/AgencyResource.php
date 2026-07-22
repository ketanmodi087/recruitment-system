<?php

namespace App\Http\Resources;

use App\Models\SocialIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class AgencyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fb = SocialIntegration::where('agency_id', $this->id)->where('type', 'facebook')->first();
        $ln = SocialIntegration::where('agency_id', $this->id)->where('type', 'linkedin')->first();
        if ($this->agency_id && $this->agency_id != null) {
            $agencyNames  = DB::table('agencies')->where('id', $this->agency_id)->first();
            $agencyName = $agencyNames->name;
            $profilePic = $agencyNames->profile;
        } else {
            $agencyName = $this->name;
            $profilePic = $this->profile;
        }
        if ($fb) {
            $fbintegrate = true;
        } else {
            $fbintegrate = false;
        }

        if ($ln) {
            $lnintegrate = true;
        } else {
            $lnintegrate = false;
        }

        return [
            'user_id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role_id' => $this->role_id,
            'profile_pic' => $profilePic,
            // 'token' => $this->createToken("Token")->plainTextToken,
            "agency_name" => $agencyName,
            'roles' => $this->roles->pluck('name') ?? [],
            'roles_permissions' => $this->getPermissionsViaRoles()->pluck('name') ?? [],
            'permissions' => $this->permissions->pluck('name') ?? [],
            'integrate' => ["facebook" => $fbintegrate, "linkedin" => $lnintegrate]
        ];
    }
}
