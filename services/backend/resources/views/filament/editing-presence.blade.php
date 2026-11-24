@if(auth('web')->check())
@php
$presenceResources=[];
foreach(\Filament\Facades\Filament::getPanel('admin_sv')->getResources() as $resource){
 if(!array_key_exists('index',$resource::getPages()) || !$resource::canViewAny()) continue;
 $presenceResources[]=['slug'=>$resource::getSlug(),'url'=>$resource::getUrl('index'),'product'=>str_ends_with($resource,'\\Products\\ProductResource')];
}
// Filament pages (sync, integration tools etc.) are whitelisted on the server too.
$presencePages=[];
foreach(\Filament\Facades\Filament::getPanel('admin_sv')->getPages() as $page){
 if(!$page::canAccess()) continue;
 try { $presencePages[]=['slug'=>$page::getSlug(),'url'=>$page::getUrl()]; }
 catch(\Throwable $error) { /* Pages requiring route parameters are not tracked. */ }
}
@endphp
<script>
(()=>{
 if(window.__editingPresence)return;window.__editingPresence=true;
 const resources=@js($presenceResources), pages=@js($presencePages), actorId=@js((string) auth('web')->id());
 const windowId=typeof crypto.randomUUID==='function'?crypto.randomUUID():
  'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,c=>{const v=crypto.getRandomValues(new Uint8Array(1))[0]&15;return (c==='x'?v:(v&3)|8).toString(16);});
 let busy=false, reported=false, lastInteractionAt=Date.now();
 const endpoint=@js(url('/admin_sv/editing-presence'));
 function target(url,editOnly=false){try{const path=new URL(url,location.href).pathname;for(const r of resources){const base=new URL(r.url).pathname;const tail=path.slice(base.length).match(/^\/([^/]+)(?:\/(edit|operator-edit))?$/);if(path.startsWith(base)&&tail&&(!editOnly||tail[2])&&!['create','import','sort'].includes(tail[1]))return {resource:r.slug,record:decodeURIComponent(tail[1])};}}catch{}return null;}
 function currentPage(){
  const path=new URL(location.href).pathname.replace(/\/+$/,'')||'/';
  if(path==='/admin_sv') return {kind:'dashboard'};
  for(const r of resources){
   const base=new URL(r.url,location.href).pathname.replace(/\/+$/,'');
   if(path===base) return {kind:'resource',resource:r.slug,action:'index'};
   if(!path.startsWith(base+'/'))continue;
   const tail=path.slice(base.length+1).split('/').map(decodeURIComponent);
   if(tail.length===1&&tail[0]==='create') return {kind:'resource',resource:r.slug,action:'create'};
   if(tail.length===2&&['edit','view','operator-edit'].includes(tail[1]))
    return {kind:'resource',resource:r.slug,action:tail[1]==='operator-edit'?'edit':tail[1],record:tail[0]};
  }
  for(const p of pages){if(path===(new URL(p.url,location.href).pathname.replace(/\/+$/,'')||'/')) return {kind:'page',slug:p.slug};}
  return {kind:'other'};
 }
 function release(){
  return fetch(endpoint,{method:'DELETE',credentials:'same-origin',keepalive:true,headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify({window_id:windowId,actor_id:actorId})}).catch(()=>{});
 }
 async function tick(){if(busy||!window.Livewire)return;busy=true;
  try {
  // A background/idle tab is not evidence of someone working.
  if(document.hidden===true||document.visibilityState==='hidden'||Date.now()-lastInteractionAt>120000){
   if(reported){reported=false;await release();}
   return;
  }
  const rows=new Map(),editing=[],editingPage=target(location.href,true);if(editingPage)editing.push(editingPage);
  document.querySelectorAll('.fi-ta a[href]').forEach(a=>{const t=target(a.href);if(t&&rows.size<30)rows.set(t.resource+':'+t.record,{target:t,element:a.closest('tr')||a.parentElement});});
  for(const c of Livewire.all()){const component=c.$wire?c:c.__instance;const wire=component?.$wire||c;const name=String(component?.name||'');if(name.includes('relation-managers')&&!name.includes('product-variants-relation-manager'))continue;let actions;try{actions=wire.$get('mountedActions');}catch{continue;}
   if(!Array.isArray(actions))continue;
   for(const action of actions){if(action.name!=='edit'||!action.context?.recordKey)continue;
    const resource=name.includes('product-variants-relation-manager')?resources.find(r=>r.product):resources.find(r=>location.pathname.startsWith(new URL(r.url).pathname));
    if(resource)editing.push({resource:resource.slug,record:String(action.context.recordKey)});
   }
  }
  // An open edit modal takes precedence over the underlying list or parent editor.
  const current=editing.length ? {kind:'resource',resource:editing.at(-1).resource,action:'edit',record:editing.at(-1).record} : currentPage();
  const response=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify({window_id:windowId,actor_id:actorId,targets:[...rows.values()].map(x=>x.target),editing:editing.slice(0,5),page:current})});
   if(!response.ok)return;reported=true;const result=await response.json();document.querySelectorAll('[data-editing-presence]').forEach(e=>e.remove());
   for(const [key,{element}] of rows){const names=result.records[key]||[];if(names.length){const badge=document.createElement('small');badge.dataset.editingPresence='1';badge.textContent='Редактирует: '+names.join(', ');badge.style.cssText='display:block;color:#d97706;padding:4px';element.querySelector('td')?.append(badge);}}
   for(const t of editing){const names=result.records[t.resource+':'+t.record]||[];if(names.length){const banner=document.createElement('div');banner.dataset.editingPresence='1';banner.setAttribute('role','status');banner.textContent='Эту карточку также редактирует: '+names.join(', ')+'. Сохранение доступно; согласуйте изменения.';banner.style.cssText='padding:12px;background:#fff7ed;color:#92400e;border:1px solid #f59e0b;margin:8px';(document.querySelector('[role="dialog"] form')||document.querySelector('main'))?.prepend(banner);}}
  }catch{}finally{busy=false;}
 }
 const markInteraction=()=>{lastInteractionAt=Date.now();};
 document.addEventListener('pointerdown',markInteraction,{passive:true});
 document.addEventListener('keydown',markInteraction);
 document.addEventListener('scroll',markInteraction,{passive:true});
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState!=='hidden')markInteraction();tick();});
 setInterval(tick,25000);
 document.addEventListener('livewire:navigated',()=>{markInteraction();tick();});
 document.addEventListener('click',()=>setTimeout(tick,500));
 setTimeout(tick,1000);
 window.addEventListener('pagehide',()=>{reported=false;release();});
})();
</script>

@endif
