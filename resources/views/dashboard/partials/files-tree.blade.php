{{-- One level of the folder tree. Includes itself for the levels below. --}}
<ul class="tree-list">
    @foreach ($nodes as $node)
        <li>
            @if ($node['children'] !== [])
                <details @if ($node['open']) open @endif>
                    <summary @class(['tree-row', 'is-active' => $node['active']])>
                        <span class="tree-caret">@include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</span>
                        <a href="{{ $browse($node['path']) }}" @if ($node['active']) aria-current="page" @endif>{{ $node['name'] }}</a>
                    </summary>
                    @include('larapilot::dashboard.partials.files-tree', ['nodes' => $node['children'], 'browse' => $browse])
                </details>
            @else
                <div @class(['tree-row', 'is-leaf', 'is-active' => $node['active']])>
                    <span class="tree-caret" aria-hidden="true"></span>
                    <a href="{{ $browse($node['path']) }}" @if ($node['active']) aria-current="page" @endif>{{ $node['name'] }}</a>
                </div>
            @endif
        </li>
    @endforeach
</ul>
