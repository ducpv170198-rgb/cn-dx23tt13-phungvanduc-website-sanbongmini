<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class SanBongMiniSeeder extends Seeder {
 public function run(): void {
  foreach ([['name'=>'Sân 5A','type'=>'5'],['name'=>'Sân 5B','type'=>'5'],['name'=>'Sân 7A','type'=>'7']] as $p) {
   $id=DB::table('pitches')->where('name',$p['name'])->value('id');
   if (!$id) $id=DB::table('pitches')->insertGetId($p+['surface'=>'Cỏ nhân tạo','status'=>'active','created_at'=>now(),'updated_at'=>now()]);
   foreach ([0,1] as $weekend) foreach ([['06:00:00','17:00:00'],['17:00:00','22:00:00']] as $i=>$times) {
    $base=$p['type']==='5'?180000:300000;
    DB::table('pitch_price_rules')->updateOrInsert(['pitch_id'=>$id,'day_type'=>$weekend,'start_time'=>$times[0],'end_time'=>$times[1]],['price_per_hour'=>$base+($weekend?40000:0)+($i?60000:0),'updated_at'=>now(),'created_at'=>now()]);
   }
  }
  foreach ([['Nước suối','drink','chai',10000,100],['Nước thể thao','drink','chai',20000,60],['Thuê áo','jersey','bộ',30000,30]] as [$name,$type,$unit,$price,$stock])
   DB::table('services')->updateOrInsert(['name'=>$name],['type'=>$type,'unit'=>$unit,'price'=>$price,'stock'=>$stock,'is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);
 }
}
