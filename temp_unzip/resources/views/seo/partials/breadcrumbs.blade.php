<div class="container">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ url('/') }}">Home</a>
        @if(isset($location))
            <span>›</span>
            @if($location->type === 'area' && isset($location->parent))
                <a href="{{ url('/locations/' . $location->parent->slug . '/') }}">{{ $location->parent->name }}</a>
                <span>›</span>
                <a href="{{ url('/locations/' . $location->parent->slug . '/' . $location->slug . '/') }}">{{ $location->name }}</a>
            @else
                <a href="{{ url('/locations/' . $location->slug . '/') }}">{{ $location->name }}</a>
            @endif
        @endif
        @if(isset($service))
            <span>›</span>
            <a href="{{ url('/find/' . $service->slug . '/') }}">{{ $service->name }}</a>
        @endif
        @if(!empty($page->h1) && (!isset($service) && !isset($location)))
            <span>›</span>
            <span>{{ $page->h1 }}</span>
        @endif
    </nav>
</div>
