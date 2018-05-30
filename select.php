<?php

//1.  DB接続します
try {
  $pdo = new PDO('mysql:dbname=gs_db;charset=utf8;host=localhost','root','');
} catch (PDOException $e) {
  exit('DbConnectError:'.$e->getMessage());
}

//２．データ登録SQL作成
$pageType = $_POST["pagetype"];
$newsWord = $_POST["newsword"];
$stmt = $pdo->prepare("SELECT * FROM natalie_news_table WHERE news_type = '$pageType' AND news_contents LIKE '%$newsWord%' ORDER BY news_date DESC LIMIT 10");
$status = $stmt->execute();

//３．データ表示
$view="";
if($status==false) {
    //execute（SQL実行時にエラーがある場合）
  $error = $stmt->errorInfo();
  exit('sqlError:'.$error[2]);

}else{
  //Selectデータの数だけ自動でループしてくれる
  //FETCH_ASSOC=http://php.net/manual/ja/pdostatement.fetch.php
  //演算子.=を使うのはwhile処理でどんどん変数に加えていくから。
  while( $result = $stmt->fetch(PDO::FETCH_ASSOC)){ 
    $view .= '<p>';
    $view .= $result["news_id"].'<br>'.$result["news_date"].'<br>'.$result["news_title"].'<br>'.$result["news_contents"].'<br>';
    $view .= '</p>';  
  }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>検索結果表示</title>
</head>

<div>
  検索結果表示(最大表示：最新10件まで)
    <div><?=$view?></div>
</div>

</body>
</html>