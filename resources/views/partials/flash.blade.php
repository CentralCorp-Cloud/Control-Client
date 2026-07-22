@foreach (['success', 'warning', 'error'] as $kind)
    @if (session($kind))
        <x-alert :type="$kind">{{ session($kind) }}</x-alert>
    @endif
@endforeach

@if ($errors->any())
    <x-alert type="error">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
