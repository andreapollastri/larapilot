<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>{{ $studio->name }} · Timetable</title></head>
<body>
<h1>This week at {{ $studio->name }}</h1>
<ul>
@foreach ($week as $row)
    <li>{{ $row['starts_at'] }} · {{ $row['room'] }} · {{ $row['left'] }} left</li>
@endforeach
</ul>
</body>
</html>
