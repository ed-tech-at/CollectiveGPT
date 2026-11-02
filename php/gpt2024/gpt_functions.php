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

const OPTIONEN_FILE = __DIR__ . "/../../optionen.json";
const CHART_CONFIG_FILE = __DIR__ . "/../../chart-config.json";

/**
 * Standardwerte fuer optionen.json (nicht im Git, wird bei Bedarf angelegt).
 * active_workshop -1 = Chatbot pausiert; p/h/c/prompt_id = aktueller Prompt, HTML, Chart-Flag.
 */
static function getOptionenDefaults () {
  return (object) [
    "active_workshop" => -1,
    "lang" => "de",
    "p" => "",
    "h" => "",
    "c" => 0,
    "prompt_id" => 0,
  ];
}

/**
 * Liest optionen.json. Fehlt die Datei oder ist sie kaputt, wird sie mit Standardwerten angelegt;
 * fehlende Schluessel werden ergaenzt.
 */
static function getOptionen () {
  $a = null;
  if (is_file(self::OPTIONEN_FILE)) {
    $a = json_decode(file_get_contents(self::OPTIONEN_FILE));
  }

  $defaults = self::getOptionenDefaults();
  if (!is_object($a)) {
    $a = $defaults;
    self::setOptionen($a);
    return $a;
  }

  $ergaenzt = false;
  foreach ($defaults as $key => $value) {
    if (!property_exists($a, $key)) {
      $a->$key = $value;
      $ergaenzt = true;
    }
  }
  if ($ergaenzt) {
    self::setOptionen($a);
  }
  return $a;
}

static function setOptionen ($jsonObj) {
  $jstext = json_encode($jsonObj, JSON_UNESCAPED_UNICODE);
  if (file_put_contents(self::OPTIONEN_FILE, $jstext, LOCK_EX) === false) {
    error_log("CollectiveGPT: optionen.json nicht schreibbar (" . self::OPTIONEN_FILE . ")");
    return 0;
  }
  return 1;
}

/**
 * Liest chart-config.json (zuletzt an die Teilnehmenden gesendetes Diagramm).
 * Fehlt die Datei, wird eine leere Konfiguration angelegt.
 */
static function getChartConfig () {
  $a = null;
  if (is_file(self::CHART_CONFIG_FILE)) {
    $a = json_decode(file_get_contents(self::CHART_CONFIG_FILE));
  }
  if (!is_object($a)) {
    $a = new \stdClass();
    self::setChartConfig($a);
  }
  return $a;
}

static function setChartConfig ($jsonObj) {
  try {
    $jstext = json_encode($jsonObj);
    file_put_contents(self::CHART_CONFIG_FILE, $jstext, LOCK_EX);
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
 *
 * Hinweis: gpt-oss ist ein Reasoning-Modell (Harmony-Format). Es erzeugt zuerst interne
 * "analysis"-Tokens und erst danach die sichtbare Antwort im "final"-Kanal. Ein sehr kleines
 * max_tokens (z.B. 4) wird komplett vom Reasoning verbraucht -> content bleibt leer. Deshalb
 * ziehen wir max_tokens auf eine Untergrenze hoch und lesen als Fallback den Reasoning-Kanal.
 */
public static function chatWizard($messages, $n = 1, $max_tokens = 4, $temperature = 1.0)
{
  global $openai_api_key, $openai_base_url, $openai_model;

  // Reasoning-Modelle brauchen genug Spielraum, um durch die (mit reasoning_effort=low kurze)
  // Analyse-Phase bis zur eigentlichen Antwort zu kommen. max_tokens ist nur eine Obergrenze;
  // bei einer kurzen Satz-Fortsetzung stoppt das Modell selbst deutlich frueher.
  $MIN_MAX_TOKENS = 1024;
  if ($max_tokens < $MIN_MAX_TOKENS) {
    $max_tokens = $MIN_MAX_TOKENS;
  }

  $url = rtrim($openai_base_url, "/") . "/chat/completions";

  $payload = [
    "model"           => $openai_model,
    "messages"        => $messages,
    "n"               => $n,
    "max_tokens"      => $max_tokens,
    "temperature"     => $temperature,
    "logprobs"        => true,
    "reasoning_effort" => "low", // gpt-oss: wenig Reasoning -> schneller + kuerzere Antwort
  ];

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 300,
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
    $result["r{$index}"] = self::shortenContinuation(self::extractChoiceText($choice));
    $result["p{$index}"] = $choice["logprobs"]["content"][0]["logprob"] ?? null;
  }
  $result["usage"] = $response["usage"] ?? null;

  return json_encode($result, JSON_UNESCAPED_UNICODE);
}

/**
 * Sicherheitsnetz: kuerzt die Antwort auf eine kurze Satz-Fortsetzung. Nimmt nur die erste
 * Zeile, schneidet am ersten Satzende ab und begrenzt zusaetzlich hart auf wenige Woerter.
 */
private static function shortenContinuation($text)
{
  $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
  if ($text === "") {
    return "";
  }

  // Bis zum ersten Satzende (. ! ?) inkl. Satzzeichen.
  if (preg_match('/^.*?[.!?]/u', $text, $m)) {
    $text = $m[0];
  }

  // Harte Obergrenze an Woertern.
  $MAX_WORDS = 8;
  $words = preg_split('/\s+/u', $text);
  if (count($words) > $MAX_WORDS) {
    $text = implode(" ", array_slice($words, 0, $MAX_WORDS));
  }

  return $text;
}

/**
 * Holt den Antworttext aus einer OpenAI-Choice. Deckt neben dem Standardfeld message.content
 * auch Reasoning-Modelle (gpt-oss) ab, die den sichtbaren Text ggf. leer lassen und den Inhalt
 * unter reasoning_content / reasoning bzw. content als Array-Parts liefern.
 */
private static function extractChoiceText($choice)
{
  $msg = $choice["message"] ?? [];

  $content = $msg["content"] ?? "";
  // Manche Server liefern content als Array von Parts ([{type:text,text:...}, ...]).
  if (is_array($content)) {
    $parts = [];
    foreach ($content as $part) {
      if (is_string($part)) {
        $parts[] = $part;
      } elseif (isset($part["text"])) {
        $parts[] = $part["text"];
      }
    }
    $content = implode("", $parts);
  }

  if (trim((string) $content) !== "") {
    return $content;
  }

  // Fallback: Reasoning-Kanal (vLLM: reasoning_content, andere: reasoning).
  return $msg["reasoning_content"] ?? $msg["reasoning"] ?? "";
}
}