<?php
namespace gpt2024;

use \app_ed_tech\pdoDb;
use \app_ed_tech\edTech;

/**
 * JSON-Export/-Import von Prompt-Listen (Workshop-Template oder Workshop).
 *
 * Ablauf: Template als JSON herunterladen, lokal (z. B. mit KI) bearbeiten,
 * als neuen Workshop hochladen (oder einen bestehenden Workshop ersetzen), Workshop aktiv setzen.
 */
class workshop_json {

  const FORMAT = "collectivegpt-workshop";
  const VERSION = 1;

  /**
   * @param template_prompt[] $prompts
   * @return array
   */
  public static function build($typ, $id, $name, $lang, $beschreibung, $prompts) {
    $items = [];
    foreach ($prompts as $p) {
      $prompt = html_entity_decode((string) $p->prompt, ENT_QUOTES);
      $gptCache = null;
      $cacheData = json_decode((string) $p->chatgpt_json, true);
      if (is_array($cacheData)) {
        $gptCache = ["prompt" => $prompt, "daten" => $cacheData];
      }
      $items[] = [
        "reihenfolge" => intval($p->favorite),
        "prompt" => $prompt,
        "html_code" => (string) $p->html_code,
        "ki_vorgaben" => array_values($p->ki_vorgaben),
        "gpt_cache" => $gptCache,
        "quelle_id" => intval($p->template_id),
      ];
    }

    return [
      "format" => self::FORMAT,
      "version" => self::VERSION,
      "hinweis" => "Bearbeitbar: name, lang, beschreibung und je Prompt reihenfolge (0 = in der Live-Liste ausgeblendet), prompt, html_code, ki_vorgaben (Liste aus {antwort, gewicht}, leer = echte GPT-Antwort). Prompts dürfen ergänzt, gelöscht und umsortiert werden. gpt_cache nicht bearbeiten: er wird nur übernommen, solange prompt unverändert bleibt.",
      "name" => html_entity_decode((string) $name, ENT_QUOTES),
      "lang" => (string) $lang,
      "beschreibung" => html_entity_decode((string) $beschreibung, ENT_QUOTES),
      "quelle" => [
        "typ" => $typ,
        "id" => intval($id),
        "exportiert" => date("c"),
      ],
      "prompts" => $items,
    ];
  }

  public static function sendDownload($data, $filename) {
    $filename = edTech::formatURF8toSimpleAssci($filename) . ".json";
    header("Content-Type: application/json; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Cache-Control: no-cache, must-revalidate");
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
  }

  /**
   * Liest die hochgeladene Datei (Feld "json_file") und prueft das Grundformat.
   * @return array
   */
  public static function readUpload() {
    if (!isset($_FILES["json_file"]) || $_FILES["json_file"]["error"] !== UPLOAD_ERR_OK) {
      throw new \Exception("Keine JSON-Datei hochgeladen.");
    }
    $raw = file_get_contents($_FILES["json_file"]["tmp_name"]);
    // UTF-8-BOM entfernen, falls ein Editor eins schreibt
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
      throw new \Exception("Die Datei ist kein gültiges JSON: " . json_last_error_msg());
    }
    if (isset($data["format"]) && $data["format"] !== self::FORMAT) {
      throw new \Exception("Unbekanntes Format „" . htmlspecialchars((string) $data["format"]) . "“.");
    }
    if (!isset($data["prompts"]) || !is_array($data["prompts"])) {
      throw new \Exception("Im JSON fehlt die Liste „prompts“.");
    }
    foreach ($data["prompts"] as $i => $item) {
      if (!is_array($item) || !isset($item["prompt"]) || !is_string($item["prompt"])) {
        throw new \Exception("Prompt Nr. " . ($i + 1) . " hat kein Textfeld „prompt“.");
      }
    }
    return $data;
  }

  public static function cleanLang($data, $fallback = "de") {
    $lang = strtolower(trim((string) ($data["lang"] ?? "")));
    return in_array($lang, ["de", "en"]) ? $lang : $fallback;
  }

  /**
   * Legt alle Prompts aus dem JSON fuer den Besitzer an.
   * @return int Anzahl
   */
  public static function importPrompts($data, $f_workshop_template_id, $f_workshops_id) {
    $n = 0;
    foreach ($data["prompts"] as $i => $item) {
      $prompt = trim($item["prompt"]);
      $favorite = isset($item["reihenfolge"]) ? intval($item["reihenfolge"]) : $i + 1;

      $new_id = template_prompt::insertToDb($f_workshop_template_id, $f_workshops_id, $prompt, $favorite);
      $theItem = new template_prompt($new_id);
      $theItem->html_code = (string) ($item["html_code"] ?? "");
      $theItem->ki_vorgaben = template_prompt::cleanKiVorgaben($item["ki_vorgaben"] ?? []);

      // GPT-Cache gilt nur fuer den Prompt, fuer den er erzeugt wurde
      $cache = $item["gpt_cache"] ?? null;
      if (is_array($cache) && isset($cache["daten"]) && is_array($cache["daten"])
        && trim((string) ($cache["prompt"] ?? "")) === $prompt) {
        $theItem->chatgpt_json = json_encode($cache["daten"], JSON_UNESCAPED_UNICODE);
      }
      $theItem->saveToDb();
      $n++;
    }
    return $n;
  }

  /**
   * Upload-Formular (normaler POST, kein htmx, wegen Datei).
   */
  public static function getUploadForm($action, $label, $confirm = "") {
    global $rootDir;
    $onsubmit = ($confirm != "" ? " onsubmit='return confirm(" . json_encode($confirm, JSON_HEX_APOS | JSON_HEX_QUOT) . ")'" : "");
    return "<form method='post' enctype='multipart/form-data' action='{$rootDir}/api_gpt?$action' class='d-inline-block'$onsubmit>
      <input type='file' name='json_file' accept='.json,application/json' required>
      <button class='btn btn-outline-dark btn-sm'>$label</button>
    </form>";
  }
}
?>
