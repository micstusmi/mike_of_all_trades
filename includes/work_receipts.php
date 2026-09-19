<?php
declare(strict_types=1);

require_once __DIR__ . '/work_tracker.php';
require_once __DIR__ . '/work_media.php';

function wr_fail(string $message, int $status=400): never { http_response_code($status); exit($message); }
function wr_root(): string { return dirname(__DIR__); }
function wr_storage_dir(int $jobId): string { return wr_root().'/storage/private/work_receipts_v2/job_'.$jobId; }
function wr_response_text(array $data): string { $text=''; foreach(($data['output']??[]) as $out) foreach(($out['content']??[]) as $part) if(isset($part['text'])) $text.=(string)$part['text']; return trim($text); }

function wr_normalise_uploads(array $files): array {
    if(!isset($files['name'])) return [];
    $names=is_array($files['name'])?$files['name']:[$files['name']];$out=[];
    foreach($names as $i=>$name)$out[]=['name'=>(string)$name,'tmp_name'=>(string)(is_array($files['tmp_name']??null)?($files['tmp_name'][$i]??''):($files['tmp_name']??'')),'error'=>(int)(is_array($files['error']??null)?($files['error'][$i]??UPLOAD_ERR_NO_FILE):($files['error']??UPLOAD_ERR_NO_FILE)),'size'=>(int)(is_array($files['size']??null)?($files['size'][$i]??0):($files['size']??0))];
    return $out;
}

function wr_safe_name(string $name): string { $name=basename(str_replace('\\','/',$name)); return mb_substr(preg_replace('/[^A-Za-z0-9._-]+/','_',$name)?:'receipt',0,180); }

function wr_store_one(PDO $pdo,int $jobId,?int $taskId,?int $sessionId,string $sourcePath,string $originalName,bool $uploaded): array {
    if(!is_file($sourcePath)) throw new RuntimeException('A receipt source file is missing.');
    $size=(int)(filesize($sourcePath)?:0);if($size<1||$size>15*1024*1024)throw new RuntimeException($originalName.' must be between 1 byte and 15 MB.');
    $mime=wt_normalise_uploaded_photo_mime((string)(new finfo(FILEINFO_MIME_TYPE))->file($sourcePath),$originalName);
    $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/heic'=>'jpg','image/heif'=>'jpg','image/heic-sequence'=>'jpg','image/heif-sequence'=>'jpg','application/pdf'=>'pdf'];
    if(!isset($extensions[$mime]))throw new RuntimeException($originalName.' is not a supported receipt image or PDF.');
    $dir=wr_storage_dir($jobId);if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Could not create private receipt storage.');
    $stored='receipt_'.date('Ymd_His').'_'.bin2hex(random_bytes(8)).'.'.$extensions[$mime];$absolute=$dir.'/'.$stored;$storedMime=$mime;
    if(wt_is_heic_photo($mime)){wt_save_heic_as_task_jpeg($sourcePath,$absolute);$storedMime='image/jpeg';}
    elseif($uploaded){if(!move_uploaded_file($sourcePath,$absolute))throw new RuntimeException('Could not save '.$originalName.'.');}
    elseif(!copy($sourcePath,$absolute))throw new RuntimeException('Could not save '.$originalName.'.');
    $sha=hash_file('sha256',$absolute)?:'';$existing=$pdo->prepare('SELECT id FROM work_receipts WHERE job_id=? AND sha256=? LIMIT 1');$existing->execute([$jobId,$sha]);$duplicate=(int)$existing->fetchColumn();
    if($duplicate>0){@unlink($absolute);return ['id'=>$duplicate,'duplicate'=>true];}
    $relative='storage/private/work_receipts_v2/job_'.$jobId.'/'.$stored;
    $q=$pdo->prepare("INSERT INTO work_receipts(job_id,task_id,session_id,original_name,relative_path,mime_type,file_size,sha256,status) VALUES(?,?,?,?,?,?,?,?,'processing')");
    $q->execute([$jobId,$taskId,$sessionId,wr_safe_name($originalName),$relative,$storedMime,(int)(filesize($absolute)?:$size),$sha]);
    return ['id'=>(int)$pdo->lastInsertId(),'duplicate'=>false];
}

