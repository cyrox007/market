@if ($targets !== '')
    <span wire:loading.delay wire:target="{{ $targets }}" role="status" aria-live="polite" style="font-size: 13px">
        {{ $message }}
    </span>
@endif
