@php
    $hotspots = is_array($hotspots) ? $hotspots : [];
@endphp

<div class="space-y-4">
    <div class="text-sm text-gray-600">
        <p class="mb-2">Изображение интерьера с существующими точками. Введите координаты X и Y в полях формы ниже.</p>
    </div>
    
    <div class="relative border-2 border-gray-300 rounded-lg overflow-hidden" style="position: relative; width: 100%;">
        <div style="position: relative; width: 100%;">
            <img 
                src="{{ $imageUrl }}" 
                alt="Интерьер"
                class="w-full h-auto"
                style="display: block; width: 100%; height: auto;"
            />
            
            @foreach($hotspots as $hotspot)
                <div 
                    style="position: absolute; left: {{ $hotspot['x'] }}%; top: {{ $hotspot['y'] }}%; transform: translate(-50%, -50%); z-index: 10; pointer-events: none;"
                >
                    <div 
                        style="width: 20px; height: 20px; background-color: #ef4444; border-radius: 50%; border: 2px solid white; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); position: relative;"
                        title="{{ $hotspot['product'] ?? 'Товар' }}"
                    >
                        <div style="position: absolute; inset: 0; background-color: #ef4444; border-radius: 50%; opacity: 0.75; animation: ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    
    @if(count($hotspots) > 0)
        <div class="text-xs text-gray-500 bg-gray-50 p-2 rounded">
            <p>Красные точки показывают существующие точки ({{ count($hotspots) }} шт.)</p>
            @if(config('app.debug'))
                <p class="text-xs text-gray-400 mt-1">Отладка: найдено точек - {{ count($hotspots) }}</p>
            @endif
        </div>
    @else
        <div class="text-xs text-gray-400 bg-gray-50 p-2 rounded">
            <p>Нет существующих точек для отображения</p>
        </div>
    @endif
</div>
