@props([
    'name' => 'sameday_locker_id',
    'value' => null,
    'county' => null,
    'city' => null,
    'label' => 'Alege easybox',
])

@php
    $pickerId = 'sameday-locker-'.\Illuminate\Support\Str::random(8);
    $plugin = [
        'clientId' => config('sameday.locker_plugin.client_id'),
        'countryCode' => config('sameday.country', 'RO'),
        'langCode' => strtolower((string) config('sameday.country', 'RO')),
        'apiUsername' => config('sameday.locker_plugin.lm_username'),
        'theme' => config('sameday.locker_plugin.theme', 'light'),
        'city' => $city,
        'county' => $county,
    ];
@endphp

@once
    <script src="{{ config('sameday.locker_plugin.script') }}" defer></script>
@endonce

<div id="{{ $pickerId }}" class="sameday-locker-picker">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-sameday-locker-input {{ $attributes->whereStartsWith('wire:model') }}>
    <button type="button" data-sameday-locker-open {{ $attributes->whereDoesntStartWith('wire:model') }}>{{ $label }}</button>
    <span data-sameday-locker-label></span>
</div>

<script>
    (function () {
        var root = document.getElementById(@json($pickerId));
        var config = @json($plugin);

        root.querySelector('[data-sameday-locker-open]').addEventListener('click', function () {
            if (! window.LockerPlugin) {
                return;
            }

            window.LockerPlugin.init(config);
            var plugin = window.LockerPlugin.getInstance();

            plugin.subscribe(function (locker) {
                var input = root.querySelector('[data-sameday-locker-input]');
                input.value = locker.lockerId;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                root.querySelector('[data-sameday-locker-label]').textContent = locker.name + ', ' + locker.address;
                plugin.close();
            });

            plugin.open();
        });
    })();
</script>
