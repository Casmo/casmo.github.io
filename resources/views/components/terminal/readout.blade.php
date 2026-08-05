{{--
    Dot-leader rows for short single-line data. Pass a label => value map; blank
    values drop out, so callers do not need to guard every optional field. A value
    may also be ['url' => ..., 'text' => ...] to render as a link.

    Multi-line values do not belong here -- use <x-terminal.section> instead.
--}}
@props(['rows' => []])

@php($rows = collect($rows)->filter(fn ($value) => filled($value)))

@if($rows->isNotEmpty())
    <dl {{ $attributes->merge(['class' => 'readout']) }}>
        @foreach($rows as $label => $value)
            <dt class="readout__label glow">{{ $label }}</dt>
            <dd class="readout__value">
                @if(is_array($value))
                    <a href="{{ $value['url'] }}">{{ $value['text'] ?? $value['url'] }}</a>
                @else
                    {{ $value }}
                @endif
            </dd>
        @endforeach
    </dl>
@endif
