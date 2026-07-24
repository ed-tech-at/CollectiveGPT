<?php
namespace gpt2024;

use \app_ed_tech\edTech;


class gpt_functions {

public static function sendWsPost ($channel, $msgArray) {
  global $nchan_pw;
  $url = "https://ed-tech.app/pub_id/?password={$nchan_pw}&id=" . $channel;
  $data = json_encode($msgArray);
  $options = [
      'http' => [
          'header' => "Content-type: application/x-www-form-urlencoded\r\n",
          'method' => 'POST',
          'content' => ($data),
          'timeout' => 2
      ],
  ];

  $context = stream_context_create($options);

  return file_get_contents($url, false, $context);
}

static function getOptionen () {
  try {
    $a = json_decode(file_get_contents(__DIR__ . "/../../optionen.json"));

  } catch (\Exception $th) {
    //throw $th;
  }
  return $a;
}

static function setOptionen ($jsonObj) {
  try {
    $jstext = json_encode($jsonObj);
    file_put_contents(__DIR__ . "/../../optionen.json", $jstext);
  } catch (\Exception $th) {
    //throw $th;
  }
  return 1;
}

static function getChartConfig () {
  try {
    $a = json_decode(file_get_contents(__DIR__ . "/../../chart-config.json"));

  } catch (\Exception $th) {
    //throw $th;
  }
  return $a;
}

static function setChartConfig ($jsonObj) {
  try {
    $jstext = json_encode($jsonObj);
    var_dump($jstext);
    file_put_contents(__DIR__ . "/../../chart-config.json", $jstext);
  } catch (\Exception $th) {
    //throw $th;
  }
  return 1;
}

public static function getTimestampDb() {
  return date("Y-m-d H:i:s");
}


  
public static function getSessionStatusMeldung()
{
  $statusArray = [];
  if (isset($_SESSION["status"])) {
    $statusArray = $_SESSION["status"];
  }
  $_SESSION["status"] = [];

  $a = "";
  foreach ($statusArray as $status) {
    # ["warning", "Yes"];
    $a .= "   
<div class='alert alert-dismissible alert-{$status[0]}'>
<button type='button' class='btn-close' data-bs-dismiss='alert'></button>
<p class='mb-0'>{$status[1]}</p>
</div>
";
  }
  return $a;
}

/**
 * @param String $typ primary, secondary, success, danger, warning, info , light, dark
 */

 public static function addSessionStatusMeldung($typ, $text)
{
  $_SESSION["status"][] = [$typ, $text];
}


public static function secureCharForMysql($string)
{
  return edTech::secureCharForMysql($string);

}

/**
 * Ersetzt den bisherigen Python-Proxy (/py-api/chat-wizard, setup/docker/py-gpt/app.py)
 * durch einen direkten OpenAI-kompatiblen Chat-Completions-Call via cURL.
 *
 * Konfiguration in pws.php:
 *   $openai_api_key, $openai_base_url (z.B. https://llm.tugraz.at/llmapi/v1/), $openai_model
 *
 * Rückgabe (JSON-String) im gleichen Format wie zuvor, damit processGptData() unverändert
 * funktioniert: { "r1": ..., "p1": ..., "r2": ..., "p2": ..., "usage": {...} }
 */
public static function chatWizard($messages, $n = 1, $max_tokens = 4, $temperature = 1.0)
{
  global $openai_api_key, $openai_base_url, $openai_model;

  $url = rtrim($openai_base_url, "/") . "/chat/completions";

  $payload = [
    "model"       => $openai_model,
    "messages"    => $messages,
    "n"           => $n,
    "max_tokens"  => $max_tokens,
    "temperature" => $temperature,
    "logprobs"    => true,
  ];

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HTTPHEADER     => [
      "Content-Type: application/json",
      "Authorization: Bearer " . $openai_api_key,
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
  ]);

  $raw = curl_exec($ch);
  if ($raw === false) {
    $err = curl_error($ch);
    return json_encode(["error" => "Request failed: " . $err]);
  }
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  $response = json_decode($raw, true);
  if (!is_array($response) || !isset($response["choices"])) {
    http_response_code($status ?: 502);
    return json_encode(["error" => "Invalid upstream response", "raw" => $raw], JSON_UNESCAPED_UNICODE);
  }

  $result = [];
  foreach ($response["choices"] as $i => $choice) {
    $index = $i + 1;
    $result["r{$index}"] = $choice["message"]["content"] ?? "";
    $result["p{$index}"] = $choice["logprobs"]["content"][0]["logprob"] ?? null;
  }
  $result["usage"] = $response["usage"] ?? null;

  return json_encode($result, JSON_UNESCAPED_UNICODE);
}
}