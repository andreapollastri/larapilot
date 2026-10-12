<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitlistController
{
    public function store(Request $request, ClassSession $session): JsonResponse
    {
        $entry = $session->waitlist()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['position' => (int) $session->waitlist()->max('position') + 1],
        );

        return response()->json(['position' => $entry->position], 201);
    }
}
