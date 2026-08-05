@props([
    'path' => '~',
    'command' => null,
    'user' => null,
])

@php($host = \App\Support\Terminal::host($user))

<p {{ $attributes->merge(['class' => 'prompt glow']) }}><span class="prompt__host">{{ $host }}</span><span class="prompt__path">:{{ $path }}</span><span class="prompt__sigil">$</span>@if($command) <span class="prompt__command">{{ $command }}</span>@endif</p>
