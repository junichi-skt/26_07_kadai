
<?php

// ジャンル選択データが飛ばされてこない時の対応

if(!isset($_POST["pagetype"]) ||$_POST["pagetype"] == ""){
	exit('ParamError');
  }else{
	  $pageType = $_POST["pagetype"];
  }


// 選ばれたジャンルのスクレイピング処理
require_once('phpQueryAllInOne.php');

function scrape_urls() {
	$pageType = $_POST["pagetype"];
	$format = "https://natalie.mu/%s/news";
	$htmlBase = sprintf($format,$pageType);
	$html = file_get_contents($htmlBase);
	$obj  = phpQuery::newDocument($html);
	$article  = $obj->find(".GAE_latestNews > .NA_articleList");
	$contents = $article['a'];

	$urls = [];
	foreach( $contents as $content ){
		$urls[] =  $content->getAttribute('href');
	}
	return $urls;
}

//トップページに出てるニュースを一個ずつとってきてDB格納する処理
function get_ids($urls) {
	$newsIds = [];
	foreach ( $urls as $url ) {
		$html  = file_get_contents($url);
		$obj   = phpQuery::newDocument($html);
		$newsDate = $obj->find(".NA_articleHeader > .NA_attr > p")->text();
		$title = $obj->find(".NA_articleHeader > h1")->text();
		$contents = $obj['.NA_articleBody > p']->text();

		//広告ページ(＝タイトル取れない)以外のときに処理。
		if($title != null){
			preg_match("/\d{6}/", $url, $matches);
			$newsId = $matches[0];

			array_push($newsIds,$newsId);
		}
	}
	return $newsIds;
}

function upinsert($newsId, $newsDate, $pageType, $title, $contents) {
//DB接続(root以降はDBのユーザー名とパスワード)
	try {
		$pageType = $_POST["pagetype"];
		$dbh  = new PDO('mysql:dbname=gs_db;charset=utf8;host=localhost','root','');
		$stmt = $dbh-> query("SET NAMES utf8;");
			$stmt = $dbh->prepare('
			INSERT INTO natalie_news_table (news_id,news_date,news_type,news_title,news_contents,created_at,updated_at)
			VALUES( :a1, :a2, :a3, :a4, :a5, now(), now())
			ON DUPLICATE KEY UPDATE news_title = :a4, news_contents = :a5, updated_at = now()');
			$stmt->bindValue('a1', $newsId, PDO::PARAM_INT);  //Integer（文字列：PARAM_STR/数値の場合 PDO::PARAM_INT)
			$stmt->bindValue('a2', $newsDate, PDO::PARAM_STR);  //Integer（数値の場合 PDO::PARAM_INT)
			$stmt->bindValue('a3', $pageType, PDO::PARAM_STR);  //Integer（数値の場合 PDO::PARAM_INT)
			$stmt->bindValue('a4', $title, PDO::PARAM_STR);  //Integer（数値の場合 PDO::PARAM_INT)
			$stmt->bindValue('a5', $contents, PDO::PARAM_STR);  //Integer（数値の場合 PDO::PARAM_INT)
			$stmt->execute();//executeでSQL実行だよー。
			echo $newsId;
			echo '<br>';
			echo $newsDate;
			echo '<br>';
			echo $title;
			echo '<br>';
			echo $contents;
			echo '<br>';
			print_r($stmt->errorInfo());
	} catch (PODException $e) {
		exit('DbConnectError:'.$e->getMessage());
  }
}

function build_json() {
	$dbh  = new PDO('mysql:dbname=gs_db;charset=utf8;host=localhost','root','');
	$stmt = $dbh-> query("SET NAMES utf8;");
	$stmt = $dbh->query("SELECT * FROM natalie_news_table ORDER BY news_date DESC limit 4");

	$res  = [];
	foreach ($stmt as $row) {
		print($row['news_contents']);
		array_push(
			$res,
			array(
				'uid'			 => $row['news_id'],
				'titleText'		 => $row['news_title'],
				'updateDate'	 => $row['news_date'],
				'mainText'		 => $row['news_contents'],
				'redirectionUrl' => "https://natalie.mu/".$row['news_type']."/news/".$row['news_id']
			)
		);
	}

	$json = json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	return $json;
}

function scp_json($file) {
	$cmd = sprintf("scp -i ~/pem/sekita.pem ./%s ec2-user@54.91.175.119:/usr/share/nginx/html/", $file);
	echo exec($cmd);
}

$urls = scrape_urls();
$newsIds  = get_ids($urls);
foreach( $newsIds as $newsId ) {
	$pageType = $_POST["pagetype"];
	$html	  = file_get_contents(sprintf("https://natalie.mu/%s/news/%s", $pageType , $newsId));
	$obj	  = phpQuery::newDocument($html);
	$str = $obj->find(".NA_articleHeader > .NA_attr > p")->text();
	$title = $obj->find(".NA_articleHeader > h1")->text();
	$contents = $obj['.NA_articleBody > p']->text();
	$search = array("年","月","日");
	$replace = array("-","-","");
	$newsDate = str_replace($search,$replace,$str);
	upinsert($newsId, $newsDate, $pageType, $title, $contents);
	echo $newsId;
}

$json = build_json();
$filename = 'gs.json';
$json_utf8 = mb_convert_encoding($json, 'UTF-8');
print($json);
file_put_contents($filename, $json_utf8);
scp_json($filename);

?>