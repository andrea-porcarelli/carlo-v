<li class="{{ (Request::is($route) ) ? 'active' : '' }}">

    <a href="@if (Route::has($route)) {{ route($route) }} @else # @endif">
        <i class="fa {{ $icon }} {{ isset($icon_color) ? $icon_color : '' }}"></i>
        <span class="nav-label {{ isset($class) ? $class : '' }}">{{ $label }}</span>
    </a>
    @isset($sub_menu)
        <ul class="nav nav-second-level collapse">
            @foreach($sub_menu as $menu)
                <li>
                    <a href="{{ route($menu['route']) }}">
                        <i class="fa {{ $menu['icon'] }}"></i>
                        {{ $menu['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endisset
</li>
