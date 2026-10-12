<?php

use App\Models\Studio;

it('shows the week of a studio', function () {
    $studio = Studio::factory()->withWeek()->create();

    $this->get(route('timetable', $studio))->assertOk()->assertSee('This week at');
});
