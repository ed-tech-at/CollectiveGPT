<?php
namespace gpt2024;

use \app_ed_tech\pdoDb;
use \app_ed_tech\edTech;

/**
 * Ein Prompt. Gehoert entweder zu einem Workshop-Template (f_workshop_template_id)
 * oder zu einem konkreten Workshop (f_workshops_id).
 */
class template_prompt {

  public $template_id;
  public $f_workshop_template_id;
  public $f_workshops_id;
  public $f_source_template_id;
  public $prompt;
  public $favorite;
  public $last_time_used;
  public $chatgpt_json;
  public $html_code;
  /** @var array [["antwort" => string, "gewicht" => float], ...] */
  public $ki_vorgaben = [];

  public function __construct($_template_id)
  {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare(
      'SELECT * FROM template_prompt WHERE template_id = :template_id'
    );
    $stmt->execute(['template_id' => $_template_id]);

    $data = $stmt->fetch();
    $stmt->closeCursor();

    if (!empty($data)) {
      $this->template_id = $data['template_id'];
      $this->f_workshop_template_id = $data['f_workshop_template_id'];
      $this->f_workshops_id = $data['f_workshops_id'];
      $this->f_source_template_id = $data['f_source_template_id'];
      $this->prompt = json_decode($data['prompt']);
      $this->favorite = $data['favorite'];
      $this->chatgpt_json = $data['chatgpt_json'];
      $this->html_code = $data['html_code'];
      $this->last_time_used = $data['last_time_used'];
      $this->ki_vorgaben = json_decode($data['ki_vorgaben'], true) ?: [];
    } else {
      throw new \Exception("template_prompt ($_template_id) not found", 404001);
    }
  }

  public function saveToDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE template_prompt SET
      prompt = :prompt,
      chatgpt_json = :chatgpt_json,
      ki_vorgaben = :ki_vorgaben,
      html_code = :html_code,
      favorite = :favorite
    WHERE template_id = :template_id");

    // prompt kommt aus der DB bereits escaped zurueck; vor dem erneuten Escapen dekodieren,
    // sonst wird bei jedem Speichern (z. B. saveGptJson) aus &quot; ein &amp;quot;
    $stmt->execute([
      'prompt' => json_encode(gpt_functions::secureCharForMysql(html_entity_decode((string) $this->prompt, ENT_QUOTES))),
      'favorite' => gpt_functions::secureCharForMysql($this->favorite),
      'chatgpt_json' => ($this->chatgpt_json),
      'ki_vorgaben' => self::kiVorgabenToDb($this->ki_vorgaben),
      'html_code' => ($this->html_code),
      'template_id' => gpt_functions::secureCharForMysql($this->template_id),
    ]);

