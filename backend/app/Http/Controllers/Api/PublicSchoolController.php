<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\JsonResponse;

class PublicSchoolController extends Controller
{
    /**
     * Public, white-label school information for the branded sign-in page.
     */
    public function show(School $school): JsonResponse
    {
        return response()->json([
            'school' => [
                'name' => $school->name,
                'slug' => $school->slug,
                'logo' => $school->logo,
                'primary_color' => $school->primary_color,
                'timezone' => $school->timezone,
            ],
        ]);
    }
}
