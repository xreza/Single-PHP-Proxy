<?php
$TEMP_DIR='temp';
if(!file_exists ($TEMP_DIR)){
  mkdir("$TEMP_DIR", 0777, true);
}
error_reporting(0);
if(isset($_REQUEST['clear'])&&$_REQUEST['clear']=='clear'){
  clearTemp(true);
  header('Location: '.$_SERVER['PHP_SELF']);
  die();
}else{
  clearTemp(false);
}
if(isset($_REQUEST['url'])&&!empty($_REQUEST['url'])){
  $url = $_REQUEST['url'];
  $encode=(isset($_REQUEST['encode'])&&$_REQUEST['encode']=='yes')?'yes':'no';
  $full=(isset($_REQUEST['full'])&&$_REQUEST['full']=='yes')?'yes':'no';
  $fixhref=(isset($_REQUEST['fixhref'])&&$_REQUEST['fixhref']=='yes')?'yes':'no';
  loadPage($url,$encode,$full,$fixhref);
}
else{
  ?>
  <form method="get" action="<?=$_SERVER['PHP_SELF']?>">
    <input type="text" name="url" style="width:1000px" /><br/>
    Proxy full resources <input type="checkbox" name="full" value="yes" <?=is_dir($TEMP_DIR)?'':'disabled="disabled"'?>/>
    Change encoding <input type="checkbox" name="encode" value="yes" checked="checked"/>
    Fix hrefs <input type="checkbox" name="fixhref" value="yes" checked="checked"/><br/>
    <input type="submit" value="GO"/>
    <button tabIndex="-1" onclick="if(confirm('Sure?')==true){window.location.href='?clear=clear';} return false;">Clear all cookies</button>
  </form>
  <?
}

function loadPage($targetUrl,$encode,$full,$fixhref){
  global $TEMP_DIR;
  $localHttpProtocol=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!='off')?'https://':'http://';
  //  Prepend http protocol if not exists
  if(!preg_match("/^(?:https?:\\/\\/).+/i",$targetUrl)){
    $targetUrl='http://'.$targetUrl;
  }
  //  Remove duplicated '/'
  $targetUrl=preg_replace("/([^:\\/]+?)(\\/\\/+)/i","$1/",$targetUrl);
  //  Figure out local and target urls
  preg_match("/^(?:https?:\\/\\/)(?:.+\\/|.+)/i",$targetUrl,$basicTargetUrlMatch);
  $basicTargetUrl=$basicTargetUrlMatch[0];
  preg_match("/^(?:https?:\\/\\/)((?:(?!\\/).)+)[\\/]?/i",$basicTargetUrl,$veryBasicTargetUrlMatch);
  $veryBasicTargetLocalUrl=$veryBasicTargetUrlMatch[1];
  preg_match("/^(?:https?:\\/\\/)(?:.+\\/|.+)/i",$localHttpProtocol.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'],$basicLocalUrlMatch);
  $basicLocalUrl=$basicLocalUrlMatch[0];

  //  Get original html view
  $cookieFile=$TEMP_DIR.'/'.'CURLCOOKIE_'.urlencode($veryBasicTargetLocalUrl).".txt";
  //$UAIE = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; SLCC1; .NET CLR 2.0.50727; .NET CLR 3.0.04506; .NET CLR 3.5.21022; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
  $UAChrome='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.96 Safari/537.36';
  $ch = curl_init();
  curl_setopt($ch,CURLOPT_URL, $targetUrl);
  curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
  curl_setopt($ch,CURLOPT_USERAGENT,$UAChrome);
  curl_setopt($ch,CURLOPT_COOKIEFILE,$cookieFile);
  curl_setopt($ch,CURLOPT_COOKIEJAR,$cookieFile);
  //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  $html = curl_exec ($ch);
  curl_close($ch);

  if($full=='yes'){
    //  Fix full resources
    $resPattern="/<.+?(?:src=|href=|url)['\"\\(]?((?:(?![>'\"]).)*?\\.(?:jpg|jpeg|png|gif|bmp|ico|js|css))['\"\\)]?.*?(?:\\/>|>)/i";
    preg_match_all($resPattern,$html,$matchReses);
    for($i=0;$i<count($matchReses[0]);$i++){
      if(strlen($matchReses[1][$i])<=0){
        continue;
      }
      $newResPath=downloadToTemp($matchReses[1][$i],$basicTargetUrl,$TEMP_DIR,$basicLocalUrl);
      $html=str_replace($matchReses[0][$i],str_replace($matchReses[1][$i],$newResPath,$matchReses[0][$i]),$html);
    }
  }

  if($fixhref=='yes'){
    //  Fix href for web links
    $hrefPattern="/<.+?(?:src=|href=|action=)['\"]?((?!(?:(?:https?:)?\\/\\/)|javascript:)(?:(?![>'\"]).)*)['\"]?.*?(?:\\/>|>)/i";
    preg_match_all($hrefPattern,$html,$hrefMatches);
    for($i=0;$i<count($hrefMatches[0]);$i++){
      if(strlen($hrefMatches[1][$i])<=0){
        continue;
      }
      $html=str_replace($hrefMatches[0][$i],str_replace($hrefMatches[1][$i],$basicTargetUrl.'/'.$hrefMatches[1][$i],$hrefMatches[0][$i]),$html);
    }
  }

  //  Add onclick method for href to avoid jumping out
  $html=preg_replace('/href=/','onclick="window.location.href=\''.$localHttpProtocol.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?url=\'+escape(this.href)+\'&encode='.$encode.'\'+\'&fixhref='.$fixhref.'\'+\'&full='.$full.'\';return false;" href=',$html);

  //  Output html view
  header('Content-Security-Policy: '.'upgrade-insecure-requests');
  echo '<meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">';
  echo '<div onclick="window.open(\''.$targetUrl.'\');" style="width:50%;height:10px;background:red;position:fixed;top:0;z-index:1000000;left:0"></div>';
  echo '<div onclick="window.location.href=\''.$localHttpProtocol.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'\';" style="width:50%;height:10px;background:blue;position:fixed;top:0;z-index:1000000;left:50%"></div>';
  echo $encode=='yes'?changeEncoding($html):$html;
}

