{{--
    The prompt every page opens with, plus its metadata readout. Rendered by the
    layout inside the chrome panel, so it carries no texture of its own -- child
    views supply it via @section('terminal-header'), not inside 'body'.
--}}
@props([
    'path' => '~',
    'file' => null,
    'command' => 'cat',
    'user' => null,
    'rows' => [],
])

<header {{ $attributes->merge(['class' => 'terminal-header']) }}>
    <x-terminal.prompt
        :path="$path"
        :command="$file ? $command.' '.$file : $command"
        :user="$user"
    />

    <x-terminal.readout :rows="$rows" />
</header>
