@php($prefix = substr($getStatePath(), 0, -strlen('_warehouse_map')))
<div x-data="{
    lat: $wire.entangle('{{ $prefix }}latitude'),
    lon: $wire.entangle('{{ $prefix }}longitude'),
    centre: $wire.entangle('{{ $prefix }}map_centre'),
    map: null, marker: null, observer: null,
    sync() {
        if (!this.map) return;
        const valid = this.lat !== null && this.lat !== '' && this.lon !== null && this.lon !== '';
        if (valid) {
            const point = [Number(this.lat), Number(this.lon)];
            if (!this.marker) {
                this.marker = L.marker(point, {draggable: true, icon: L.divIcon({html: '📍', className: '', iconSize: [28, 36], iconAnchor: [14, 36]})}).addTo(this.map);
                this.marker.on('dragend', () => { const p = this.marker.getLatLng(); this.lat = p.lat; this.lon = p.lng; });
            }
            this.marker.setLatLng(point);
            this.map.setView(point, 17);
        } else {
            if (this.marker) { this.map.removeLayer(this.marker); this.marker = null; }
            if (this.centre) this.map.setView(this.centre, 12);
        }
    },
    init() {
        this.$nextTick(() => {
            if (!window.L) return;
            this.map = L.map(this.$refs.canvas).setView([55.75, 37.62], 5);
            this.map.attributionControl.setPrefix(false);
            L.tileLayer(@js(config('address_directory.map_tiles')), {maxZoom: 19, attribution: '© OpenStreetMap contributors'}).addTo(this.map);
            this.map.on('click', (e) => { this.lat = e.latlng.lat; this.lon = e.latlng.lng; this.sync(); });
            this.$watch('lat', () => this.sync());
            this.$watch('lon', () => this.sync());
            this.$watch('centre', () => this.sync());
            this.observer = new ResizeObserver(() => this.map.invalidateSize());
            this.observer.observe(this.$refs.canvas);
            this.sync();
        });
    },
    destroy() { this.observer?.disconnect(); this.map?.remove(); }
}">
    <p style="margin-bottom: 8px" x-text="lat !== null && lat !== '' ? 'Точка склада найдена. При необходимости перетащите метку к въезду.' : 'Точных координат нет. Укажите склад щелчком на карте — вводить числа не нужно.'"></p>
    <div wire:ignore x-ref="canvas" style="height: 320px; border-radius: 8px; z-index: 0"></div>
    <p style="margin-top: 8px; font-size: 12px">Подложка карты загружается из OpenStreetMap. Центр населённого пункта не сохраняется как точка склада.</p>
</div>
