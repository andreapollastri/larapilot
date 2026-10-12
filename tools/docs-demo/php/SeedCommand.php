<?php

declare(strict_types=1);

namespace FernwayDemo;

use Faker\Factory as Faker;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The database of Fernway on a developer's machine at the end of the story: the migrations in the
 * batches they ran in, four beta studios, three weeks of classes, members, packs, payments, bookings,
 * and waitlists. Dates are those of the story, moved by DEMO_SHIFT_DAYS like every other date.
 *
 * Members are fictional (Faker en_GB, fixed seed); every address ends in .example.
 */
class SeedCommand extends Command
{
    protected $signature = 'fernway:demo-seed';

    protected $description = 'Migrate in batches and write the rows of the Fernway demo';

    private int $shift = 0;

    public function handle(): int
    {
        $this->shift = (int) (getenv('DEMO_SHIFT_DAYS') ?: 0);
        $this->migrate();
        $this->rows();

        return self::SUCCESS;
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse($time, 'UTC')->addDays($this->shift);
    }

    private function migrate(): void
    {
        $batches = [
            ['2026-09-08 13:20', ['0001_01_01_000000_create_users_table.php', '0001_01_01_000001_create_cache_table.php', '0001_01_01_000002_create_jobs_table.php']],
            ['2026-09-09 10:30', ['2026_09_09_101500_create_studios_and_instructors_tables.php']],
            ['2026-09-14 14:20', ['2026_09_15_143000_create_class_types_and_sessions_tables.php']],
            ['2026-09-15 09:50', ['2026_09_17_110000_create_bookings_table.php']],
            ['2026-09-22 10:00', ['2026_09_22_093000_create_membership_plans_and_memberships_tables.php']],
            ['2026-09-28 10:10', ['2026_09_28_160000_create_waitlist_entries_table.php']],
            ['2026-09-30 09:55', ['2026_09_30_150000_create_payments_table.php']],
        ];
        foreach ($batches as [$time, $files]) {
            Carbon::setTestNow($this->at($time));
            Artisan::call('migrate', ['--path' => array_map(fn ($f) => 'database/migrations/'.$f, $files), '--force' => true]);
        }
        Carbon::setTestNow();
    }

