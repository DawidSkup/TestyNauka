<?php
$base = 'http://127.0.0.1:8099';
$jar = sys_get_temp_dir() . '/d2.txt';
@unlink($jar);

function rq($m,$u,$p=null,$f=true){global $jar;$ch=curl_init($u);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>$f,CURLOPT_MAXREDIRS=>5,CURLOPT_COOKIEJAR=>$jar,CURLOPT_COOKIEFILE=>$jar,CURLOPT_TIMEOUT=>20]);if($m==='POST'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($p??[]));}$r=curl_exec($ch);$s=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$a=explode("\r\n\r\n",$r,2);return ['status'=>$s,'html'=>$a[1]??$r,'head'=>$a[0]??''];}

$pdo=new PDO('sqlite:'.dirname(__DIR__).'/data/app.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec("DELETE FROM users WHERE email='x@y.pl'");
$pdo->exec("DELETE FROM zaproszenia WHERE email='x@y.pl'");
$pdo->exec("DELETE FROM kody WHERE user_id NOT IN (SELECT id FROM users)");

// invite
$pdo->prepare('UPDATE zaproszenia SET uzyty_at=datetime("now") WHERE email="x@y.pl" AND uzyty_at IS NULL')->execute();
$kod='TEST99';
$pdo->prepare("INSERT INTO zaproszenia (email,grupa,kod,wygasa_at,created_at) VALUES ('x@y.pl','materialy',?,datetime('now','+15 minutes'),datetime('now'))")->execute([$kod]);
$pdo=null;

$r=rq('GET',"$base/rejestracja.php");preg_match('/name="csrf" value="([^"]+)"/',$r['html'],$m);
$r=rq('POST',"$base/rejestracja.php",['csrf'=>$m[1],'email'=>'x@y.pl','kod'=>$kod,'imie'=>'X','haslo'=>'Pass1234','haslo2'=>'Pass1234'],false);
echo "REG: {$r['status']}\n";
$r=rq('GET',"$base/admin/index.php");
echo "AFTER: {$r['status']} ".(strpos($r['html'],'Panel startowy')?'OK':'NO')."\n";

@unlink($jar);
$r=rq('GET',"$base/login.php?tryb=uzytkownik");preg_match('/name="csrf" value="([^"]+)"/',$r['html'],$m);
$r=rq('POST',"$base/login.php",['csrf'=>$m[1],'tryb'=>'uzytkownik','kod'=>$kod],false);
echo "LOGIN2: {$r['status']} ".(strpos($r['head'],'login.php')?'STAY':'REDIR')."\n";
var_dump(strpos($r['html'],'Kod jest nieprawidłowy')!==false);