function wr_accept_uploads(PDO $pdo,int $jobId,?int $taskId,?int $sessionId,array $files): array {
    $uploads=array_values(array_filter(wr_normalise_uploads($files),static fn(array $f):bool=>$f['error']!==UPLOAD_ERR_NO_FILE));
    if(!$uploads)wr_fail('Choose receipt photos, PDFs or a ZIP file.');if(count($uploads)>20)wr_fail('Choose no more than 20 files at once.');
    $uploadBytes=array_sum(array_map(static fn(array $f):int=>max(0,(int)$f['size']),$uploads));if($uploadBytes>120*1024*1024)wr_fail('The combined upload must be no larger than 120 MB.');
    $result=['queued'=>0,'duplicates'=>0,'ids'=>[]];$zipEntries=0;$zipExpandedBytes=0;
    foreach($uploads as $upload){
        if($upload['error']!==UPLOAD_ERR_OK)throw new RuntimeException('One receipt upload failed with code '.$upload['error'].'.');
        $isZip=strtolower(pathinfo($upload['name'],PATHINFO_EXTENSION))==='zip';
        if(!$isZip){$saved=wr_store_one($pdo,$jobId,$taskId,$sessionId,$upload['tmp_name'],$upload['name'],true);$result[$saved['duplicate']?'duplicates':'queued']++;$result['ids'][]=$saved['id'];continue;}
        if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP support is unavailable on this server. Upload the receipt images directly instead.');
        $zip=new ZipArchive();if($zip->open($upload['tmp_name'])!==true)throw new RuntimeException('A ZIP file could not be opened.');
        for($i=0;$i<$zip->numFiles;$i++){
            $stat=$zip->statIndex($i);$name=(string)($stat['name']??'');if($name===''||str_ends_with($name,'/')||str_contains($name,'..'))continue;
            $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($ext,['jpg','jpeg','png','webp','heic','heif','pdf'],true))continue;
            if(++$zipEntries>60){$zip->close();throw new RuntimeException('ZIP files may contain no more than 60 receipt images/PDFs in one upload.');}
            if((int)($stat['size']??0)>15*1024*1024){$zip->close();throw new RuntimeException('A receipt inside the ZIP is larger than 15 MB.');}$zipExpandedBytes+=(int)($stat['size']??0);if($zipExpandedBytes>180*1024*1024){$zip->close();throw new RuntimeException('The expanded ZIP contents are too large.');}
            $tmp=tempnam(sys_get_temp_dir(),'mot_receipt_');if($tmp===false)throw new RuntimeException('Could not create a temporary receipt file.');
            $stream=$zip->getStream($name);if(!$stream){@unlink($tmp);continue;}$out=fopen($tmp,'wb');if(!$out){fclose($stream);@unlink($tmp);continue;}stream_copy_to_stream($stream,$out);fclose($stream);fclose($out);
            try{$saved=wr_store_one($pdo,$jobId,$taskId,$sessionId,$tmp,basename($name),false);$result[$saved['duplicate']?'duplicates':'queued']++;$result['ids'][]=$saved['id'];}finally{@unlink($tmp);}
        }
        $zip->close();
    }
    if($result['queued']<1&&$result['duplicates']<1)throw new RuntimeException('No supported receipt images or PDFs were found.');
    return $result;
}

function wr_start_worker(?int $jobId=null): bool {
    if(!function_exists('exec'))return false;$script=wr_root().'/tools/process_receipt_ocr_queue.php';if(!is_file($script))return false;
    $defaultPhp=PHP_BINDIR.DIRECTORY_SEPARATOR.(PHP_OS_FAMILY==='Windows'?'php.exe':'php');$php=(string)wt_env('WORKTRACKER_PHP_BIN',$defaultPhp);if(!is_file($php))return false;
    if(PHP_OS_FAMILY==='Windows')$command='start /B "" '.escapeshellarg($php).' '.escapeshellarg($script).($jobId?' '.(int)$jobId:'').' > NUL 2>&1';
    else{if(!is_executable($php))return false;$command=escapeshellarg($php).' '.escapeshellarg($script).($jobId?' '.(int)$jobId:'').' > /dev/null 2>&1 &';}
    @exec($command);return true;
}

