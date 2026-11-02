<?php
namespace gpt2024;

use \app_ed_tech\pdoDb;
use \app_ed_tech\edTech;

/**
 * Konkreter Workshop. Hat eigene Prompts (template_prompt.f_workshops_id), abgeleitet von einem
 * Workshop-Template oder aus einer hochgeladenen JSON-Datei.
 */
class workshops {

  public $workshops_id;
  public $f_workshop_template_id;
  public $start_time;
  public $workshop_name;
  public $lang;
  public $created;
  public $json_uploaded;

  public function __construct($_workshops_id) {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare(
      'SELECT * FROM workshops WHERE workshops_id = :workshops_id'
    );
    $stmt->execute(['workshops_id' => $_workshops_id]);

    $data = $stmt->fetch();
    $stmt->closeCursor();

    if (!empty($data)) {
      $this->workshops_id = $data['workshops_id'];
      $this->f_workshop_template_id = $data['f_workshop_template_id'];
      $this->start_time = $data['start_time'];
      $this->workshop_name = $data['workshop_name'];
      $this->lang = $data['lang'];
      $this->created = $data['created'];
      $this->json_uploaded = $data['json_uploaded'];
    } else {
      throw new \Exception("Workshops ($_workshops_id) not found", 404001);
    }
  }

  public function saveToDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE workshops SET
      start_time = :start_time,
      workshop_name = :workshop_name,
      lang = :lang,
      json_uploaded = :json_uploaded
    WHERE workshops_id = :workshops_id");

    $stmt->execute([
      'start_time' => intval($this->start_time),
      'workshop_name' => gpt_functions::secureCharForMysql($this->workshop_name),
      'lang' => ($this->lang == "en" ? "en" : "de"),
      'json_uploaded' => intval($this->json_uploaded),
      'workshops_id' => intval($this->workshops_id),
    ]);

