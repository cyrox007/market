<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InteriorIdeaResource;
use App\Models\Page\InteriorIdea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InteriorIdeaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $ideas = InteriorIdea::cached('index', function () {
            return InteriorIdea::with(['hotspots.product.parentProduct'])
                ->active()
                ->ordered()
                ->get();
        });

        return InteriorIdeaResource::collection($ideas);
    }
}
