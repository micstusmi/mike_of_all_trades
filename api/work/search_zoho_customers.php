<?php
declare(strict_types=1);
require_once __DIR__.'/_admin_auth.php';require_once __DIR__.'/../../includes/zoho_functions.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function zsc_email(array $c): string {if(!empty($c['email']))return trim((string)$c['email']);foreach(($c['contact_persons']??[]) as $p)if(!empty($p['email']))return trim((string)$p['email']);return '';}
function zsc_phone(array $c): string {foreach(['mobile','phone'] as $f)if(!empty($c[$f]))return trim((string)$c[$f]);foreach(($c['contact_persons']??[]) as $p)foreach(['mobile','phone'] as $f)if(!empty($p[$f]))return trim((string)$p[$f]);return '';}
function zsc_address(array $c): string {$b=is_array($c['billing_address']??null)?$c['billing_address']:[];$parts=[];foreach(['address','street2','city','state','zip'] as $f){$v=trim((string)($b[$f]??''));if($v!==''&&!in_array($v,$parts,true))$parts[]=$v;}return implode(', ',$parts);}
try{
    $query=trim((string)($_GET['q']??''));if(mb_strlen($query)<2){echo json_encode(['ok'=>true,'contacts'=>[],'customers'=>[]]);exit;}
    if(filter_var($query,FILTER_VALIDATE_EMAIL))$contacts=zohoListContacts(['email'=>strtolower($query)]);elseif(preg_match('/^[\d\s()+-]+$/',$query)){$digits=preg_replace('/\D+/','',$query)??'';$contacts=strlen($digits)>=4?zohoListContacts(['phone_contains'=>substr($digits,-8)]):[];}else $contacts=zohoListContacts(['search_text'=>$query]);
    $rows=[];$seen=[];foreach($contacts as $c){$contactId=trim((string)($c['contact_id']??''));if($contactId===''||isset($seen[$contactId]))continue;$seen[$contactId]=true;$rows[]=['contact_id'=>$contactId,'name'=>trim((string)($c['contact_name']??$c['company_name']??'')),'company'=>(string)($c['company_name']??''),'email'=>zsc_email($c),'phone'=>zsc_phone($c),'address'=>zsc_address($c)];if(count($rows)>=15)break;}
    echo json_encode(['ok'=>true,'contacts'=>$rows,'customers'=>$rows],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){error_log('Zoho customer search error: '.$e->getMessage());http_response_code(502);echo json_encode(['ok'=>false,'error'=>'Zoho customer search is temporarily unavailable. No customer information was changed.','message'=>'Zoho customer search is temporarily unavailable. No customer information was changed.']);}
