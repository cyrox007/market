<?php
namespace Tests\Feature\Admin;
use App\Models\User;
use App\Models\Product\Product;
use App\Filament\Resources\Products\ProductResource;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
class EditingPresenceTest extends TestCase
{
 private function operator(string $name): User {
  Role::findOrCreate('super_admin','web');$u=User::factory()->create(['name'=>$name]);$u->assignRole('super_admin');return $u;
 }
 private function payload(Product $p,string $window,bool $edit=true):array {
  $t=['resource'=>ProductResource::getSlug(),'record'=>(string)$p->id];return ['window_id'=>$window,'targets'=>[$t],'editing'=>$edit?[$t]:[]];
 }
 public function test_heartbeat_is_visible_to_other_operator_and_expires():void {
  $p=Product::factory()->create();$a=$this->operator('Анна');$b=$this->operator('Борис');$w=(string)Str::uuid();$key=ProductResource::getSlug().':'.$p->id;
  $this->actingAs($a,'web')->postJson('/admin_sv/editing-presence',$this->payload($p,$w))->assertOk()->assertJsonPath('records.'.$key,[]);
  $this->actingAs($b,'web')->postJson('/admin_sv/editing-presence',$this->payload($p,(string)Str::uuid(),false))->assertOk()->assertJsonPath('records.'.$key,['Анна']);
  $this->travel(91)->seconds();
  $this->actingAs($b,'web')->postJson('/admin_sv/editing-presence',$this->payload($p,(string)Str::uuid(),false))->assertOk()->assertJsonPath('records.'.$key,[]);
 }
 public function test_release_only_removes_own_window():void {
  $p=Product::factory()->create();$a=$this->operator('Анна');$b=$this->operator('Борис');$w=(string)Str::uuid();
  $this->actingAs($a,'web')->postJson('/admin_sv/editing-presence',$this->payload($p,$w))->assertOk();
  $this->actingAs($b,'web')->deleteJson('/admin_sv/editing-presence',['window_id'=>$w])->assertOk();
  $this->assertDatabaseHas('admin_editing_presence',['user_id'=>$a->id,'window_id'=>$w]);
  $this->actingAs($a,'web')->deleteJson('/admin_sv/editing-presence',['window_id'=>$w])->assertOk();
  $this->assertDatabaseMissing('admin_editing_presence',['window_id'=>$w]);
 }
 public function test_viewer_can_read_but_cannot_mark_editing():void {
  $role=Role::findOrCreate('viewer','web');foreach(['viewAny products','view products'] as $n)$role->givePermissionTo(Permission::findOrCreate($n,'web'));
  $u=User::factory()->create();$u->assignRole($role);$p=Product::factory()->create();$window=(string)Str::uuid();
  $this->actingAs($u,'web')->postJson('/admin_sv/editing-presence',$this->payload($p,$window,false))->assertOk();
  $this->actingAs($u,'web')->postJson('/admin_sv/editing-presence',$this->payload($p,$window))->assertForbidden();
  $this->assertDatabaseCount('admin_editing_presence',0);
 }
 public function test_unregistered_resource_and_oversized_batch_are_rejected():void {
  $u=$this->operator('Анна');$this->actingAs($u,'web')->postJson('/admin_sv/editing-presence',['window_id'=>(string)Str::uuid(),'targets'=>[['resource'=>'App\\Models\\User','record'=>'1']]])->assertUnprocessable();
  $p=Product::factory()->create();$payload=$this->payload($p,(string)Str::uuid(),false);$payload['targets']=array_fill(0,31,$payload['targets'][0]);
  $this->actingAs($u,'web')->postJson('/admin_sv/editing-presence',$payload)->assertUnprocessable();
 }
 public function test_anonymous_access_is_denied():void {
  $this->postJson('/admin_sv/editing-presence',['window_id'=>(string)Str::uuid(),'targets'=>[]])->assertUnauthorized();
 }
}
