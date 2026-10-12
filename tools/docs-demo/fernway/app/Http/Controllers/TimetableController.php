<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Studio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TimetableController
{
    public function __invoke(Studio $studio): View
    {
        DB::enableQueryLog();

        $sessions = ClassSession::query()
            ->whereHas('classType', fn ($query) => $query->where('studio_id', $studio->id))
            ->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->where('cancelled', false)
            ->orderBy('starts_at')
            ->get();

        dump($sessions->count());

        $week = $sessions->map(fn (ClassSession $session) => [
            'id' => $session->id,
            'starts_at' => $session->starts_at->format('Y-m-d H:i:s'),
            'room' => $session->room,
            'left' => $session->spotsLeft(),
        ]);

        dump($week->filter(fn (array $row) => $row['left'] <= 5)->take(3)->values()->all());

        if (count(DB::getQueryLog()) > 25) {
            Log::warning('Slow query on timetable', ['studio' => $studio->slug, 'queries' => count(DB::getQueryLog())]);
        }

        return view('timetable', ['studio' => $studio, 'week' => $week]);
    }
}
