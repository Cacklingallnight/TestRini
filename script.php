<?php
script($_POST['phrases'], intval($_POST['region']), $_POST['token']);
function script($requests, $region, $token)
{
   mb_internal_encoding("UTF-8");
   mb_http_output("UTF-8");
   $queue = explode("\n", $requests);
   foreach ($queue as &$line) {
      $line = trim($line);
   }
   unset($line);
   $queue = splitToBatches($queue);
   $result = processQueue($token, $queue, $region);
   $fp = fopen('phrases.csv', 'wb');
   foreach ($result as $line) {
      fputcsv($fp, $line, ',');
   }
   fclose($fp);
   $filename = "phrases.csv";
   header("Content-disposition: attachment;filename=$filename");
   readfile($filename);
   exit();
}

function processQueue($token, $queue, $region, $locale = 'ru')
{
   $result = [];
   $result[0] = array('Запрос', 'Частота');
   $inWork = 0;
   while (empty($queue) != true || $inWork != 0) {
      $count = count(getWordstatReportList($token)->data);
      while ($count < 5 && count($queue) != 0) {
         createNewWordstatReport($locale, $token, array_pop($queue), $region);
         $inWork++;
         $count++;
      }
      $wordstatReportList = getWordstatReportList($token);
      foreach ($wordstatReportList->data as $report) {
         if ($report->StatusReport == 'Done') {
            $response = getWordstatReport($locale, $token, $report->ReportID);
            foreach ($response->data as $json) {
               $result[] = array($json->SearchedWith[0]->Phrase, $json->SearchedWith[0]->Shows);
            }
            deleteWordstatReport($locale, $token, $report->ReportID);
            $inWork--;
         }
      }
   }
   return $result;
}

function getWordstatReportList($token, $locale = 'ru')
{
   $url = 'https://api-sandbox.direct.yandex.ru/live/v4/json/';
   $data = array(
      'method' => 'GetWordstatReportList',
      'locale' => $locale,
      'token' => $token
   );
   return handlePost($data, $url);
}
function splitToBatches($queue)
{
   $result = [];
   $batchSize = 10;
   $numRequests = count($queue);
   $numBatches = ceil($numRequests / $batchSize);
   for ($batch = 0; $batch < $numBatches; $batch++) {
      $start = $batch * $batchSize;
      array_push($result, array_slice($queue, $start, $batchSize));
   }
   return $result;
}

function deleteWordstatReport($locale, $token, $wordstatRepostId)
{
   $url = 'https://api-sandbox.direct.yandex.ru/live/v4/json/';
   $data = array(
      'method' => 'DeleteWordstatReport',
      'param' => $wordstatRepostId,
      'locale' => $locale,
      'token' => $token
   );
   $options = array(
      'http' => array(
         'method'  => 'POST',
         'content' => json_encode($data, JSON_UNESCAPED_UNICODE),
         'header' =>  "Content-Type: application/json\r\n" .
            "Accept: application/json\r\n"
      )
   );
   $context = stream_context_create($options);
   $result = file_get_contents($url, false, $context);
   $response = json_decode($result, false);
   return $response->data == 1;
}
function createNewWordstatReport($locale, $token, $phrases, $geoId)
{
   $url = 'https://api-sandbox.direct.yandex.ru/live/v4/json/';
   $data = array(
      'method' => 'CreateNewWordstatReport',
      'param' => array(
         'Phrases' => $phrases,
         'GeoID' => array($geoId)
      ),
      'locale' => $locale,
      'token' => $token
   );
   return handlePost($data, $url);
}
function getWordstatReport($locale, $token, $wordStatReportId)
{
   $url = 'https://api-sandbox.direct.yandex.ru/live/v4/json/';
   $data = array(
      'method' => 'GetWordstatReport',
      'param' => $wordStatReportId,
      'locale' => $locale,
      'token' => $token
   );
   return handlePost($data, $url);
}

function handlePost($data, $url)
{
   $options = array(
      'http' => array(
         'method' => 'POST',
         'content' => json_encode($data, JSON_UNESCAPED_UNICODE),
         'header' => "Content-Type: application/json\r\n" .
            "Accept: application/json\r\n"
      )
   );
   $context = stream_context_create($options);
   $result = file_get_contents($url, false, $context);
   $json = json_decode($result, false);
   if (isset($json->data)) {
      return $json;
   } else if ($json->error_code) {
      echo "Error: code = " . $json->error_code
         . ", str = " . $json->error_str
         . ", detail = " . $json->error_detail;
      die();
   } else {
      echo "Unknown error";
      die();
   }
}
