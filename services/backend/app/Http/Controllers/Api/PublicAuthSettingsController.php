<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Settings\PublicAuthSettings;
use Illuminate\Http\JsonResponse;

class PublicAuthSettingsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $settings = PublicAuthSettings::current();

        return response()->json(['data' => [
            'registration_enabled' => $settings->registration_enabled,
            'login_enabled' => $settings->login_enabled,
        ]])->header('Cache-Control', 'no-store, private');
    }
}
