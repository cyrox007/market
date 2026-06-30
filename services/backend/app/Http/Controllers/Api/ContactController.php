<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Settings\ContactSettings;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = ContactSettings::getInstance();

        return response()->json([
            'contact' => new ContactResource($settings),
        ]);
    }
}


