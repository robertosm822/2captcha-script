<?php
/*
$filename - File name and local path. URL is not supported.
$apikey   - Your API key
$rtimeout - timeout of answer check
$mtimeout - max waiting time of answer

$is_verbose - false(commenting OFF),  true(commenting ON)

Additional captcha settings:
$is_phrase - 0 OR 1 - captcha contains two or more words
$is_regsense - 0 OR 1 - case sensitive captcha
$is_numeric -  0 OR 1 OR 2 OR 3
0 = parameter is not used (default value)
1 = captcha contains numbers only
2 = captcha contains letters only
3 = captcha contains numbers only or letters only
$min_len    -  0 - unlimited, otherwise sets the max length of the answer
$max_len    -  0 - unlimited, otherwise sets the min length of the answer
$language 	- 0 OR 1 OR 2 
0 = parameter is not used (default value)
1 = cyrillic captcha
2 = latin captcha

usage examples:
$text=recognize("captcha.jpg","YOUR_KEY_HERE",true, "2captcha.com");

$text=recognize("/path/to/file/captcha.jpg","YOUR_KEY_HERE",false, "2captcha.com");  

$text=recognize("/path/to/file/captcha.jpg","YOUR_KEY_HERE",false, "2captcha.com",1,0,0,5);  

*/

// capturar imagem do site: http://app.anp.gov.br/anp-cpl-web/public/simp/consulta-base-distribuicao/consulta.xhtml
$urlAnpBase='http://app.anp.gov.br/';
$urlANP = 'http://app.anp.gov.br/anp-cpl-web/public/simp/consulta-base-distribuicao/consulta.xhtml';
require('./simple_html_dom.php');

function baixarImg($imgurl){
    if( !@copy( $imgurl, './img/testeCaptcha.jpg' ) ) {
        $errors= error_get_last();
        // echo "COPY ERROR: ".$errors['type'];
        // echo "<br />\n".$errors['message'];
    } else {
        // echo "File copied from remote!";
    }
}

$html = file_get_html($urlANP);
$links = array();
//print_r($html->find('img[id="frmConsulta:CaptchaImgID"]'));

foreach($html->find('img[id="frmConsulta:CaptchaImgID"]') as $img) {
 $contents[] = $img->src;
}
 
//print_r($contents);

define("imgNewCaptcha",$urlAnpBase.$contents[0]);
//echo '<img src="'.constant('imgNewCaptcha').'">';

// armazenar imagem do captcha em pasta local
baixarImg(constant('imgNewCaptcha'));

//tratamento de erro no metodo de leitura da url
function my_file_get_contents( $site_url ){
	$ch = curl_init();
	$timeout = 10; // set to zero for no timeout
	curl_setopt ($ch, CURLOPT_URL, $site_url);
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    ob_start();
	curl_exec($ch);
	curl_close($ch);
	$file_contents = ob_get_contents();
	ob_end_clean();
	return $file_contents;
}

function recognize(
            $filename,
            $apikey,
            $is_verbose = true,
            $domain="2captcha.com",
            $rtimeout = 5,
            $mtimeout = 120,
            $is_phrase = 0,
            $is_regsense = 0,
            $is_numeric = 4,
            $min_len = 0,
            $max_len = 5,
            $language = 0
            )
{
	if (!file_exists($filename))
	{
		if ($is_verbose) echo "file $filename not found\n";
		return false;
	}

    if (function_exists('curl_file_create')) { // php 5.5+ 
        $cFile = curl_file_create($filename, mime_content_type($filename), 'file'); 
    } else { // 
        $cFile = '@' . realpath($filename);
    } 

    $postdata = array(
        'method'    => 'post', 
        'key'       => $apikey, 
        'file'      => $cFile,
        'phrase'	=> $is_phrase,
        'regsense'	=> $is_regsense,
        'numeric'	=> $is_numeric,
        'min_len'	=> $min_len,
        'max_len'	=> $max_len,
		'language'	=> $language
        
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,             "https://2captcha.com/in.php");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,     1);
    curl_setopt($ch, CURLOPT_TIMEOUT,             60);
    curl_setopt($ch, CURLOPT_POST,                 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,         $postdata);
    $result = curl_exec($ch);
    if (curl_errno($ch)) 
    {
    	if ($is_verbose) echo "CURL returned error: ".curl_error($ch)."\n";
        return false;
    }
    curl_close($ch);
    if (strpos($result, "ERROR")!==false)
    {
    	if ($is_verbose) echo "server returned error: $result\n";
        return false;
    }
    else
    {
        $ex = explode("|", $result);
        $captcha_id = $ex[1];
    	if ($is_verbose) // echo "captcha sent, got captcha ID $captcha_id\n https://2captcha.com/res.php?key=".$apikey.'&action=get&id='.$captcha_id."\n";
        $waittime = 0;

        if ($is_verbose) // echo "waiting for $rtimeout seconds\n";
        sleep($rtimeout);
        $x = 1;
        while($x < 15)
        {

            $result = my_file_get_contents("https://2captcha.com/res.php?key=".$apikey.'&action=get&id='.$captcha_id);
            if (strpos($result, 'ERROR')!==false)
            {
            	if ($is_verbose) echo "server returned error: $result\n";
                return false;
            }
            if ($result=="CAPCHA_NOT_READY")
            {
            	if ($is_verbose) echo "captcha is not ready yet\n";
            	$waittime += $rtimeout;
            	if ($waittime>$mtimeout) 
            	{
            		if ($is_verbose) echo "timelimit ($mtimeout) hit\n";
            		break;
            	}
        		if ($is_verbose) echo "waiting for $rtimeout seconds\n";
            	sleep($rtimeout);
            }
            else
            {
            	$ex = explode('|', $result);
            	if (trim($ex[0])=='OK') return trim($ex[1]);
            }
            $x++;
        }
        $urlCodeOk= "\n\n http://2captcha.com/res.php?key=".$apikey.'&action=get&id='.$captcha_id."\n";
          
        return $urlCodeOk;
    }
}

$urlCodeOk = recognize('./img/testeCaptcha.jpg', "1b755845270ee8614f617701ef345132");

// $url_codigo_img = 'http://2captcha.com/res.php?key=1b755845270ee8614f617701ef345132&action=get&id=63252113604';

$html = new simple_html_dom();
$urlFinal = (string)trim($urlCodeOk);
$html->load_file($urlFinal);
$dados = $html->plaintext;
$str = explode('|', $dados);
if($str[0] != 'CAPCHA_NOT_READY'){
    echo $str[1];
}else {
    echo $str[0];
}

exit();
