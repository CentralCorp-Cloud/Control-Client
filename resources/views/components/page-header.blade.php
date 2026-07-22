@props(['title', 'description' => null, 'eyebrow' => null])
<header {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="min-w-0">
        @if($eyebrow)<p class="eyebrow mb-2">{{ $eyebrow }}</p>@endif
        <h1 class="page-title">{{ $title }}</h1>
        @if($description)<p class="page-description">{{ $description }}</p>@endif
    </div>
    @if(isset($actions))<div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>@endif
</header>