function wr_process_receipt(PDO $pdo,array $receipt): void {
    $id=(int)$receipt['id'];$path=wr_root().'/'.ltrim((string)$receipt['relative_path'],'/');if(!is_file($path))throw new RuntimeException('Stored receipt file is missing.');
    $key=trim((string)wt_env('OPENAI_API_KEY',''));if($key==='')throw new RuntimeException('OPENAI_API_KEY is not configured.');
    $prompt='Read this Australian supplier receipt accurately. Return JSON only: {"supplier":"","receipt_number":"","purchase_date":"YYYY-MM-DD or null","gross_total":0,"gst_total":0,"net_total":0,"confidence_note":"","lines":[{"description":"","quantity":null,"unit_price":null,"gross_amount":null,"gst_amount":null,"net_amount":null}]}. Preserve product names/codes where legible. Exclude payment/tender/change lines from purchased items. Never invent unreadable values. Totals must reflect the receipt; in Australia GST-inclusive taxable amounts normally have GST equal to one eleventh, but only calculate that when the receipt clearly states GST-inclusive/taxable treatment. Mention uncertainty in confidence_note.';
    $bytes=file_get_contents($path);if(!is_string($bytes)||$bytes==='')throw new RuntimeException('Stored receipt is empty.');$data='data:'.(string)$receipt['mime_type'].';base64,'.base64_encode($bytes);
    $attachment=$receipt['mime_type']==='application/pdf'?['type'=>'input_file','filename'=>(string)$receipt['original_name'],'file_data'=>$data]:['type'=>'input_image','image_url'=>$data,'detail'=>'high'];
    $payload=json_encode(['model'=>wt_env('WORKTRACKER_RECEIPT_AI_MODEL',wt_env('WORKTRACKER_AI_MODEL','gpt-5.6-luna')),'input'=>[['role'=>'user','content'=>[['type'=>'input_text','text'=>$prompt],$attachment]]],'max_output_tokens'=>8000],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $ch=curl_init('https://api.openai.com/v1/responses');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>150,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json']]);$raw=curl_exec($ch);$err=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($raw===false||$http<200||$http>=300)throw new RuntimeException($err?:'Receipt AI failed with HTTP '.$http.'.');
    $response=json_decode((string)$raw,true);$text=preg_replace('/^```(?:json)?\s*|\s*```$/i','',wr_response_text(is_array($response)?$response:[]));$parsed=json_decode(trim((string)$text),true);if(!is_array($parsed))throw new RuntimeException('AI returned an unexpected receipt format.');
    $num=static fn($v):?float=>is_numeric($v)?round(max(0,(float)$v),2):null;$date=trim((string)($parsed['purchase_date']??''));if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))$date=null;
    $pdo->beginTransaction();try{
        $pdo->prepare('DELETE FROM work_receipt_lines WHERE receipt_id=?')->execute([$id]);$insert=$pdo->prepare('INSERT INTO work_receipt_lines(receipt_id,job_id,task_id,line_order,description,quantity,unit_price,gross_amount,gst_amount,net_amount) VALUES(?,?,?,?,?,?,?,?,?,?)');$order=0;
        foreach((array)($parsed['lines']??[]) as $line){if(!is_array($line))continue;$description=mb_substr(trim((string)($line['description']??'')),0,500);if($description==='')continue;$order+=10;$insert->execute([$id,(int)$receipt['job_id'],$receipt['task_id']?:null,$order,$description,$num($line['quantity']??null),$num($line['unit_price']??null),$num($line['gross_amount']??null),$num($line['gst_amount']??null),$num($line['net_amount']??null)]);}
        $gross=$num($parsed['gross_total']??null);$gst=$num($parsed['gst_total']??null);$net=$num($parsed['net_total']??null);if($net===null&&$gross!==null&&$gst!==null)$net=round($gross-$gst,2);
        if($order===0&&$gross!==null){$insert->execute([$id,(int)$receipt['job_id'],$receipt['task_id']?:null,10,'Receipt purchase',1,$gross,$gross,$gst,$net]);}
        $pdo->prepare("UPDATE work_receipts SET status='ready',supplier=?,receipt_number=?,purchase_date=?,gross_total=?,gst_total=?,net_total=?,confidence_note=?,raw_ai_json=?,error_message=NULL WHERE id=?")->execute([mb_substr(trim((string)($parsed['supplier']??'')),0,190)?:null,mb_substr(trim((string)($parsed['receipt_number']??'')),0,120)?:null,$date,$gross,$gst,$net,mb_substr(trim((string)($parsed['confidence_note']??'')),0,500)?:null,json_encode($parsed,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$id]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
