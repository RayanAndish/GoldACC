{{-- File: resources/views/components/action-message.blade.php --}}
        @props(['on'])

        <div x-data="{ shown: false, timeout: null }"
             x-init="@this.on ? @this.on('{{ $on }}', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2000); }) : @this.livewire.on('{{ $on }}', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2000); })"
             x-show="shown"
             x-transition:leave.opacity.duration.1500ms
             style="display: none;"
             {{ $attributes->merge(['class' => 'text-sm text-green-600']) }}> {{-- Adjusted color to green --}}
            {{ $slot->isEmpty() ? __('ذخیره شد.') : $slot }} {{-- Translated default message --}}
        </div>