<?php
require_once('phpQueryAllInOne.php');

function scrape_urls() {
	$html = file_get_contents('https://natalie.mu/music/news');
	$obj  = phpQuery::newDocument($html);
	$article  = $obj->find(".NA_articleList");
	$contents = $article['a'];

	$urls = [];
	foreach( $contents as $content ){
		$urls[] =  $content->getAttribute('href');
	}
	return $urls;
}

function get_ids($urls) {
    $newsIds = [];
	foreach ( $urls as $url ) {
		$html  = file_get_contents($url);
        $obj   = phpQuery::newDocument($html);
        $newsDate = $obj->find(".NA_articleHeader > .NA_attr > p")->text();
		$title = $obj->find(".NA_articleHeader > h1")->text();
		$contents = $obj['.NA_articleBody p']->text();

        if($title != null){
    		preg_match("/\d{6}/", $url, $matches);
	    	$newsId = $matches[0];

            array_push($newsIds, $newsId);
        }
	}
	return $newsIds;
}

function upinsert($id, $title, $desc) {
	try {
		$dbh  = new PDO('mysql:dbname=gs_db;charset=utf8;host=localhost','root','');
		$stmt = $dbh-> query("SET NAMES utf8;");
		$stmt = $dbh->prepare('
			INSERT INTO natalie_news_table2 (id, title, type, description, created_at, updated_at)
			VALUES (:id, :title, :type, :desc, now(), now())
			ON DUPLICATE KEY UPDATE title = :title, description = :desc, updated_at = now()
			');
		$stmt->bindValue(':id', $id);
		$stmt->bindValue(':title', $title);
		$stmt->bindValue(':type', 'news');
		$stmt->bindValue(':desc', $desc);
		$stmt->execute();
		print_r($stmt->errorInfo());
	} catch (PODException $e) {
		exit('DbConnectError:'.$e->getMessage());
  }
}

function build_json() {
	$dbh  = new PDO('mysql:dbname=gs_db;charset=utf8;host=localhost','root','');
	$stmt = $dbh-> query("SET NAMES utf8;");
	$stmt = $dbh->query("SELECT * FROM natalie_news_table2 limit 3");

	$res  = [];
	foreach ($stmt as $row) {
		print($row['description']);
		array_push(
			$res,
			array(
				'uid'			 => $row['id'],
				'titleText'		 => $row['title'],
				'updateDate'	 => "2018-05-29T00:00:00.0Z",
				'mainText'		 => $row['description'],
				'redirectionUrl' => ''
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
	$html	  = file_get_contents(sprintf("https://natalie.mu/stage/news/%s", $newsId));
	$obj	  = phpQuery::newDocument($html);
	$contents = str_replace("  ", "", $obj->find(".NA_articleBody")->text());
	$title	  = $obj->find("h1")->text();
	upinsert($newsId, $title, $contents);
	echo $newsId;
}

$json = build_json();
$filename = 'gs.json';
$json_utf8 = mb_convert_encoding($json, 'UTF-8');
print($json);
file_put_contents($filename, $json_utf8);
scp_json($filename);

?>