<?php
$link = "/var/www/html/instagram_crawler";
include_once $link.'/db.php';
date_default_timezone_set('Asia/Seoul');

$tag = '';  /* 검색할 태그 지정 */
$url = 'https://www.instagram.com/explore/tags/'.$tag.'/'; // instagrame tag urlhttps://www.instagram.com/p/B9ydzRCnURH/

$tag_2 = '';  /* 검색할 태그 지정 */
$url_2 = 'https://www.instagram.com/explore/tags/'.$tag_2.'/'; // instagrame tag urlhttps://www.instagram.com/p/B9ydzRCnURH/

// $url = 'https://www.instagram.com/p/Input_shortcode/'; // instagrame tag url

$results_array_1 = scrape_insta_hash($url);
$results_array_2 = scrape_insta_hash($url_2);

$results_array = array_merge($results_array_1, $results_array_2);

if(isset($results_array)){

  // null => 해시태그 검색으로 넣기
  // '1' => 게시물로 넣기
  if (strpos($url, 'tags') !== false) {
    $type = null;
  } else {
    $type = 1;
  }
  Input($link, $results_array, $type);
}

// ?__a=1 파일 가져오기
function scrape_insta_hash($url) {

    $resource = file_get_contents($url);

    $shards = explode('window._sharedData = ', $resource);

    $insta_json = explode(';</script>', $shards[1]);

    $insta_array = json_decode($insta_json[0], TRUE);

    return $insta_array;
}


