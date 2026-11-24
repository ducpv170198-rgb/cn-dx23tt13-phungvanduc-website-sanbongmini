<?php
// Run from the Laravel project root. Never migrates the existing database.
require __DIR__.'/vendor/autoload.php';
function envset($k,$v){putenv("$k=$v"); $_ENV[$k]=$v; $_SERVER[$k]=$v;}
function boot(){ $app=require __DIR__.'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); return $app; }
function requestOne($app,$job){
    $kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
    $app['auth']->forgetGuards();
    $server=['HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json','REMOTE_ADDR'=>'127.0.0.1'];
    if(isset($job['token'])) $server['HTTP_AUTHORIZATION']='Bearer '.$job['token'];
    $r=Illuminate\Http\Request::create($job['url'],$job['method']??'GET',[],[],[],$server,json_encode($job['body']??[]));
    $t=hrtime(true); $response=$kernel->handle($r); $ms=(hrtime(true)-$t)/1e6;
    $kernel->terminate($r,$response);
    return ['status'=>$response->getStatusCode(),'ms'=>round($ms,3),'body'=>json_decode($response->getContent(),true)];
}
if(($argv[1]??'')==='worker'){
    try { $app=boot(); $expected=getenv('DB_DATABASE'); if(!str_starts_with($expected?:'', 'fieldly_qa_') || $app['db']->connection()->selectOne('SELECT DATABASE() AS db')->db!==$expected) throw new RuntimeException('Worker database isolation failed'); $spec=json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR); file_put_contents($argv[2].'.ready','1');
        $deadline=microtime(true)+90; while(!file_exists($spec['gate'])){if(microtime(true)>$deadline)throw new RuntimeException('Gate timeout'); usleep(1000); clearstatcache();}
        $out=[]; foreach($spec['jobs'] as $job)$out[]=requestOne($app,$job); echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(Throwable $e){fwrite(STDERR,$e->getMessage());exit(1);} exit;
}
if(!extension_loaded('pdo_mysql')){fwrite(STDERR,"Can bat pdo_mysql trong PHP Laragon.\n");exit(1);}
if(!is_file(__DIR__.'/.env')){fwrite(STDERR,"Thieu .env cua du an.\n");exit(1);}
$cfg=Dotenv\Dotenv::parse(file_get_contents(__DIR__.'/.env'));
if(($cfg['DB_CONNECTION']??'')!=='mysql'||!empty($cfg['DB_URL'])){fwrite(STDERR,"Can DB_CONNECTION=mysql va khong dung DB_URL.\n");exit(1);}
$host=$cfg['DB_HOST']??'127.0.0.1'; $port=$cfg['DB_PORT']??'3306';
if(!in_array($host,['127.0.0.1','localhost','::1'],true)){fwrite(STDERR,"Chi cho phep MySQL cuc bo.\n");exit(1);}
$run=date('Ymd_His').'_'.bin2hex(random_bytes(4)); $db='fieldly_qa_'.$run;
$dir=__DIR__.'/kiemthu_ketqua/'.$run; mkdir($dir,0700,true);
$report=['run'=>$run,'database'=>$db,'mode'=>'Laravel HTTP kernel, independent PHP processes; no Apache/network transport','php'=>PHP_VERSION,'cases'=>[]];
function saveReport(){global $dir,$report;file_put_contents($dir.'/ketqua.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));}
function checkCase($id,$ok,$detail){global $report; $report['cases'][]=['id'=>$id,'pass'=>(bool)$ok,'detail'=>$detail];echo $id.': '.($ok?'PASS':'FAIL').PHP_EOL;saveReport();}
function batch($groups,$name){global $dir; $gate=$dir.'/'.$name.'.go';$ps=[];
    foreach($groups as $i=>$jobs){$f=$dir.'/'.$name.'_'.$i.'.json';file_put_contents($f,json_encode(['gate'=>$gate,'jobs'=>$jobs]));
        $p=proc_open([PHP_BINARY,__FILE__,'worker',$f],[0=>['pipe','r'],1=>['file',$f.'.out','w'],2=>['file',$f.'.err','w']],$pipes,__DIR__);if(!is_resource($p))throw new RuntimeException('Cannot spawn worker');fclose($pipes[0]);$ps[]=[$p,$f];}
    try{$deadline=microtime(true)+90;do{$ready=true;foreach($ps as [$p,$f]){clearstatcache();if(!file_exists($f.'.ready')){$ready=false;if(!proc_get_status($p)['running'])throw new RuntimeException('Worker startup: '.file_get_contents($f.'.err'));}}if(!$ready)usleep(10000);if(microtime(true)>$deadline)throw new RuntimeException('Worker startup timeout');}while(!$ready);
        touch($gate);$deadline=microtime(true)+180;do{$running=false;foreach($ps as [$p,$f])if(proc_get_status($p)['running'])$running=true;if($running)usleep(20000);if(microtime(true)>$deadline)throw new RuntimeException('Worker execution timeout');}while($running);
        $out=[];foreach($ps as [$p,$f]){$data=json_decode(file_get_contents($f.'.out'),true);if(!is_array($data))throw new RuntimeException('Worker error: '.file_get_contents($f.'.err'));$out=array_merge($out,$data);}return $out;
    }finally{foreach($ps as [$p,$f]){if(proc_get_status($p)['running'])proc_terminate($p);proc_close($p);}}
}
function job($url,$method='GET',$body=[],$token=null){return compact('url','method','body','token');}
try{
    $pdo=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$cfg['DB_USERNAME']??'root',$cfg['DB_PASSWORD']??'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach(['APP_ENV'=>'testing','APP_CONFIG_CACHE'=>$dir.'/unused_config.php','DB_CONNECTION'=>'mysql','DB_URL'=>'','DB_DATABASE'=>$db,'DB_HOST'=>$host,'DB_PORT'=>$port,'DB_USERNAME'=>$cfg['DB_USERNAME']??'root','DB_PASSWORD'=>$cfg['DB_PASSWORD']??'','CACHE_STORE'=>'array','SESSION_DRIVER'=>'array','QUEUE_CONNECTION'=>'sync','MAIL_MAILER'=>'array','APP_DEBUG'=>'false'] as $k=>$v)envset($k,$v);
    $app=boot(); $conn=$app['db']->connection(); if($conn->getDatabaseName()!==$db||$conn->selectOne('SELECT DATABASE() AS db')->db!==$db)throw new RuntimeException('Database isolation failed');
    $report['os']=PHP_OS_FAMILY; $report['script_sha256']=hash_file('sha256',__FILE__); $report['mysql']=$conn->selectOne('SELECT VERSION() AS version')->version;
    $console=$app->make(Illuminate\Contracts\Console\Kernel::class); if($console->call('migrate',['--force'=>true])!==0)throw new RuntimeException($console->output());
    if($console->call('db:seed',['--class'=>'Database\\Seeders\\SanBongMiniSeeder','--force'=>true])!==0)throw new RuntimeException($console->output());
    $D=fn($table)=>$conn->table($table);$p=$D('pitches')->where('type','5')->first();$s=$D('services')->whereNotNull('stock')->first();$day=now()->addDays(2)->format('Y-m-d');
    $admin=new App\Models\User();$admin->name='QA Admin';$admin->email='qa-admin@example.invalid';$admin->password=Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(20)));$admin->role='admin';$admin->save();$token=$admin->createToken('qa')->plainTextToken;
    $customer=new App\Models\User();$customer->name='QA Customer';$customer->email='qa-customer@example.invalid';$customer->password=Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(20)));$customer->role='customer';$customer->save();$ct=$customer->createToken('qa')->plainTextToken;
    $booking=fn($a,$b,$services=[])=>job('/api/bookings','POST',['pitch_id'=>$p->id,'customer_name'=>'QA synthetic','customer_phone'=>'0900000000','start_at'=>"$day $a:00",'end_at'=>"$day $b:00",'services'=>$services]);
    foreach([['TN01',2,'06:00','07:00'],['TN03',10,'08:00','09:00']] as [$id,$n,$a,$b]){$r=batch(array_fill(0,$n,[$booking($a,$b)]),$id);$codes=array_column($r,'status');$count=$D('bookings')->where('pitch_id',$p->id)->where('start_at',"$day $a:00")->count();$ok=count(array_filter($codes,fn($c)=>$c===201))===1&&count(array_filter($codes,fn($c)=>$c===422))===$n-1&&$count===1;checkCase($id,$ok,['http_status'=>$codes,'rows'=>$count,'responses'=>$r]);}
    $r=batch([[$booking('10:00','11:00')],[$booking('11:00','12:00')]],'TN02');checkCase('TN02',array_column($r,'status')===[201,201],$r);
    $stock=(int)$D('services')->where('id',$s->id)->value('stock');$r=batch([[$booking('13:00','14:00',[['service_id'=>$s->id,'quantity'=>2]])]],'create_service');$bid=$r[0]['body']['data']['id']??null;if(!$bid)throw new RuntimeException('Service booking failed');$invoice=$D('invoices')->where('booking_id',$bid)->first();
    $pay=['idempotency_key'=>'qa-receipt','kind'=>'receipt','method'=>'cash','amount'=>10000];$url='/api/admin/invoices/'.$invoice->id.'/payments';$r=batch([[job($url,'POST',$pay,$token)],[job($url,'POST',$pay,$token)]],'TN04');$n=$D('payments')->where('idempotency_key','qa-receipt')->count();checkCase('TN04',$n===1&&count(array_filter(array_column($r,'status'),fn($c)=>$c>=200&&$c<300))===2,['rows'=>$n,'responses'=>$r]);
    $changed=$pay; $changed['amount']=10001;
    $r=batch([[job($url,'POST',$changed,$token)]],'TN09');checkCase('TN09-idempotency-conflict',$r[0]['status']===422 && $D('payments')->where('idempotency_key','qa-receipt')->count()===1,['response'=>$r]);
    $r=batch([[job('/api/admin/bookings/'.$bid.'/cancel','PATCH',[],$token)]],'TN10');checkCase('TN10-paid-cancel-blocked',$r[0]['status']===422 && $D('bookings')->where('id',$bid)->value('status')!=='cancelled',['response'=>$r]);
    $pay['kind']='refund';$pay['idempotency_key']='qa-refund';$r=batch([[job($url,'POST',$pay,$token)]],'refund');$net=(int)$D('payments')->where('invoice_id',$invoice->id)->selectRaw("SUM(CASE WHEN kind='receipt' THEN amount ELSE -amount END) AS net")->value('net');checkCase('TN04-refund',$r[0]['status']>=200&&$r[0]['status']<300&&$net===0,['net'=>$net,'response'=>$r]);
    $r=batch([[job('/api/admin/bookings/'.$bid.'/cancel','PATCH',[],$token),job('/api/admin/bookings/'.$bid.'/cancel','PATCH',[],$token)]],'TN05');$after=(int)$D('services')->where('id',$s->id)->value('stock');$moves=$D('service_stock_movements')->where('booking_id',$bid)->where('action','booking_cancel')->count();checkCase('TN05',$after===$stock&&$moves===1&&$D('bookings')->where('id',$bid)->value('status')==='cancelled'&&array_column($r,'status')===[200,200],['stock_before'=>$stock,'stock_after'=>$after,'restore_rows'=>$moves,'responses'=>$r]);
    $r=batch([[job('/api/admin/pitches','GET',[],$ct)]],'TN08');checkCase('TN08',$r[0]['status']===403,$r);
    $staff=new App\Models\User();$staff->name='QA Staff';$staff->email='qa-staff@example.invalid';$staff->password=Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(20)));$staff->role='staff';$staff->save();$st=$staff->createToken('qa')->plainTextToken;
    $r=batch([[job('/api/admin/pitches'),job('/api/admin/pitches','GET',[],$ct),job('/api/admin/pitches','GET',[],$st),job('/api/admin/pitches','GET',[],$token)]],'TN11');checkCase('TN11-roles',array_column($r,'status')===[401,403,200,200],['statuses'=>array_column($r,'status')]);
    $r=batch([[job('/api/admin/recurring-bookings','POST',['pitch_id'=>$p->id,'customer_name'=>'QA recurring','customer_phone'=>'0900000000','first_date'=>now()->addDays(40)->format('Y-m-d'),'start_time'=>'06:00','end_time'=>'07:00','weeks'=>3],$token)]],'TN12-create');
    $series=$r[0]['body']['data']['series_id']??null; $ids=$r[0]['body']['data']['booking_ids']??[];
    if(!$series || count($ids)!==3)throw new RuntimeException('Recurring fixture failed');
    $D('bookings')->where('id',$ids[0])->update(['start_at'=>now()->subHours(2),'end_at'=>now()->subHour(),'status'=>'confirmed']);
    $D('bookings')->where('id',$ids[1])->update(['start_at'=>now()->subMinutes(5),'end_at'=>now()->addMinutes(55),'status'=>'confirmed']);
    $r=batch([[job('/api/admin/recurring-bookings/'.$series.'/cancel-future','PATCH',[],$token)]],'TN12-cancel');
    $statuses=$D('bookings')->whereIn('id',$ids)->orderBy('id')->pluck('status')->all();checkCase('TN12-recurring-cancel',$r[0]['status']===200 && $statuses===['confirmed','confirmed','cancelled'],['statuses'=>$statuses,'response'=>$r]);
    $D('invoices')->where('booking_id',$ids[0])->update(['payment_status'=>'paid']);
    $D('invoices')->where('booking_id',$ids[1])->update(['payment_status'=>'paid']);
    $exit=$console->call('bookings:complete-paid');$statuses=$D('bookings')->whereIn('id',$ids)->orderBy('id')->pluck('status')->all();checkCase('TN13-lifecycle',$exit===0 && $statuses===['completed','confirmed','cancelled'],['statuses'=>$statuses,'output'=>$console->output(),'fixture_note'=>'Invoice paid status set directly to isolate lifecycle command']);
    // Synthetic load fixture: five active type-5 pitches, 200 bookings across 30 days.
    $pitches=$D('pitches')->where('type','5')->pluck('id')->all();while(count($pitches)<5){$id=$D('pitches')->insertGetId(['name'=>'QA pitch '.count($pitches),'type'=>'5','surface'=>'QA','status'=>'active','created_at'=>now(),'updated_at'=>now()]);foreach([0,1] as $weekend)$D('pitch_price_rules')->insert(['pitch_id'=>$id,'day_type'=>$weekend,'start_time'=>'06:00:00','end_time'=>'22:00:00','price_per_hour'=>180000,'created_at'=>now(),'updated_at'=>now()]);$pitches[]=$id;}
    for($i=0;$i<200;$i++){ $date=now()->addDays(3+($i%30))->format('Y-m-d');$idx=intdiv($i,30);$pid=$pitches[$idx%5];$hour=15+intdiv($idx,5);$code='QA'.$run.'_'.$i;$code=substr($code,0,32);$start=sprintf('%s %02d:00:00',$date,$hour);$end=sprintf('%s %02d:00:00',$date,$hour+1);$id=$D('bookings')->insertGetId(['booking_code'=>$code,'pitch_id'=>$pid,'customer_name'=>'QA fixture','customer_phone'=>'0900000000','start_at'=>$start,'end_at'=>$end,'status'=>'pending','source'=>'web','pitch_price'=>180000,'service_total'=>0,'total_amount'=>180000,'created_at'=>now(),'updated_at'=>now()]);$inv=$D('invoices')->insertGetId(['invoice_code'=>$code,'booking_id'=>$id,'subtotal'=>180000,'total'=>180000,'payment_status'=>'unpaid','created_at'=>now(),'updated_at'=>now()]);$D('invoice_items')->insert(['invoice_id'=>$inv,'description'=>'QA fixture','quantity'=>1,'unit_price'=>180000,'line_total'=>180000,'created_at'=>now(),'updated_at'=>now()]);}
    $groups=[];for($i=0;$i<10;$i++){ $jobs=[];for($j=0;$j<20;$j++)$jobs[]=job('/api/availability?type=5&date='.now()->addDays(3+(($i*20+$j)%30))->format('Y-m-d'));$groups[]=$jobs;}
    $r=batch($groups,'TN06');$times=array_column($r,'ms');sort($times);$p95=$times[(int)ceil(count($times)*.95)-1];$errors=count(array_filter($r,fn($x)=>$x['status']!==200||count($x['body']['slots']??[])!==160));checkCase('TN06',count($r)===200&&$errors===0&&$p95<=2000,['requests'=>count($r),'workers'=>10,'pitches'=>5,'fixture_bookings'=>200,'fixture_days'=>30,'p95_ms'=>$p95,'min_ms'=>min($times),'max_ms'=>max($times),'errors'=>$errors,'samples_ms'=>$times]);
    $overlap=$conn->selectOne("SELECT COUNT(*) AS n FROM bookings a JOIN bookings b ON a.pitch_id=b.pitch_id AND a.id<b.id AND a.start_at<b.end_at AND a.end_at>b.start_at WHERE a.status<>'cancelled' AND b.status<>'cancelled'")->n;$missing=$conn->selectOne('SELECT COUNT(*) AS n FROM bookings b LEFT JOIN invoices i ON i.booking_id=b.id WHERE i.id IS NULL')->n;$bad=$conn->selectOne('SELECT COUNT(*) AS n FROM invoices i WHERE i.total <> (SELECT COALESCE(SUM(line_total),0) FROM invoice_items WHERE invoice_id=i.id)')->n;checkCase('TN07-integrity',$overlap==0&&$missing==0&&$bad==0,['overlaps'=>$overlap,'missing_invoices'=>$missing,'invoice_mismatches'=>$bad,'note'=>'Snapshot after workload; not a long-duration soak test']);
    $report['completed']=true;
}catch(Throwable $e){$report['fatal']=$e->getMessage();fwrite(STDERR,$e->getMessage().PHP_EOL);}finally{saveReport(); // Remove worker inputs containing disposable auth tokens, retain outputs and final JSON.
    foreach(glob($dir.'/*_*.json') as $f)unlink($f);echo "Ket qua: $dir/ketqua.json\nDatabase QA giu lai de doi chieu: $db\n";}
exit(!empty($report['completed'])&&!in_array(false,array_column($report['cases'],'pass'),true)?0:1);