function downloadToTemp($fileUrl,$basicTargetUrl,$tempDir,$basicLocalUrl){
  $needPrepend=false;
  $localHttpProtocol=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!='off')?'https://':'http://';
  if(preg_match("/^(?:\\/\\/).+/i",$fileUrl)){
    $fileUrl='http:'.$fileUrl;
  }else if(!preg_match("/^(?:https?:\\/\\/).+/i",$fileUrl)){
    $needPrepend=true;
    //$fileUrl=$basicTargetUrl.'/'.$fileUrl;
  }
  $splitedUrl=explode(".",$fileUrl);
  $ext=$splitedUrl[count($splitedUrl)-1];
  do{
    $tempFilename = rand(0,100000).'.'.$ext;
  }while(file_exists($tempFilename));
  if($needPrepend){
    do{
      $attemptFileUrl=$basicTargetUrl.'/'.$fileUrl;
      $downloadedFile=file_get_contents(html_entity_decode($attemptFileUrl));
      $basicTargetUrl=substr($basicTargetUrl,0,strrpos($basicTargetUrl,"/"));
    }while(empty($downloadedFile)&&!preg_match("/^(?:https?:\\/\\/).+?/i",$basicTargetUrl));
  }else{
    $downloadedFile=file_get_contents(html_entity_decode($fileUrl));
  }
  if(!empty($downloadedFile)){
    $newFileUrl=$basicLocalUrl.'/'.$tempDir.'/'.$tempFilename;
    file_put_contents($tempDir.'/'.$tempFilename,$downloadedFile);
    return $newFileUrl;
  }else{
    return $fileUrl;
  }
}

function clearTemp($clearCookies){
  global $TEMP_DIR;
  $dirTemp=opendir($TEMP_DIR);
  while ($file=readdir($dirTemp)) {
    if($file!="." && $file!="..")
    {
      if(strpos($file,"CURLCOOKIE_")!==false && !$clearCookies)
      continue;
      try{
        $fullpath=$TEMP_DIR."/".$file;
        unlink($fullpath);
      }catch(Exception $ee){}
      }
    }
  }

  function changeEncoding($text){
    $encodeType=mb_detect_encoding($text,array('UTF-8','ASCII','GBK'));
    if ($encodeType=='UTF-8') {
      return $text;  //No need to change
    } else {
      //return iconv($encodeType,"UTF-8//ignore",$text);
      return mb_convert_encoding($text,"UTF-8",$encodeType);  //Change to UTF-8
    }
  }
  ?>
