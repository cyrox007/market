<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AboutResource;
use App\Models\Page\AboutPage;
use Illuminate\Http\JsonResponse;

class AboutController extends Controller
{
    public function show(): JsonResponse
    {
        $aboutPage = AboutPage::cached('about_page', function () {
            return AboutPage::getInstance()
                ->load(['teamMembers', 'advantages']);
        });

        return response()->json([
            'about' => new AboutResource($aboutPage),
        ]);
    }
}




