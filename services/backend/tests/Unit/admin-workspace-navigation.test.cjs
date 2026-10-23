const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const context={module:{exports:{}}};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../../public/js/admin-workspace.js'),'utf8'),context);
const {shouldWarnBeforeUnload}=context.module.exports;
test('successful server redirect does not trigger abandoned-edit warning',()=>{
 assert.equal(shouldWarnBeforeUnload(true,true,false,{redirect:'/admin_sv/products'}),false);
});
test('failed validation or request still protects an unpersisted draft',()=>{
 assert.equal(shouldWarnBeforeUnload(true,true,false,{}),true);
 assert.equal(shouldWarnBeforeUnload(true,false,false,undefined),true);
});
test('saved form and durable encrypted draft do not warn',()=>{
 assert.equal(shouldWarnBeforeUnload(false,true,false,{}),false);
 assert.equal(shouldWarnBeforeUnload(true,false,true,{}),false);
});