// DB 데이터 넣기
// null => 해시태그 검색으로 넣기
// '1' => 게시물로 넣기
function Input($link, $Input_data, $type = null){
  $img_url = "";  // 저장할 이미지 url 입력
  $today = date("Y-m-d");
  $today_time =  date("Y-m-d H:i:s");

  $db = new DBC;  // DB 객체
  $db->DBI();  // DB 선언

  switch ($type) {
    case null:
        $data = $Input_data['entry_data']['TagPage'][0]['graphql']['hashtag']['edge_hashtag_to_media']['edges'];

        $length = count($data);

        for ($i=0; $i < $length ; $i++) {

          /* 크롤링 데이터 가져오기 */
          $shortcode = $data[$i]['node']['shortcode'];
          $text = preg_replace("/\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}/", "",
                  $data[$i]['node']['edge_media_to_caption']['edges'][0]['node']['text']);
          $like = $data[$i]['node']['edge_liked_by']['count'];
          $img = $data[$i]['node']['thumbnail_src'];

          $comment = $data[$i]['node']['edge_media_to_comment']['count'];
          $url = "https://www.instagram.com/p/".$shortcode;

          /* DB 넣기 */
          try {
            $query = "SELECT shortcode FROM GET_INSTAGRAM where shortcode = :shortcode";
            $db->DBQ($query);
            $db->result->bindParam(':shortcode', $shortcode); //바인드 변수로 들어갈 변수 지정
            $db->DBE();
            $db->resultRow();

            /* shortcode 중복 검사 */
            if( $db->resultRow() == 0 || $db->resultRow() == false ){

              // 이미지 다운 로드
              copy($img, $link."/img/$shortcode.jpg");
              $img = $img_url.$shortcode.".jpg";

              $query = "INSERT INTO GET_INSTAGRAM(shortcode, `text`, `like_cnt`, image_url, `comment_cnt`, `url`) values (:shortcode, :text, :like, :img, :comment, :url)";

              $db->DBQ($query);

              /* 바인딩 */
              $db->result->bindParam(':shortcode', $shortcode);
              $db->result->bindParam(':text', $text);
              $db->result->bindParam(':like', $like);
              $db->result->bindParam(':img', $img);
              $db->result->bindParam(':comment', $comment);
              $db->result->bindParam(':url', $url);

              /* Query 실행 */
              $db->DBE();

              /* LOG */
              file_put_contents($link."/logs/"."log(".$today.").txt", "[".$today_time."] ".$shortcode." >> INSERT COMPELETE!\n", FILE_APPEND);
            }else{
              $query = "UPDATE GET_INSTAGRAM SET `like_cnt` = :like, `comment_cnt` = :comment, `updated_at` = now() where shortcode = :shortcode";

              $db->DBQ($query);

              /* 바인딩 */
              $db->result->bindParam(':shortcode', $shortcode);
              $db->result->bindParam(':like', $like);
              $db->result->bindParam(':comment', $comment);

              /* Query 실행 */
              $db->DBE();
            }

          } catch (\Exception $e) {
            /* LOG */
            file_put_contents($link."/logs/"."log(".$today.").txt", "[".$today_time."] ".$shortcode." >> INSERT ERROR! Error:: ".$e->getMessage()."\n", FILE_APPEND);
          }
        }
      break;

    case 1:
        $data = $Input_data['entry_data']['PostPage'][0]['graphql']['shortcode_media'];

        /* 크롤링 데이터 가져오기 */
        $shortcode = $data['shortcode'];
        $text = preg_replace("/\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}/", "",
                $data['edge_media_to_caption']['edges'][0]['node']['text']);
        $like = $data['edge_media_preview_like']['count'];
        $img = $data['display_resources'][0]['src'];
        // $timestamp = date('Y-m-d H:i:s', $data['taken_at_timestamp']);
        $comment = $data['edge_media_preview_comment']['count'];
        $url = "https://www.instagram.com/p/".$shortcode;

        /* DB 넣기 */
        try {
          $query = "SELECT shortcode FROM GET_INSTAGRAM where shortcode = :shortcode";
          $db->DBQ($query);
          $db->result->bindParam(':shortcode', $shortcode); //바인드 변수로 들어갈 변수 지정
          $db->DBE();
          $db->resultRow();

          /* shortcode 중복 검사 */
          if( $db->resultRow() == 0 || $db->resultRow() == false ){

            // 이미지 다운 로드
            copy($img, $link."/img/$shortcode.jpg");
            $img = $img_url.$shortcode.".jpg";

            $query = "INSERT INTO GET_INSTAGRAM(shortcode, `text`, `like_cnt`, image_url, `comment_cnt`, `url`) values (:shortcode, :text, :like, :img, :comment, :url)";

            $db->DBQ($query);

            /* 바인딩 */
            $db->result->bindParam(':shortcode', $shortcode);
            $db->result->bindParam(':text', $text);
            $db->result->bindParam(':like', $like);
            $db->result->bindParam(':img', $img);
            $db->result->bindParam(':comment', $comment);
            $db->result->bindParam(':url', $url);

            /* Query 실행 */
            $db->DBE();

            /* LOG */
            file_put_contents($link."/logs/"."log(".$today.").txt", "[".$today_time."] ".$shortcode." >> INSERT COMPELETE!\n", FILE_APPEND);
          }else{
            $query = "UPDATE GET_INSTAGRAM SET `like_cnt` = :like, `comment_cnt` = :comment, `updated_at` = now() where shortcode = :shortcode";

            $db->DBQ($query);

            /* 바인딩 */
            $db->result->bindParam(':shortcode', $shortcode);
            $db->result->bindParam(':like', $like);
            $db->result->bindParam(':comment', $comment);

            /* Query 실행 */
            $db->DBE();
          }

        } catch (\Exception $e) {

          /* LOG */
          file_put_contents($link."/logs/"."log(".$today.").txt", "[".$today_time."] ".$shortcode." >> INSERT ERROR! Error:: ".$e->getMessage()."\n", FILE_APPEND);
        }
      break;
  }

  file_put_contents($link."/logs/cron_log/" ."cron_log(".$today.").txt", "[".$today_time."] >> EXECUTED !\n", FILE_APPEND);

  $db->DBO();
}

?>
