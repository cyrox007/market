<?php
namespace App\Http\Controllers;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminEditingPresenceController extends Controller
{
 public function update(Request $request): JsonResponse
 {
  $panel=Filament::getPanel('admin_sv'); $user=$request->user('web');
  abort_unless($user && $user->canAccessPanel($panel),403); Filament::setCurrentPanel($panel);
  $data=$request->validate(['actor_id'=>'required|string|max:100','window_id'=>'required|uuid','targets'=>'present|array|max:30','targets.*.resource'=>'required|string|max:150','targets.*.record'=>'required|string|max:150','editing'=>'nullable|array|max:5','editing.*.resource'=>'required|string|max:150','editing.*.record'=>'required|string|max:150']);
  abort_unless((string)$user->id === $data['actor_id'],409);
  $resources=[]; foreach($panel->getResources() as $class){$resources[$class::getSlug()]=$class;}
  $visible=[]; $editing=[];
  foreach(['targets','editing'] as $kind){foreach($data[$kind]??[] as $target){
   $class=$resources[$target['resource']]??null; abort_unless($class,422);
   $record=$class::getEloquentQuery()->whereKey($target['record'])->first();
   abort_unless($record && ($kind==='editing' ? $class::canEdit($record) : $class::canView($record)),403);
   $key=$target['resource'].':'.$target['record']; $visible[$key]=$target;
   if($kind==='editing'){$editing[$key]=$target;}
  }}
  DB::transaction(function()use($data,$editing,$user):void{
   DB::table('admin_editing_presence')->where('expires_at','<=',now())->delete();
   DB::table('admin_editing_presence')->where('user_id',$user->id)->where('window_id',$data['window_id'])->delete();
   foreach($editing as $target){DB::table('admin_editing_presence')->insert(['user_id'=>$user->id,'window_id'=>$data['window_id'],'resource'=>$target['resource'],'record_key'=>$target['record'],'expires_at'=>now()->addSeconds(90)]);}
  });
  $result=[];
  foreach($visible as $key=>$target){
   $result[$key]=DB::table('admin_editing_presence as p')->join('users as u','u.id','=','p.user_id')
    ->where('p.resource',$target['resource'])->where('p.record_key',$target['record'])->where('p.expires_at','>',now())
    ->where('p.window_id','!=',$data['window_id'])->select('u.name')->distinct()->pluck('name')->all();
  }
  return response()->json(['records'=>$result,'ttl'=>90])->header('Cache-Control','no-store, private');
 }
 public function release(Request $request): JsonResponse
 {
  abort_unless($request->user('web')?->canAccessPanel(Filament::getPanel('admin_sv')),403);
  $data=$request->validate(['actor_id'=>'required|string|max:100','window_id'=>'required|uuid']);
  abort_unless((string)$request->user('web')->id === $data['actor_id'],409);
  DB::table('admin_editing_presence')->where('user_id',$request->user('web')->id)->where('window_id',$request->input('window_id'))->delete();
  return response()->json(['released'=>true])->header('Cache-Control','no-store, private');
 }
}
