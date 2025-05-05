<?php
namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Jenssegers\Agent\Agent;
use Stevebauman\Location\Facades\Location;

trait Loggable
{
    //
    public function logActivity($logName, $message, $properties = [])
    {
        $ip = request()->ip();
        // $ip = '103.239.147.187';
        $location  = Location::get($ip);
        $agent     = new Agent();
        $userAgent = request()->header('User-Agent');

        // Default properties
        $defaultProperties = [
            'created_by'       => Auth::user()->name ?? null,
            'created_by_email' => Auth::user()->email ?? null,
            'ip'               => $ip ?? null,
            'country'          => $location->countryName ?? null,
            'city'             => $location->cityName ?? null,
            'region'           => $location->regionName ?? null,
            'latitude'         => $location->latitude ?? null,
            'longitude'        => $location->longitude ?? null,
            'timezone'         => $location->timezone ?? null,
            'user_agent'       => $userAgent,
            'device'           => $agent->device(),
            'platform'         => $agent->platform(),
            'platform_version' => $agent->version($agent->platform()),
            'browser'          => $agent->browser(),
            'browser_version'  => $agent->version($agent->browser()),
        ];

        // Merge properties
        $allProperties = array_merge($defaultProperties, $properties);

        // Log activity
        activity($logName)
            ->causedBy(Auth::user() ?? null)
            ->withProperties($allProperties)
            ->log($message);
    }
}