const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
test('progressive viewport never creates a marker until a real or manual point exists',()=>{
 const blade=fs.readFileSync(path.join(__dirname,'../../resources/views/filament/forms/warehouse-map.blade.php'),'utf8');
 const expression=blade.split('<div x-data="')[1].split('">')[0].replace(/@js\(config\('address_directory.map_tiles'\)\)/g,JSON.stringify('tiles'));
 let markers=0,click;const views=[],bounds=[];
 const map={setView(p,z){views.push([p,z]);return this},fitBounds(p,o){bounds.push([p,o]);},on(e,fn){click=fn},attributionControl:{setPrefix(){}},invalidateSize(){},removeLayer(){},remove(){}};
 const L={map(){return map},tileLayer(){return {addTo(){}}},marker(){markers++;return {addTo(){return this},on(){},setLatLng(){}}},divIcon(){}};
 const context={L,window:{L},$wire:{entangle(){return null}},ResizeObserver:class{observe(){}disconnect(){}},setTimeout,clearTimeout};
 const state=vm.runInNewContext('('+expression+')',context);
 Object.assign(state,{$nextTick:fn=>fn(),$refs:{canvas:{}},$watch(){}});state.init();
 state.centre={bounds:[[50,38],[53,42]],zoom:9};state.sync();assert.equal(bounds.length,1);assert.equal(markers,0);
 state.centre={point:[51.6,39.2],zoom:12};state.sync();assert.equal(views.at(-1)[1],12);assert.equal(markers,0);
 state.centre={bounds:[[51.6,39.2],[51.7,39.3]],zoom:16};state.sync();assert.equal(bounds.at(-1)[1].maxZoom,16);assert.equal(markers,0);
 state.lat=51.65;state.lon=39.25;state.sync();assert.equal(markers,1);assert.equal(views.at(-1)[1],17);
 state.lat=null;state.lon=null;state.centre=null;state.sync();assert.equal(state.marker,null);assert.equal(views.at(-1)[1],5);
 click({latlng:{lat:51.66,lng:39.26}});assert.equal(state.lat,51.66);assert.equal(state.lon,39.26);
 state.destroy();
});
