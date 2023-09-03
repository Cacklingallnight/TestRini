<?php
script($_POST['phrases'], intval($_POST['region']), $_POST['token']);
function script($requests, $region, $token)
{
   mb_internal_encoding("UTF-8");
   mb_http_output("UTF-8");
   $array = explode("\n", $requests);
   foreach ($array as &$line) {
      $line = trim($line);
   }
   unset($line);
   $batchSize = 50;
   $numRequests = count($array);
   $numBatches = ceil($numRequests / $batchSize);
   $result = [];
   $result[0] = array('Запрос', 'Частота');
   for ($batch = 0; $batch < $numBatches; $batch++) {
      $start = $batch * $batchSize;
      $batchStrings = array_slice($array, $start, $batchSize);
      processBatch($batchStrings, $token, $result, $region);
   }
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
function processBatch($batchPhrases, $token, &$result, $geoId = 215, $locale = 'ru')
{
   $batchSize = 10;
   $numRequests = count($batchPhrases);
   $numBatches = ceil($numRequests / $batchSize);
   for ($batch = 0; $batch < $numBatches; $batch++) {
      $start = $batch * $batchSize;
      $phrases = array_slice($batchPhrases, $start, $batchSize);
      createNewWordstatReport($locale, $token, $phrases, $geoId);
   }
   $attempts = 0;
   while ($attempts < 5) {
      sleep(1);
      $wordstatReportList = getWordstatReportList($locale, $token);
      foreach ($wordstatReportList->data as $report) {
         if ($report->StatusReport == 'Done') {
            $response = getWordstatReport($locale, $token, $report->ReportID);
            foreach ($response->data as $json) {
               $result[] = array($json->SearchedWith[0]->Phrase, $json->SearchedWith[0]->Shows);
            }
            deleteWordstatReport($locale, $token, $report->ReportID);
         }
      }
      $attempts++;
   }
}
function getWordstatReportList($locale, $token)
{
   $url = 'https://api-sandbox.direct.yandex.ru/live/v4/json/';
   $data = array(
      'method' => 'GetWordstatReportList',
      'locale' => $locale,
      'token' => $token
   );
   return handlePost($data, $url);
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
