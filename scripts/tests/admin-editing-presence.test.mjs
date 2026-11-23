import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';
import { randomUUID, randomFillSync } from 'node:crypto';
const source = readFileSync(new URL('../../services/backend/resources/views/filament/editing-presence.blade.php', import.meta.url),'utf8')
 .split('<script>')[1].split('</script>')[0]
 .replace('@js($presenceResources)',JSON.stringify([{slug:'products',url:'https://example.test/admin_sv/products',product:true}]))
 .replace('@js($presencePages)',JSON.stringify([{slug:'dashboard',url:'https://example.test/admin_sv'}]))
 .replace("@js((string) auth('web')->id())",'"17"')
 .replace("@js(url('/admin_sv/editing-presence'))",'"https://example.test/admin_sv/editing-presence"');
function setup(all,{randomUUIDAvailable=true}={}) {
 let tick,time=Date.now();const requests=[];const events={},documentEvents={};
 const document={hidden:false,visibilityState:'visible',querySelectorAll:()=>[],querySelector:()=>null,addEventListener(name,fn){documentEvents[name]=fn;}};
 const context={URL,Map,Array,String,JSON,Date:{now:()=>time},crypto:{ ...(randomUUIDAvailable ? {randomUUID} : {}), getRandomValues:randomFillSync },location:{href:'https://example.test/admin_sv/products/1/edit',pathname:'/admin_sv/products/1/edit'},
 document,
 setInterval(fn){tick=fn;},setTimeout(){},
 fetch:async(url,options)=>{requests.push({...JSON.parse(options.body),_method:options.method});return {ok:true,json:async()=>({records:{}})};},
 Livewire:{all},window:{Livewire:true,addEventListener(name,fn){events[name]=fn;}}};
 vm.runInNewContext(source,context);return {tick:()=>tick(),requests,events,documentEvents,document,location:context.location,advance:ms=>{time+=ms;}};
}
test('Livewire proxy exposes __instance object and nested offer edit uses $wire.$get',async()=>{
 const wire={$get(name){assert.equal(name,'mountedActions');return [{name:'edit',context:{recordKey:'25'}}];}};
 const t=setup(()=>[{__instance:{name:'app.filament.resources.products.relation-managers.product-variants-relation-manager',$wire:wire}}]);
 await t.tick();assert.equal(t.requests.length,1);assert.equal(t.requests[0].actor_id,'17');
 assert(t.requests[0].editing.some(x=>x.record==='25'&&x.resource==='products'));
 t.events.pagehide();assert.equal(t.requests[1].actor_id,'17');
});
test('direct Livewire instance works and unexpected inspection errors cannot latch busy',async()=>{
 let fail=true;const t=setup(()=>{if(fail){fail=false;throw new Error('temporary component teardown');}return [{name:'products',$wire:{$get:()=>[]}}];});
 await t.tick();await t.tick();await t.tick();assert.equal(t.requests.length,2);
});

test('window heartbeat uses a valid UUID when randomUUID is unavailable on HTTP origins',async()=>{
 const t=setup(()=>[],{randomUUIDAvailable:false});
 await t.tick();
 assert.equal(t.requests.length,1);
 assert.match(t.requests[0].window_id,/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
});

test('heartbeat identifies the opened Filament resource and record without form contents',async()=>{
 const t=setup(()=>[]);
 await t.tick();
 assert.deepEqual(JSON.parse(JSON.stringify(t.requests[0].page)),{kind:'resource',resource:'products',action:'edit',record:'1'});
 assert.equal(t.requests[0]._method,'POST');
 assert(!('fields' in t.requests[0]));
});
test('hidden tab immediately releases presence and a visible tab resumes reporting',async()=>{
 const t=setup(()=>[]);
 await t.tick();assert.equal(t.requests.length,1);
 t.document.visibilityState='hidden';t.document.hidden=true;
 await t.tick();assert.equal(t.requests.length,2);assert.equal(t.requests[1]._method,'DELETE');
 await t.tick();assert.equal(t.requests.length,2);
 t.document.visibilityState='visible';t.document.hidden=false;
 t.documentEvents.visibilitychange();
 await t.tick();assert.equal(t.requests.at(-1)._method,'POST');
});
test('idle tab expires locally after two minutes, while interactions resume reporting',async()=>{
 const t=setup(()=>[]);
 await t.tick();
 t.advance(121000);
 await t.tick();assert.equal(t.requests.at(-1)._method,'DELETE');
 t.documentEvents.pointerdown();
 await t.tick();
 assert.equal(t.requests.at(-1)._method,'POST');
});