    $stmt->closeCursor();
  }

  public function updateFromPost() {
    $this->start_time = strtotime($_POST["start_time"]);
    $this->workshop_name = $_POST["workshop_name"];
    $this->lang = $_POST["lang"];
  }

  /**
   * @return int NewId
   */
  public static function insertToDb($workshop_name = "", $start_time = null, $lang = "de", $f_workshop_template_id = 0) {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("INSERT INTO workshops
    (start_time, workshop_name, lang, f_workshop_template_id, created) VALUES
    (:start_time, :workshop_name, :lang, :f_workshop_template_id, :created)");
    $stmt->execute([
      "start_time" => intval($start_time ?? time()),
      "workshop_name" => gpt_functions::secureCharForMysql($workshop_name),
      "lang" => ($lang == "en" ? "en" : "de"),
      "f_workshop_template_id" => intval($f_workshop_template_id),
      "created" => time(),
    ]);
    $new_id = $db->lastInsertId();
    $stmt->closeCursor();
    return $new_id;
  }

  /**
   * @return workshops theWorkshop
   */
  public static function getLastWorkshop() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare(
      'SELECT workshops_id FROM workshops ORDER BY start_time DESC LIMIT 1'
    );
    $stmt->execute();

    $data = $stmt->fetch();
    $stmt->closeCursor();

    if (!empty($data)) {
      $lastWorkshop = new self($data['workshops_id']);
      return $lastWorkshop;
    } else {
      throw new \Exception("No workshops found", 404002);
    }
  }

  /**
   * @return template_prompt[]
   */
  public function getPrompts() {
    return template_prompt::getAllPrompts(true, 0, $this->workshops_id);
  }

  public function getJson() {
    return workshop_json::build("workshop", $this->workshops_id, $this->workshop_name, $this->lang, "", $this->getPrompts());
  }

  /**
   * Ersetzt alle Prompts des Workshops durch die aus dem JSON (alte werden soft-geloescht,
   * damit send_prompts-Verweise gueltig bleiben).
   * @return int Anzahl
   */
  public function replaceFromJson($data) {
    template_prompt::deleteAllOf(0, $this->workshops_id);
    $n = workshop_json::importPrompts($data, 0, $this->workshops_id);
    // saveToDb escaped erneut, daher den Namen aus der DB vorher zuruecksetzen
    $this->workshop_name = html_entity_decode($this->workshop_name, ENT_QUOTES);
    if (isset($data["name"]) && trim($data["name"]) != "") {
      $this->workshop_name = trim($data["name"]);
    }
    $this->lang = workshop_json::cleanLang($data, $this->lang);
    $this->json_uploaded = time();
    $this->saveToDb();
    return $n;
  }

  /**
   * Setzt den Workshop aktiv; die Sprache der Teilnehmenden-Ansicht folgt dem Workshop.
   */
  public static function activate($workshops_id) {
    $optionen = gpt_functions::getOptionen();
    $optionen->active_workshop = $workshops_id;

    if ($workshops_id != "-1") {
      $theWorkshop = new self($workshops_id);
      $optionen->lang = $theWorkshop->lang;
      template_prompt::resetLastTimeUsed($workshops_id);
    }
    gpt_functions::setOptionen($optionen);

    send_prompts::setPrompt("", 0);

    if ($workshops_id == "-1") {
      gpt_functions::sendWsPost("gpt2024p", ["a"=>"stop"]);
    }
  }

  public static function getEditInterface($theWorkshop = null) {
    $workshop_id = ($theWorkshop != null ? $theWorkshop->workshops_id : 0);
    $lang = ($theWorkshop != null ? $theWorkshop->lang : "de");
    global $rootDir;

    $a = "
    <div class='card mb-3' id='card-id-$workshop_id'>
    <h3 class='card-header'>Edit Workshop ID E$workshop_id</h3>
    <div class='card-body'>
    <form enctype='multipart/form-data' method='post' hx-post='{$rootDir}/api_gpt?class=workshops&save=" . $workshop_id . "' hx-target='#card-id-" . $workshop_id . "' hx-swap='outerHTML'>
    Workshop Name <input name='workshop_name' value='" . ($theWorkshop != null ? $theWorkshop->workshop_name : "") . "'><br>
    Start Time: <input name='start_time' value='" . date("Y-m-d H:i:s", ($theWorkshop != null ? $theWorkshop->start_time : time())) . "'><br>
    Sprache <select name='lang'>
      <option value='de'" . ($lang == "de" ? " selected" : "") . ">Deutsch</option>
      <option value='en'" . ($lang == "en" ? " selected" : "") . ">English</option>
    </select><br>
    <input class='btn btn-success' value='Save' type='submit'>
    <span id='indicator_$workshop_id' class='htmx-indicator'>Saving... <i class='fas fa-spinner fa-pulse'></i> </span>";
    if ($theWorkshop != null) {
      $a .= "
      <span class='btn btn-secondary' hx-get='{$rootDir}/api_gpt?class=workshops&details=$workshop_id' >Cancel</span>
      <span class='btn btn-danger' hx-target='this' hx-swap='outerHTML' hx-post='{$rootDir}/api_gpt?class=workshops&askDel={$workshop_id}'>Delete?</span>
      ";
    } else {
      $a .= "
      <a class='btn btn-warning' href='{$rootDir}/admin_gpt/?workshops=1&active_workshop=-1' >No active Workshop</a>
      ";
    }
    $a .= "</form>";
    if ($theWorkshop == null) {
      $a .= "<hr>Leerer Workshop oben, oder ableiten unter <a href='{$rootDir}/admin_gpt/?template=1'>Template</a>, oder aus JSON: "
        . workshop_json::getUploadForm("class=workshops&importJson=0", "Als neuen Workshop hochladen");
    }
    $a .= "
    </div>
    </div>
    ";
    return $a;
  }

  public function deleteFromDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("DELETE FROM workshops WHERE workshops_id = :workshops_id");
    $stmt->execute(["workshops_id" => $this->workshops_id]);
    $stmt->closeCursor();

    template_prompt::deleteAllOf(0, $this->workshops_id);

    return $this->workshops_id;
  }

  public function getDetails() {

    global $rootDir;
    $optionen = gpt_functions::getOptionen();
    $id = $this->workshops_id;
    $count = template_prompt::countOf(0, $id);

    $h1 = "Workshop ID W" . $id;
    if ($optionen->active_workshop == $id) {
      $h1 .= " <span class='badge bg-success'>aktiv</span>";
    }
    $herkunft = ($this->f_workshop_template_id > 0 ? "abgeleitet von WT{$this->f_workshop_template_id}" : "ohne Template");
    if ($this->json_uploaded > 0) {
      $herkunft .= ", JSON hochgeladen " . date("Y-m-d H:i", $this->json_uploaded);
    }

    $btns = "
    <span class='btn btn-info' hx-get='{$rootDir}/api_gpt?class=workshops&editUi={$id}' hx-target='#card-id-{$id}' hx-swap='outerHTML'>Edit</span>
    <span class='btn btn-danger' hx-target='this' hx-swap='outerHTML' hx-post='{$rootDir}/api_gpt?class=workshops&askDel={$id}'>Delete?</span>
    <a class='btn btn-warning' href='{$rootDir}/admin_gpt/?workshops=1&active_workshop={$id}'>set Active</a>
    <a class='btn btn-primary' href='{$rootDir}/admin_gpt/?workshops=1&w={$id}'>Prompts bearbeiten</a>
    <a class='btn btn-outline-dark' href='{$rootDir}/api_gpt?class=workshops&exportJson={$id}'>JSON herunterladen</a>
    <br>JSON ersetzen: " . workshop_json::getUploadForm("class=workshops&importJson={$id}", "Prompts ersetzen", "Alle Prompts von W{$id} durch die Datei ersetzen?");

    $a = "<div class='card mb-3' id='card-id-{$id}'>
    <h3 class='card-header'>$h1</h3>
    <div class='card-body'>
      Workshop Name: {$this->workshop_name}<br>
      Start Time: " . date("Y-m-d H:i:s", $this->start_time) . " · Sprache: {$this->lang} · Prompts: $count ($herkunft)<br>
      $btns
    </div>
    </div>";
    return $a;
  }

  public function getSimple() {
    $class = 'info';
    if (intval($this->start_time) + 60 * 10 > time()) {
      $class = 'secondary';
    }

    $a = "<div class='workshop' id='workshop-{$this->workshops_id}'>
    <p>Workshop: {$this->workshop_name} <span class='btn btn-$class' onclick='selectWorkshop(this, \"{$this->workshop_name}\", \"{$this->workshops_id}\")'>ID E{$this->workshops_id}; Start " . date("Y-m-d H:i:s", $this->start_time) . "</p>
    </div>";
    return $a;
  }

  /**
   * @return Workshops[]
   */
  static public function getAllWorkshops() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("SELECT workshops_id FROM workshops ORDER BY start_time DESC");
    $stmt->execute();
    $dataArray = $stmt->fetchAll();
    $stmt->closeCursor();

    $item_array = [];
    foreach ($dataArray as $item) {
      try {
        $item_array[] = new self($item['workshops_id']);
      } catch (\Exception $e) {
        var_dump($e);
      }
    }
    return $item_array;
  }

  public static function handleApi() {
    global $rootDir;

    if (isset($_GET["editUi"])) {
      $theItem = new self($_GET["editUi"]);
      echo self::getEditInterface($theItem);
      exit();
    }
    if (isset($_GET["askDel"])) {
      $class = "workshops";
      echo "<span class='btn btn-warning' hx-swap='outerHTML' hx-post='{$rootDir}/api_gpt?class={$class}&doDel={$_GET["askDel"]}' hx-target='#card-id-{$_GET["askDel"]}'>Really Delete!</span>";
      exit();
    }
    if (isset($_GET["doDel"])) {
      $theItem = new self($_GET["doDel"]);
      $theItem->deleteFromDb();
      echo "<div class='alert alert-success'>Entry has been deleted.</div>";
      exit();
    }

    if (isset($_GET["save"])) {
      $save_id = $_GET["save"];

      if ($save_id == 0) {
        $save_id = self::insertToDb("", strtotime($_POST["start_time"]));
      }

      $theItem = new self($save_id);
      $theItem->updateFromPost();
      $theItem->saveToDb();

      echo $theItem->getDetails();
      exit();
    }

    if (isset($_GET["details"])) {
      $theItem = new self($_GET["details"]);
      echo $theItem->getDetails();
      exit();
    }

    if (isset($_GET["exportJson"])) {
      $theItem = new self($_GET["exportJson"]);
      workshop_json::sendDownload($theItem->getJson(), "workshop-W{$theItem->workshops_id}-" . html_entity_decode($theItem->workshop_name, ENT_QUOTES));
    }

    // importJson=0: neuer Workshop aus JSON; importJson=ID: Prompts dieses Workshops ersetzen
    if (isset($_GET["importJson"])) {
      try {
        $data = workshop_json::readUpload();
        $workshops_id = intval($_GET["importJson"]);
        if ($workshops_id == 0) {
          $template_id = (($data["quelle"]["typ"] ?? "") == "workshop_template" ? intval($data["quelle"]["id"] ?? 0) : 0);
          $workshops_id = self::insertToDb($data["name"] ?? "Import " . date("Y-m-d H:i"), time(), workshop_json::cleanLang($data), $template_id);
        }
        $theItem = new self($workshops_id);
        $n = $theItem->replaceFromJson($data);
        gpt_functions::addSessionStatusMeldung("success", "Workshop W$workshops_id: $n Prompts aus JSON übernommen.");
      } catch (\Exception $e) {
        gpt_functions::addSessionStatusMeldung("danger", $e->getMessage());
      }
      edTech::throw303SeeOther("$rootDir/admin_gpt/?workshops=1");
    }
  }

}
?>
