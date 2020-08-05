
<!-- has children but no parent -->
@if ($navItem->hasChildren())
<a href="#{{ $navItem->id() }}" data-toggle="collapse" aria-expanded="false" onclick="rotateIcon(this.lastElementChild.lastElementChild)" class="{{ $navItem->getCssClasses() }} {{ $navItem->hasActiveChild() ? 'nav__item--expanded' : '' }} flex-column align-items-start submenu-toggler" style="background-color: rgb(71, 75, 84) !important; font-size: {{ $navItem->sidebarPosition != null ? '12px !important;' : '18px !important;' }}">
    <div class="d-flex w-100 justify-content-start align-items-center">
        {!! $navItem->name !!} 
        <i class="fas fa-chevron-right ml-auto" style="font-size: 16px;"></i>
    </div>
</a>
<div id="{{ $navItem->id() }}" class="sidebar-submenu collapse show" style="">
    <ul class="{{ $navItem->sidebarPosition != null ? 'secondhalf' : '' }}" style="background: rgb(71, 75, 84) !important; padding: 0px 5px;">
        @foreach($navItem->children as $child)
            @include('pilot::admin.nav.sidebar._item', ['navItem' => $child])
        @endforeach
        {{-- <li><a href="http://test-pilot-2.test/learn/cms-intro" target="_blank" class="active secondhalf sidebar list-group-item list-group-item-action text-secondary">CMS Introduction</a></li>  --}}
    </ul>
</div>

<!-- has NO children but HAS parent -->
@elseif (!$navItem->hasChildren() && $navItem->parent != null)
    <li><a href="{{ $navItem->url }}" target="{{ $navItem->linkTarget }}" class="{{ $navItem->sidebarPosition != null ? 'secondhalf' : '' }} sidebar list-group-item list-group-item-action text-secondary" style="background-color: rgb(71, 75, 84) !important; font-size: {{ $navItem->sidebarPosition != null ? '12px !important;' : '18px !important;' }}">{!! $navItem->name !!}</a></li> 
@elseif (!$navItem->hasChildren() && !$navItem->parent != null)
<!-- has NO children but NO parent ( normal link ) -->
<a href="{{ $navItem->url }}" target="{{ $navItem->linkTarget }}" class="pilot-nav__item {{ $navItem->sidebarPosition != null ? 'secondhalf' : '' }} sidebar list-group-item list-group-item-action" style="background-color: rgb(71, 75, 84) !important; font-size: {{ $navItem->sidebarPosition != null ? '12px !important;' : '18px !important;' }}">{!! $navItem->name !!}</a>
@endif