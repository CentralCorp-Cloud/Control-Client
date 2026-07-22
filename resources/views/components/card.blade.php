@props(['title'=>null, 'description'=>null, 'padding'=>true])
<section {{ $attributes->class(['surface', 'p-5 sm:p-6' => $padding]) }}>
    @if($title || isset($actions))
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>@if($title)<h2 class="section-title">{{ $title }}</h2>@endif @if($description)<p class="mt-1 text-sm leading-5 text-slate-500 dark:text-slate-400">{{ $description }}</p>@endif</div>
            @if(isset($actions))<div class="shrink-0">{{ $actions }}</div>@endif
        </div>
    @endif
    {{ $slot }}
</section>