    private function rows(): void
    {
        $faker = Faker::create('en_GB');
        $faker->seed(20260907);
        $password = Hash::make('not-a-real-password');

        $people = [];
        $user = function (string $name, string $email, ?string $phone, string $created) use (&$people, $password): int {
            $id = DB::table('users')->insertGetId([
                'name' => $name, 'email' => $email, 'phone' => $phone, 'email_verified_at' => $this->at($created),
                'password' => $password, 'created_at' => $this->at($created), 'updated_at' => $this->at($created),
            ]);
            $people[$name] = $id;

            return $id;
        };

        $studios = [
            ['Tidal Yoga', 'tidal-yoga', 'Brighton', '#2f6b55', 'Erin Walsh', 120],
            ['Oak & Iron Strength', 'oak-and-iron', 'Bristol', '#7a4b2a', 'Nadia Brooks', 90],
            ['Northside Pilates', 'northside-pilates', 'Leeds', '#4b5e9a', 'Joanna Pike', 180],
            ['Harbour Boxing Club', 'harbour-boxing', 'Cardiff', '#9a3b3b', 'Rhys Morgan', 60],
        ];
        $instructors = [
            'tidal-yoga' => [['Tom Barker', 'lead'], ['Nia Osei', 'instructor'], ['Grace Holloway', 'instructor']],
            'oak-and-iron' => [['Wendy Davis', 'lead'], ['Tom Barker', 'instructor']],
            'northside-pilates' => [['Hana Ito', 'lead'], ['Chris Doyle', 'instructor']],
            'harbour-boxing' => [['Marcus Reid', 'lead']],
        ];
        $types = [
            'tidal-yoga' => [['Vinyasa Flow', 'all levels', 60, 20], ['Sunrise Hatha', 'gentle', 60, 18], ['Yin & Breath', 'gentle', 75, 16], ['Power Yoga', 'strong', 60, 20]],
            'oak-and-iron' => [['Strength Foundations', 'all levels', 60, 12], ['Mobility Flow', 'gentle', 45, 16], ['HIIT', 'strong', 45, 16], ['Barbell Club', 'strong', 75, 10]],
            'northside-pilates' => [['Reformer Basics', 'all levels', 50, 10], ['Mat Pilates', 'all levels', 55, 18], ['Reformer Flow', 'strong', 50, 10]],
            'harbour-boxing' => [['Boxing Fundamentals', 'all levels', 60, 18], ['Bag Work', 'strong', 45, 14]],
        ];
        $rooms = ['tidal-yoga' => ['Studio A', 'The Loft'], 'oak-and-iron' => ['Studio A', 'Rig Room'], 'northside-pilates' => ['Reformer Room', 'Mat Studio', 'Studio 3'], 'harbour-boxing' => ['Ring Hall']];
        $plans = [['5-class pack', 'pack', 5, 60, 5200], ['10-class pack', 'pack', 10, 90, 9500], ['Unlimited monthly', 'unlimited', null, 30, 11900]];

        $instructorIds = [];
        $typeIds = [];
        $planIds = [];
        foreach ($studios as $i => [$name, $slug, $city, $colour, $owner, $late]) {
            $created = sprintf('2026-09-%02d 1%d:%02d', 10 + $i, 1 + $i, 12 + 7 * $i);
            $ownerId = $user($owner, Str::slug($owner, '.').'@'.$slug.'.example', $faker->numerify('07700 9#####'), $created);
            $studioId = DB::table('studios')->insertGetId([
                'owner_id' => $ownerId, 'name' => $name, 'slug' => $slug, 'city' => $city, 'timezone' => 'Europe/London',
                'late_cancel_minutes' => $late, 'brand_color' => $colour, 'created_at' => $this->at($created), 'updated_at' => $this->at($created),
            ]);
            foreach ($instructors[$slug] as $j => [$person, $role]) {
                $uid = $people[$person] ?? $user($person, Str::slug($person, '.').'@coaches.example', $faker->numerify('07700 9#####'), $created);
                $invited = $this->at($created)->addHours(2 + $j);
                $instructorIds[$slug][] = DB::table('instructors')->insertGetId([
                    'studio_id' => $studioId, 'user_id' => $uid, 'display_name' => $person, 'role' => $role,
                    'bio' => $faker->sentence(14), 'invited_at' => $invited, 'accepted_at' => $invited->copy()->addHours(5 + 3 * $j),
                    'created_at' => $invited, 'updated_at' => $invited,
                ]);
            }
            foreach ($types[$slug] as [$type, $intensity, $minutes, $capacity]) {
                $typeIds[$slug][] = [DB::table('class_types')->insertGetId([
                    'studio_id' => $studioId, 'name' => $type, 'intensity' => $intensity, 'duration_minutes' => $minutes, 'default_capacity' => $capacity,
                    'created_at' => $this->at('2026-09-15 15:00'), 'updated_at' => $this->at('2026-09-15 15:00'),
                ]), $minutes, $capacity];
            }
            foreach ($plans as [$plan, $kind, $credits, $days, $price]) {
                $planIds[$slug][] = [DB::table('membership_plans')->insertGetId([
                    'studio_id' => $studioId, 'name' => $plan, 'kind' => $kind, 'credits' => $credits, 'valid_days' => $days,
                    'price_pence' => $price + 300 * $i, 'created_at' => $this->at('2026-09-23 10:00'), 'updated_at' => $this->at('2026-09-23 10:00'),
                ]), $credits, $days, $price + 300 * $i];
            }
        }

        // Members: four named in the docs and the mockups, the rest fictional from Faker.
        $members = [];
        foreach (['Lily Parker', 'Aisha Khan', 'Owen Hughes', 'Priti Shah'] as $n => $name) {
            $members[] = $user($name, Str::slug($name, '.').'@members.example', $faker->numerify('07700 9#####'), '2026-09-1'.(6 + $n).' 19:2'.$n);
        }
        for ($n = 0; $n < 56; $n++) {
            $name = $faker->firstName().' '.$faker->lastName();
            $members[] = $user($name, Str::slug($name, '.').'.'.$n.'@members.example', $faker->numerify('07700 9#####'), $faker->dateTimeBetween('2026-09-16', '2026-10-05')->format('Y-m-d H:i'));
        }

        // Packs and memberships, paid through Stripe Checkout from 1 October (US-010).
        $slugs = array_keys($instructors);
        foreach ($members as $k => $memberId) {
            $slug = $slugs[$k % 4];
            [$planId, $credits, $days, $price] = $planIds[$slug][$k % 3];
            $bought = $this->at('2026-10-01 08:00')->addMinutes(97 * $k);
            $membershipId = DB::table('memberships')->insertGetId([
                'membership_plan_id' => $planId, 'user_id' => $memberId, 'credits_left' => $credits === null ? null : max(0, $credits - ($k % 4)),
                'starts_on' => $bought->toDateString(), 'ends_on' => $bought->copy()->addDays($days)->toDateString(), 'created_at' => $bought, 'updated_at' => $bought,
            ]);
            DB::table('payments')->insert([
                'membership_id' => $membershipId, 'provider' => 'stripe', 'provider_reference' => 'cs_test_'.substr(hash('sha256', 'fernway'.$k), 0, 24),
                'amount_pence' => $price, 'currency' => 'GBP', 'status' => $k % 17 === 5 ? 'failed' : 'succeeded',
                'paid_at' => $k % 17 === 5 ? null : $bought->copy()->addMinutes(2), 'created_at' => $bought, 'updated_at' => $bought->copy()->addMinutes(2),
            ]);
        }

        // Three weeks of classes, from Monday 28 September to Sunday 18 October.
        $slots = [['07:00', 0], ['12:15', 1], ['18:30', 0], ['19:00', 2]];
        $now = $this->at('2026-10-11 20:30');
        $bookingRows = [];
        $sessionCount = 0;
        for ($day = 0; $day < 21; $day++) {
            $date = $this->at('2026-09-28')->addDays($day);
            if ($date->isSunday()) {
                continue;
            }
            foreach ($slugs as $s => $slug) {
                foreach ($slots as $t => [$time, $roomIndex]) {
                    if ($date->isSaturday() && $t !== 1 || ($t + $day + $s) % 5 === 4) {
                        continue;
                    }
                    [$typeId, $minutes, $capacity] = $typeIds[$slug][($day + $t + $s) % count($typeIds[$slug])];
                    $instructorList = $instructorIds[$slug];
                    $starts = Carbon::parse($date->toDateString().' '.$time, 'UTC');
                    $sessionId = DB::table('class_sessions')->insertGetId([
                        'class_type_id' => $typeId, 'instructor_id' => $instructorList[($day + $t) % count($instructorList)],
                        'room' => $rooms[$slug][$roomIndex % count($rooms[$slug])], 'starts_at' => $starts, 'ends_at' => $starts->copy()->addMinutes($minutes),
                        'capacity' => $capacity, 'cancelled' => ($day === 9 && $t === 3 && $s === 1), 'created_at' => $this->at('2026-09-25 17:00'), 'updated_at' => $this->at('2026-09-25 17:00'),
                    ]);
                    $sessionCount++;
                    // How full: evenings fill up, the first week fills less (the beta was starting).
                    $past = $starts->lt($now);
                    $fill = min(1.0, ($time === '18:30' ? 0.95 : 0.5) + ($day >= 7 ? 0.08 : -0.05) + (($day * 7 + $t * 3 + $s) % 9) / 40);
                    $count = (int) floor($capacity * ($past ? $fill : $fill * max(0.25, 1 - ($starts->diffInHours($now) / 200))));
                    $pool = $members;
                    usort($pool, fn ($a, $b) => (($a * 31 + $sessionId) % 97) <=> (($b * 31 + $sessionId) % 97));
                    foreach (array_slice($pool, 0, min($count, $capacity)) as $b => $memberId) {
                        $created = $starts->copy()->subHours(6 + (($memberId * 13 + $b) % 90));
                        if ($created->gt($now)) {
                            $created = $now->copy()->subMinutes(30 + (($memberId * 7 + $b) % 70) * 37);
                        }
                        $cancelled = (($memberId + $sessionId) % 23) === 0;
                        $bookingRows[] = [
                            'class_session_id' => $sessionId, 'user_id' => $memberId, 'status' => $cancelled ? 'cancelled' : 'confirmed',
                            'checked_in_at' => (! $cancelled && $past && (($memberId + $b) % 9) !== 0) ? $starts->copy()->subMinutes(2 + ($memberId % 12)) : null,
                            'cancelled_at' => $cancelled ? $created->copy()->addHours(3) : null, 'created_at' => $created, 'updated_at' => $created,
                        ];
                    }
                    if (! $past && $count >= $capacity) {
                        foreach (array_slice($pool, $capacity, 1 + ($sessionId % 3)) as $position => $memberId) {
                            DB::table('waitlist_entries')->insert([
                                'class_session_id' => $sessionId, 'user_id' => $memberId, 'position' => $position + 1, 'promoted_at' => null,
                                'created_at' => $starts->copy()->subHours(20 - $position), 'updated_at' => $starts->copy()->subHours(20 - $position),
                            ]);
                        }
                    }
                }
            }
        }
        foreach (array_chunk($bookingRows, 200) as $chunk) {
            DB::table('bookings')->insert($chunk);
        }

        $this->info(sprintf('%d users, %d sessions, %d bookings, %d waitlist entries.', DB::table('users')->count(), $sessionCount, DB::table('bookings')->count(), DB::table('waitlist_entries')->count()));
    }
}
