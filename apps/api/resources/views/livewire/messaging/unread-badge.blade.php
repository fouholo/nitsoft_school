<span wire:poll.20s>
    @if ($count > 0)
        <span class="ms-1 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-orange-700 px-1.5 py-0.5 text-[11px] font-semibold text-white">
            {{ $count }}
        </span>
    @endif
</span>
