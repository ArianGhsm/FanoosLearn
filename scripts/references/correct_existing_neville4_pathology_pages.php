<?php
declare(strict_types=1);

/** Read-only by default; update ONLY 42 SHA-pinned legacy Neville4e source page fields. */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

$root='/srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010';
$paths=[
 'manifest'=>[$root.'/neville4-reviewed-answer-page-candidates-20261010.json','06489c524c61e8fe221208117ce9cdc46e57b3d0a18e5f76215815ea1fc60f82'],
 'sources'=>[$root.'/post-five-page-live-sources.private.json','d8e96cf2e2517405cc6c17944f99fb1e6becc6e9aa1cecc70068c53a05f52291'],
 'answers'=>[$root.'/post-five-page-answer-review.json','62dbe2b7e06408c6d93a42ac023058fc31387eb8f6d80089f66614e40e5fe32e'],
 'original'=>['/srv/fanoos/shared/research/classification/parallel/W04/references/neville-oral-pathology@4e.txt','b240862242cfd48c9b90cdfa5cf8ba43a8e4ffe6900ea5d4e02b09419c40c58a'],
];
$db=null;$locked=false;$receipt=null;$handle=false;$committed=false;
try {
 $opts=[];
 foreach(array_slice($argv,1) as $arg){
  if(!preg_match('/^--(apply|backup|receipt)(?:=(.*))?$/D',$arg,$m)||isset($opts[$m[1]]))throw new RuntimeException('Unexpected CLI option');
  $opts[$m[1]]=$m[2]??'1';
 }
 $apply=isset($opts['apply']);
 if($apply !== (isset($opts['backup'])&&isset($opts['receipt'])))throw new RuntimeException('Apply requires backup+receipt');
 if($apply && (!function_exists('posix_getpwuid')||(posix_getpwuid(posix_geteuid())['name']??'')!=='fanoosupd'))throw new RuntimeException('Authorized updater required');
 foreach($paths as [$file,$sha]){
  if(is_link($file)||!is_file($file)||hash_file('sha256',$file)!==$sha)throw new RuntimeException('Immutable original evidence mismatch');
 }
 $read=static fn(string $k):array=>json_decode((string)file_get_contents($paths[$k][0]),true,64,JSON_THROW_ON_ERROR);
 $manifest=$read('manifest');
 if(($manifest['format']??'')!=='fanoos.pathology.neville4.reviewed-answer-page-candidates.v1'
    ||($manifest['candidate_count']??0)!==42||count($manifest['cases']??[])!==42
    ||($manifest['source_snapshot_sha256']??'')!==$paths['sources'][1]
    ||($manifest['answers_snapshot_sha256']??'')!==$paths['answers'][1]
    ||($manifest['original_pdf_page_marked_sha256']??'')!==$paths['original'][1])throw new RuntimeException('Reviewed batch invalid');
 $sources=[];foreach($read('sources')['rows'] as $x){$k=$x['year'].':'.$x['number'];if(isset($sources[$k]))throw new RuntimeException('Duplicate source snapshot');$sources[$k]=$x;}
 $answers=[];foreach($read('answers')['questions'] as $x){$k=$x['year'].':'.$x['number'];if(isset($answers[$k]))throw new RuntimeException('Duplicate answer snapshot');$answers[$k]=$x;}
 if(count($sources)!==159||count($answers)!==159)throw new RuntimeException('Whole-course snapshot must be complete');
 $text=(string)file_get_contents($paths['original'][0]);
 $map=json_decode((string)file_get_contents(dirname(__DIR__,2).'/data/bank/reference-chapter-pages.json'),true,64,JSON_THROW_ON_ERROR);
 $runs=$map['editions']['neville-oral-pathology@4e']['runs']??[];
 if(count($runs)<19)throw new RuntimeException('Canonical chapter map missing');
 $cases=[];foreach($manifest['cases'] as $x){
  $key=(int)$x['year'].':'.(int)$x['number'];$pg=(int)$x['original_pdf_page'];$pr=(string)$x['printed_page'];
  if(isset($cases[$key])||!isset($sources[$key],$answers[$key])||$x['old_page']!==null
     ||!($x['original_book_fact_reviewed']??false)||strlen((string)$x['proof'])<15
     ||$x['answer_status']!=='final'&&$x['answer_status']!=='amended')throw new RuntimeException('Manifest case invalid');
  $count=0;$chapter=null;foreach($runs as $r){if($pg>=$r[1]&&$pg<=$r[2]){$count++;$chapter=$r[0];}}
  if($count!==1||(string)$chapter!==(string)$x['chapter']
     ||!PrintedBookPageEvidence::corroboratesPageMarkedText($text,$pg,$pr))throw new RuntimeException('Book page or chapter not corroborated');
  if(!preg_match('/^=== PAGE '.$pg.' ===\h*$(.*?)(?=^=== PAGE \d+ ===|\z)/ms',$text,$hit))throw new RuntimeException('Original page absent');
  $norm=static fn(string $v):string=>preg_replace('/\s+/u',' ',mb_strtolower($v));
  if(!str_contains($norm($hit[1]),$norm((string)$x['proof'])))throw new RuntimeException('Original answer-specific evidence missing');
  $cases[$key]=$x;
 }
 if(count($cases)!==42)throw new RuntimeException('Incomplete batch');
 if($apply){
  $backup=realpath((string)$opts['backup']);
  if($backup===false||!str_starts_with($backup.'/','/var/backups/fanoos/'))throw new RuntimeException('Invalid recovery point');
  $verified=BackupManifest::verify($backup);$when=strtotime((string)($verified['created_at']??''));
  if($when===false||abs(time()-$when)>4*3600||(int)($verified['files']['database.sql']['bytes']??0)<1024)throw new RuntimeException('Fresh full verified recovery point missing');
  $receipt=(string)$opts['receipt'];
  if(realpath(dirname($receipt))!==realpath($root)||is_link($receipt)||file_exists($receipt)||!str_ends_with($receipt,'.json'))throw new RuntimeException('Receipt must be new in protected research');
  $handle=@fopen($receipt,'x');if($handle===false)throw new RuntimeException('Cannot reserve exclusive receipt');
  chmod($receipt,0600);
 }
 $db=DatabaseConnection::fromEnvironment();
 if((int)$db->query("SELECT GET_LOCK('fanoos:existing-pathology-source-pages',0)")->fetchColumn()!==1)throw new RuntimeException('Publisher lock busy');
 $locked=true;$db->beginTransaction();
 $find=$db->prepare(<<<'SQL'
SELECT q.id AS question_id,q.stem,q.stem_image,q.status AS question_status,
 s.id AS source_id,s.page,s.anchor_text,s.origin,s.reviewed_by_user_id,s.reviewed_at,s.is_primary,
 n.number AS chapter,n.kind,n.node_key,e.edition_key,r.reference_key,
 v.is_official,v.scope_chapters
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects bs ON bs.id=q.subject_id AND bs.workspace_id=q.workspace_id
JOIN bank_question_sources s ON s.question_id=q.id AND s.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=q.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=q.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=q.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.exam_type_id=et.id
 AND v.subject_id=bs.id AND v.exam_year=si.exam_year AND v.workspace_id=q.workspace_id
WHERE et.type_key='residency' AND si.exam_year=:year AND si.exam_round=1
AND bs.subject_key='oral-pathology' AND q.number_in_sitting=:number
FOR UPDATE
SQL);
 $choice=$db->prepare('SELECT position,text,image FROM bank_question_choices WHERE question_id=:qid ORDER BY position');
 $answer=$db->prepare('SELECT choice_position,status FROM bank_official_answers WHERE question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
 $update=$db->prepare("UPDATE bank_question_sources SET page=:page WHERE id=:sid AND question_id=:qid AND page IS NULL AND origin='ai' AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL");
 $report=[];foreach($cases as $key=>$x){
  $find->execute(['year'=>$x['year'],'number'=>$x['number']]);$rows=$find->fetchAll(PDO::FETCH_ASSOC);
  if(count($rows)!==1)throw new RuntimeException('Unexpected source cardinality '.$key);
  $v=$rows[0];$old=$sources[$key];$q=$answers[$key];
  if($v['source_id']!==$x['source_id']||$v['source_id']!==$old['source_id']||$v['question_id']!==$old['question_id']
     ||$v['page']!==null||$old['page']!==null||$v['anchor_text']!==$old['anchor_text']
     ||$v['origin']!=='ai'||$v['reviewed_at']!==null||$v['reviewed_by_user_id']!==null
     ||(int)$v['is_primary']!==1||$v['edition_key']!=='4e'||$v['reference_key']!=='neville-oral-pathology'
     ||$v['kind']!=='chapter'||$v['node_key']!=='ch'.str_pad((string)$x['chapter'],2,'0',STR_PAD_LEFT)
     ||(string)$v['chapter']!==(string)$x['chapter']||(int)$v['is_official']!==1
     ||$v['question_status']!=='published'||$v['stem']!==$q['stem']||$v['stem_image']!==$q['stem_image'])
    throw new RuntimeException('Protected source/question mismatch '.$key);
  $scope=json_decode((string)$v['scope_chapters'],true,64,JSON_THROW_ON_ERROR);
  if(!in_array((string)$x['chapter'],array_map(static fn($n)=>(string)($n['number']??''),$scope),true))throw new RuntimeException('Not in official syllabus '.$key);
  $choice->execute(['qid'=>$v['question_id']]);
  $actual=array_map(static fn($c)=>['position'=>(int)$c['position'],'text'=>(string)$c['text'],'image'=>$c['image']===null?null:(string)$c['image']],$choice->fetchAll(PDO::FETCH_ASSOC));
  if($actual!==$q['choices'])throw new RuntimeException('Options/media changed '.$key);
  $answer->execute(['qid'=>$v['question_id']]);$a=$answer->fetch(PDO::FETCH_ASSOC);
  if(!is_array($a)||$a['status']!==$q['answer']['status']||(int)$a['choice_position']!==(int)$q['answer']['choice_position']
     ||$a['status']!==$x['answer_status']||(int)$a['choice_position']!==(int)$x['key_position'])throw new RuntimeException('Official answer changed '.$key);
  if($apply){$update->execute(['page'=>$x['printed_page'],'sid'=>$v['source_id'],'qid'=>$v['question_id']]);if($update->rowCount()!==1)throw new RuntimeException('Compare-and-swap failed '.$key);}
  $report[]=['year'=>$x['year'],'number'=>$x['number'],'source_id'=>$v['source_id'],'chapter'=>$x['chapter'],'printed_page'=>$x['printed_page'],'pdf_page'=>$x['original_pdf_page']];
 }
 $out=['format'=>'fanoos.neville4-existing-page-only/1','applied'=>$apply,'count'=>count($report),
  'question_changes'=>0,'choice_changes'=>0,'answer_changes'=>0,'human_source_changes'=>0,
  'source_snapshot_sha256'=>$paths['sources'][1],'manifest_sha256'=>$paths['manifest'][1],'items'=>$report,'utc'=>gmdate('c')];
 if($apply){
  $out['backup_id']=basename($backup);$db->commit();$committed=true;
  $json=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
  if(fwrite($handle,$json)!==strlen($json)||!fflush($handle))throw new RuntimeException('Committed but receipt writing failed; investigate without retry');
  fclose($handle);$handle=false;
 }else{$db->rollBack();}
 echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){
 if($db instanceof PDO&&$db->inTransaction())$db->rollBack();
 if($handle!==false){fclose($handle);if(!$committed&&is_string($receipt)&&is_file($receipt))unlink($receipt);}
 fwrite(STDERR,'Original-4e page-only correction denied: '.$e->getMessage().PHP_EOL);exit(1);
}finally{
 if($locked&&$db instanceof PDO)$db->query("SELECT RELEASE_LOCK('fanoos:existing-pathology-source-pages')");
}
