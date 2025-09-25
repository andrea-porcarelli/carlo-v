<ol class="breadcrumb m-t-sm">
    <li>
        <a href="/">Home</a>
    </li>
    @if(isset($level_0['href']))
    <li>
        <a href="{{ $level_0['href'] }}">
            {{ $level_0['label'] }}
        </a>
    </li>
    @endif
    <li>
        @if(isset($level_1['href']))
            <a href="{{ $level_1['href'] }}">
                @endif
                @if(!isset($level_2))
                    <strong>
                        @endif
                        {{ $level_1['label'] }}
                        @if(!isset($level_2))
                    </strong>
                @endif
                @if(isset($level_1['href']))
            </a>
        @endif
    </li>
    @if(isset($level_2))
        <li class="active">
            <strong>{{ $level_2['label'] }}</strong>
        </li>
    @endif
</ol>