    $stmt->closeCursor();
  }

  public function updateLastTimeUsed() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE template_prompt SET
      last_time_used = :timee
    WHERE template_id = :template_id");

    $stmt->execute([
      'timee' => time(),
      'template_id' => gpt_functions::secureCharForMysql($this->template_id)
    ]);

    $stmt->closeCursor();
  }

  public static function resetLastTimeUsed($workshops_id) {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE template_prompt SET
      last_time_used = 0 WHERE f_workshops_id = :f_workshops_id");

    $stmt->execute([
      'f_workshops_id' => intval($workshops_id)
    ]);

    $stmt->closeCursor();
  }

  public function updateFromPost() {
    $this->prompt = $_POST["prompt"];
    $this->favorite = $_POST["favorite"];
    $this->html_code = $_POST["html_code"];
    $this->ki_vorgaben = self::parseKiVorgaben($_POST["ki_vorgaben"] ?? "");
  }

  /**
   * @return int NewId
   */
  public static function insertToDb($f_workshop_template_id, $f_workshops_id, $prompt = "", $favorite = 0) {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("INSERT INTO template_prompt
    (f_workshop_template_id, f_workshops_id, prompt, favorite) VALUES
    (:f_workshop_template_id, :f_workshops_id, :prompt, :favorite)");
    $stmt->execute([
      "f_workshop_template_id" => intval($f_workshop_template_id),
      "f_workshops_id" => intval($f_workshops_id),
      "prompt" => json_encode(gpt_functions::secureCharForMysql($prompt)),
      "favorite" => intval($favorite),
    ]);
    $new_id = $db->lastInsertId();
    $stmt->closeCursor();
    return $new_id;
  }

  /**
   * Kopiert den Prompt (inkl. GPT-Cache und KI-Vorgaben) zu einem anderen Besitzer.
   * @return int NewId
   */
  public function copyTo($f_workshop_template_id, $f_workshops_id) {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("INSERT INTO template_prompt
      (f_workshop_template_id, f_workshops_id, f_source_template_id, prompt, chatgpt_json, ki_vorgaben, favorite, html_code)
      SELECT :f_workshop_template_id, :f_workshops_id, template_id, prompt, chatgpt_json, ki_vorgaben, favorite, html_code
      FROM template_prompt WHERE template_id = :template_id");
    $stmt->execute([
      "f_workshop_template_id" => intval($f_workshop_template_id),
      "f_workshops_id" => intval($f_workshops_id),
      "template_id" => $this->template_id,
    ]);
    $new_id = $db->lastInsertId();
    $stmt->closeCursor();
    return $new_id;
  }

  /**
   * Liest KI-Vorgaben aus Freitext, z. B. "Mittwoch, 0.8; Donnerstag: 0.2" oder eine pro Zeile.
   * Gewicht mit Punkt oder Komma, optional in Prozent ("80%"); ohne Gewicht gilt 1.
   * @return array
   */
  public static function parseKiVorgaben($text) {
    $result = [];
    foreach (preg_split('/[\r\n;]+/u', (string) $text) as $line) {
      $line = trim($line);
      if ($line === "") {
        continue;
      }
      $antwort = $line;
      $gewicht = 1.0;
      if (preg_match('/^(.*?)\s*[:,=]\s*(\d+(?:[.,]\d+)?)\s*(%?)$/u', $line, $m)) {
        $antwort = trim($m[1]);
        $gewicht = (float) str_replace(",", ".", $m[2]);
        if ($m[3] === "%") {
          $gewicht = $gewicht / 100;
        }
      }
      if ($antwort === "") {
        continue;
      }
      $result[] = [
        "antwort" => html_entity_decode($antwort, ENT_QUOTES),
        "gewicht" => $gewicht,
      ];
    }
    return $result;
  }

  /**
   * Prueft eine KI-Vorgaben-Liste aus einer JSON-Datei und bringt sie in die DB-Form.
   * @return array
   */
  public static function cleanKiVorgaben($list) {
    if (!is_array($list)) {
      return [];
    }
    $result = [];
    foreach ($list as $item) {
      if (!is_array($item) || !isset($item["antwort"]) || trim((string) $item["antwort"]) === "") {
        continue;
      }
      $result[] = [
        "antwort" => trim((string) $item["antwort"]),
        "gewicht" => (float) str_replace(",", ".", (string) ($item["gewicht"] ?? 1)),
      ];
    }
    return $result;
  }

  public static function kiVorgabenToDb($list) {
    if (empty($list)) {
      return "";
    }
    return json_encode(array_values($list), JSON_UNESCAPED_UNICODE);
  }

  public function getKiVorgabenText() {
    $lines = [];
    foreach ($this->ki_vorgaben as $item) {
      $lines[] = $item["antwort"] . ", " . $item["gewicht"];
    }
    return implode("\n", $lines);
  }

  /**
   * KI-Vorgaben im Format von chatWizard (r1/p1, ...), damit processGptData() sie anzeigt.
   * "manual" sagt dem JS, dass p ein Gewicht und keine Logprob ist.
   * Antworten werden escaped, weil sie im Admin als HTML ausgegeben werden.
   */
  public function getKiVorgabenGptJson() {
    if (empty($this->ki_vorgaben)) {
      return "";
    }
    $result = ["manual" => true];
    foreach (array_values($this->ki_vorgaben) as $i => $item) {
      $result["r" . ($i + 1)] = htmlspecialchars($item["antwort"], ENT_QUOTES);
      $result["p" . ($i + 1)] = $item["gewicht"];
    }
    return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
  }

  /**
   * Was beim Senden in send_prompts uebernommen wird: KI-Vorgaben gehen vor dem echten GPT-Cache.
   */
  public function getEffectiveGptJson() {
    if (!empty($this->ki_vorgaben)) {
      return $this->getKiVorgabenGptJson();
    }
    return $this->chatgpt_json;
  }

  public static function getEditInterface($thePrompt = null, $f_workshop_template_id = 0, $f_workshops_id = 0) {

    admin_gpt::checkLogin();
global $py_api;

    $template_id = ($thePrompt != null ? $thePrompt->template_id : 0);
    if ($thePrompt != null) {
      $f_workshop_template_id = $thePrompt->f_workshop_template_id;
      $f_workshops_id = $thePrompt->f_workshops_id;
    }
    global $rootDir;

    $a = "
    <div class='card mb-3' id='card-id-$template_id'>
    <h3 class='card-header'>Edit Template Prompt ID T$template_id</h3>
    <div class='card-body'>
    <form enctype='multipart/form-data' method='post' hx-post='{$rootDir}/api_gpt?class=template_prompt&save=" . $template_id . "' hx-target='#card-id-" . $template_id . "' hx-swap='outerHTML'>
    <input type='hidden' name='f_workshop_template_id' value='" . intval($f_workshop_template_id) . "'>
    <input type='hidden' name='f_workshops_id' value='" . intval($f_workshops_id) . "'>
    Prompt <textarea name='prompt'  style='width: 100%'>" . ($thePrompt != null ? $thePrompt->prompt : "") . "</textarea><br>
    Favorite <input name='favorite' value='" . ($thePrompt != null ? $thePrompt->favorite : "0") . "'><br>
    HTML <textarea name='html_code' style='width: 100%; min-height: 10rem'>" . ($thePrompt != null ? $thePrompt->html_code : "") . "</textarea><br>
    KI-Vorgaben <small class='text-muted'>(simulierte KI-Antworten mit Gewicht, z. B. <code>Mittwoch, 0.8; Donnerstag: 0.2</code> oder eine pro Zeile; leer = echte GPT-Antwort)</small>
    <textarea name='ki_vorgaben' id='ki_vorgaben_$template_id' style='width: 100%; min-height: 5rem'>" . ($thePrompt != null ? htmlspecialchars($thePrompt->getKiVorgabenText(), ENT_QUOTES) : "") . "</textarea><br>
    <input class='btn btn-success' value='Save' type='submit'>
    <span id='indicator_$template_id' class='htmx-indicator'>Saving... <i class='fas fa-spinner fa-pulse'></i> </span>";
    if ($thePrompt != null) {
      $a .= "
<br>
      GPT <span class='btn btn-info' onclick='loadGptAnswer( \"$py_api\", \"{$thePrompt->prompt}\", 0, $template_id)' >(new answer)</span>";
      if (strlen($thePrompt->chatgpt_json) > 5) {
        $a .= "
      <span class='btn btn-outline-info' onclick='kiVorgabenAusGpt(" . htmlspecialchars($thePrompt->chatgpt_json, ENT_QUOTES) . ", \"#ki_vorgaben_$template_id\")'>GPT-Antwort als KI-Vorgabe übernehmen</span>";
      }
      $a .= ":
      <div id='gpt'></div>
      <div id='gpt-graph'></div>

      ";
      $gptJson = $thePrompt->getEffectiveGptJson();
      if (strlen($gptJson) > 5 ) {
        $a .= "<script>
        processGptData({$gptJson}, 0, {$template_id});
        </script>";
      }

      $a .= "


      <span class='btn btn-secondary' hx-get='{$rootDir}/api_gpt?class=template_prompt&details=$template_id' >Cancel</span>
      <span class='btn btn-danger' hx-target='this' hx-swap='outerHTML' hx-post='{$rootDir}/api_gpt?class=template_prompt&askDel={$template_id}'>Delete?</span>";
    }
    $a .= "</form>

    </div>
    </div>
    ";
    return $a;
  }

  public function deleteFromDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE template_prompt SET is_del = 1 WHERE template_id = :template_id");
    $stmt->execute(["template_id" => $this->template_id]);
    $stmt->closeCursor();

    return $this->template_id;
  }

  public function getDetails() {

    global $rootDir;

    $h1 = "Template Prompt ID T" . $this->template_id;
    $btns = "
    <span class='btn btn-info' hx-get='{$rootDir}/api_gpt?class=template_prompt&editUi={$this->template_id}' hx-target='#card-id-{$this->template_id}' hx-swap='outerHTML'>Edit</span>
    <span class='btn btn-danger' hx-target='this' hx-swap='outerHTML' hx-post='{$rootDir}/api_gpt?class=template_prompt&askDel={$this->template_id}'>Delete?</span>";

    $ki = "";
    if (!empty($this->ki_vorgaben)) {
      $ki = "KI-Vorgaben: " . htmlspecialchars(str_replace("\n", "; ", $this->getKiVorgabenText()), ENT_QUOTES) . "<br>";
    }

    $a = "<div class='card mb-3' id='card-id-{$this->template_id}'>
    <h3 class='card-header'>$h1</h3>
    <div class='card-body'>
      Prompt: {$this->prompt}<br>
      Favorite: {$this->favorite}<br>
      $ki
      Last time used: " . date("Y-m-d H:i:s", $this->last_time_used) . "<br>
      $btns
    </div>
    </div>";
    return $a;
  }

  public function getSimple() {
    // $h1 = "Template Prompt " . $this->template_id;

    $class = 'info';
    $lastTime = '';
    if (intval($this->last_time_used) > 10) {
      $class = 'secondary';
      $lastTime = "; Last " . date("H:i:s", $this->last_time_used);
    }
    $ki = (!empty($this->ki_vorgaben) ? " <span class='badge bg-warning' title='" . htmlspecialchars($this->getKiVorgabenText(), ENT_QUOTES) . "'>KI-Vorgabe</span>" : "");

    $a = "<div class='template_prompt' id='template_prompt-{$this->template_id}'>
    <p><a href='/admin_gpt/?next_prompt_template={$this->template_id}&prompt={$this->prompt}' class='btn btn-success'>Fav {$this->favorite}</a>{$this->prompt} <span class='badge bg-$class badge-pointer' onclick='selectTemplate(this, \"{$this->prompt}\", \"{$this->template_id}\")'>Fav {$this->favorite}, ID T{$this->template_id}$lastTime</span>$ki</p>
    </div>";
    return $a;
  }

  /**
   * @param bool $forEditing true: alle Prompts nach Reihenfolge; false: nur favorite > 0, unbenutzte zuerst
   * @return template_prompt[]
   */
  static public function getAllPrompts($forEditing, $f_workshop_template_id, $f_workshops_id) {
    $db = pdoDb::getConnection();

    $owner = "f_workshop_template_id = :f_workshop_template_id AND f_workshops_id = :f_workshops_id";
    if ($forEditing) {
      $stmt = $db->prepare("SELECT template_id FROM template_prompt WHERE is_del = 0 AND $owner ORDER BY favorite ASC, template_id ASC");
    } else {
      $stmt = $db->prepare("SELECT template_id FROM template_prompt WHERE is_del = 0 AND favorite > 0 AND $owner ORDER BY last_time_used ASC, favorite ASC");
    }

    $stmt->execute([
      "f_workshop_template_id" => intval($f_workshop_template_id),
      "f_workshops_id" => intval($f_workshops_id),
    ]);
    $dataArray = $stmt->fetchAll();
    $stmt->closeCursor();

    $item_array = [];
    foreach ($dataArray as $item) {
      try {
        $item_array[] = new self($item['template_id']);
      } catch (\Exception $e) {
        var_dump($e);
      }
    }
    return $item_array;
  }

  /**
   * Soft-Delete aller Prompts eines Besitzers (z. B. vor dem Ersetzen per JSON-Upload).
   */
  public static function deleteAllOf($f_workshop_template_id, $f_workshops_id) {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE template_prompt SET is_del = 1
      WHERE f_workshop_template_id = :f_workshop_template_id AND f_workshops_id = :f_workshops_id");
    $stmt->execute([
      "f_workshop_template_id" => intval($f_workshop_template_id),
      "f_workshops_id" => intval($f_workshops_id),
    ]);
    $stmt->closeCursor();
  }

  public static function countOf($f_workshop_template_id, $f_workshops_id) {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("SELECT count(*) AS n FROM template_prompt
      WHERE is_del = 0 AND f_workshop_template_id = :f_workshop_template_id AND f_workshops_id = :f_workshops_id");
    $stmt->execute([
      "f_workshop_template_id" => intval($f_workshop_template_id),
      "f_workshops_id" => intval($f_workshops_id),
    ]);
    $data = $stmt->fetch();
    $stmt->closeCursor();
    return intval($data["n"]);
  }

  /**
   * Prompt-Liste zum Bearbeiten (Template oder Workshop): Neu-Formular plus alle Prompts.
   */
  public static function getEditList($f_workshop_template_id, $f_workshops_id) {
    $a = self::getEditInterface(null, $f_workshop_template_id, $f_workshops_id);
    foreach (self::getAllPrompts(true, $f_workshop_template_id, $f_workshops_id) as $item) {
      $a .= $item->getDetails();
    }
    return $a;
  }

  public static function handleApi() {
    global $rootDir;

    if (isset($_GET["editUi"])) {
      $theItem = new self($_GET["editUi"]);
      echo self::getEditInterface($theItem);
      exit();
    }
    if (isset($_GET["askDel"])) {
      $class = "template_prompt";
      echo "<span class='btn btn-warning' hx-swap='outerHTML' hx-post='{$rootDir}/api_gpt?class={$class}&doDel={$_GET["askDel"]}' hx-target='#card-id-{$_GET["askDel"]}'>Really Delete!</span>";
      exit();
    }
    if (isset($_GET["doDel"])) {
      $theItem = new self($_GET["doDel"]);
      $theItem->deleteFromDb();
      echo "<div class='alert alert-success'>Entry has been deleted.</div>";
      exit();
    }

    if (isset($_GET["saveGptJson"])) {

      $theItem = new self($_GET["saveGptJson"]);
      $theItem->chatgpt_json = json_encode($_POST["gpt"]);

      $theItem->saveToDb();
      exit();
    }

    if (isset($_GET["save"])) {
      $save_id = $_GET["save"];

      if ($save_id == 0) {
        $save_id = self::insertToDb($_POST["f_workshop_template_id"] ?? 0, $_POST["f_workshops_id"] ?? 0);
      }

      $theItem = new self($save_id);
      $theItem->updateFromPost();
      $theItem->saveToDb();

      echo $theItem->getDetails();
      exit();
    }
    if (isset($_GET["updateLastTimeUsed"])) {
      $theItem = new self($_GET["updateLastTimeUsed"]);
      echo $theItem->updateLastTimeUsed();
      exit();
    }
    if (isset($_GET["details"])) {
      $theItem = new self($_GET["details"]);
      echo $theItem->getDetails();
      exit();
    }

  }
}
?>
