{{--
    Heads a block or a list: "// verdict". Replaces the old `>` prefix, which is
    retired site-wide. Lowercase, because it reads as a source comment.
--}}
@props(['label'])

<div {{ $attributes->merge(['class' => 'my-6']) }}>
    <p class="section-marker">// {{ Str::lower($label) }}</p>

    {{ $slot }}
</div>
