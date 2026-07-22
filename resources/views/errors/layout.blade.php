<!doctype html>
<html lang="fr">
<head>@include('layouts.head')</head>
<body><main class="flex min-h-dvh items-center justify-center px-4 py-12"><div class="w-full max-w-xl text-center"><a href="{{ route('home') }}" class="mx-auto flex w-fit items-center gap-2 text-lg font-semibold"><span class="flex size-8 items-center justify-center rounded-lg bg-slate-950 text-sm font-bold text-white dark:bg-white dark:text-slate-950">C</span>CentralCloud</a><p class="eyebrow mt-12">@yield('code')</p><h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">@yield('title')</h1><p class="mx-auto mt-4 max-w-md leading-7 text-slate-600 dark:text-slate-400">@yield('message')</p><div class="mt-8 flex flex-wrap justify-center gap-3">@yield('actions')</div></div></main></body>
</html>
