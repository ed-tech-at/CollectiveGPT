<?php
namespace gpt2024;

use \app_ed_tech\pdoDb;
use \app_ed_tech\edTech;

/**
 * Wiederverwendbare Vorlage fuer Workshops (benannte Prompt-Liste).
 */
class workshop_templates {

  public $workshop_template_id;
  public $name;
  public $lang;
  public $description;
  public $created;
  public $updated;

  public function __construct($_workshop_template_id) {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare(
      'SELECT * FROM workshop_templates WHERE workshop_template_id = :id AND is_del = 0'
    );
    $stmt->execute(['id' => $_workshop_template_id]);

    $data = $stmt->fetch();
    $stmt->closeCursor();

    if (!empty($data)) {
      $this->workshop_template_id = $data['workshop_template_id'];
      $this->name = $data['name'];
      $this->lang = $data['lang'];
      $this->description = $data['description'];
      $this->created = $data['created'];
      $this->updated = $data['updated'];
    } else {
      throw new \Exception("workshop_templates ($_workshop_template_id) not found", 404001);
    }
  }

  public function saveToDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE workshop_templates SET
      name = :name,
      lang = :lang,
      description = :description,
      updated = :updated
    WHERE workshop_template_id = :id");

    $stmt->execute([
      'name' => gpt_functions::secureCharForMysql($this->name),
      'lang' => ($this->lang == "en" ? "en" : "de"),
      'description' => gpt_functions::secureCharForMysql($this->description),
      'updated' => time(),
      'id' => intval($this->workshop_template_id),
    ]);

    $stmt->closeCursor();
  }

  public function updateFromPost() {
    $this->name = $_POST["name"];
    $this->lang = $_POST["lang"];
    $this->description = $_POST["description"];
  }

  /**
   * @return int NewId
   */
  public static function insertToDb($name = "", $lang = "de", $description = "") {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("INSERT INTO workshop_templates
    (name, lang, description, created, updated) VALUES
    (:name, :lang, :description, :created, :created)");
    $stmt->execute([
      "name" => gpt_functions::secureCharForMysql($name),
      "lang" => ($lang == "en" ? "en" : "de"),
      "description" => gpt_functions::secureCharForMysql($description),
      "created" => time(),
    ]);
    $new_id = $db->lastInsertId();
    $stmt->closeCursor();
    return $new_id;
  }

  public function deleteFromDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE workshop_templates SET is_del = 1 WHERE workshop_template_id = :id");
    $stmt->execute(["id" => $this->workshop_template_id]);
    $stmt->closeCursor();

    template_prompt::deleteAllOf($this->workshop_template_id, 0);

    return $this->workshop_template_id;
  }

  /**
   * @return template_prompt[]
   */
  public function getPrompts() {
    return template_prompt::getAllPrompts(true, $this->workshop_template_id, 0);
  }

  public function getJson() {
    return workshop_json::build("workshop_template", $this->workshop_template_id, $this->name, $this->lang, $this->description, $this->getPrompts());
  }

  /**
   * Leitet einen konkreten Workshop ab: neue Workshop-Zeile, Prompts werden kopiert.
   * @return int workshops_id
   */
  public function deriveWorkshop($workshop_name, $start_time) {
    $workshops_id = workshops::insertToDb($workshop_name, $start_time, $this->lang, $this->workshop_template_id);
    foreach ($this->getPrompts() as $p) {
      $p->copyTo(0, $workshops_id);
    }
    return $workshops_id;
  }

  public static function getEditInterface($theItem = null) {
    global $rootDir;
    $id = ($theItem != null ? $theItem->workshop_template_id : 0);
    $lang = ($theItem != null ? $theItem->lang : "de");

    $a = "
    <div class='card mb-3' id='wt-card-id-$id'>
    <h3 class='card-header'>" . ($id ? "Edit Workshop-Template WT$id" : "Neues Workshop-Template") . "</h3>
    <div class='card-body'>
    <form method='post' hx-post='{$rootDir}/api_gpt?class=workshop_templates&save=$id' hx-target='#wt-card-id-$id' hx-swap='outerHTML'>
    Name <input name='name' style='width: 100%' value='" . ($theItem != null ? $theItem->name : "") . "'><br>
    Sprache <select name='lang'>
      <option value='de'" . ($lang == "de" ? " selected" : "") . ">Deutsch</option>
      <option value='en'" . ($lang == "en" ? " selected" : "") . ">English</option>
    </select><br>
    Beschreibung <textarea name='description' style='width: 100%'>" . ($theItem != null ? $theItem->description : "") . "</textarea><br>
    <input class='btn btn-success' value='Save' type='submit'>";
    if ($theItem != null) {
      $a .= "
      <span class='btn btn-secondary' hx-get='{$rootDir}/api_gpt?class=workshop_templates&details=$id' hx-target='#wt-card-id-$id' hx-swap='outerHTML'>Cancel</span>";
    }
    $a .= "</form>";
    if ($theItem == null) {
      $a .= "<hr>Oder Template aus JSON anlegen: " . workshop_json::getUploadForm("class=workshop_templates&importJson=1", "Als neues Template hochladen");
    }
    $a .= "
    </div>
    </div>";
    return $a;
  }

  public function getDetails() {
    global $rootDir;
    $id = $this->workshop_template_id;
    $count = template_prompt::countOf($id, 0);

    return "<div class='card mb-3' id='wt-card-id-$id'>
    <h3 class='card-header'>Workshop-Template WT$id: {$this->name}</h3>
    <div class='card-body'>
      Sprache: {$this->lang} · Prompts: $count · geändert " . date("Y-m-d H:i", $this->updated) . "<br>
      " . ($this->description != "" ? nl2br($this->description) . "<br>" : "") . "
      <a class='btn btn-primary' href='{$rootDir}/admin_gpt/?template=1&wt=$id'>Prompts bearbeiten</a>
      <a class='btn btn-outline-dark' href='{$rootDir}/api_gpt?class=workshop_templates&exportJson=$id'>JSON herunterladen</a>
      <span class='btn btn-info' hx-get='{$rootDir}/api_gpt?class=workshop_templates&editUi=$id' hx-target='#wt-card-id-$id' hx-swap='outerHTML'>Edit</span>
      <span class='btn btn-danger' hx-target='this' hx-swap='outerHTML' hx-post='{$rootDir}/api_gpt?class=workshop_templates&askDel=$id'>Delete?</span>
      <hr>
      <form method='post' action='{$rootDir}/api_gpt?class=workshop_templates&derive=$id' class='d-inline-block'>
        Workshop ableiten: <input name='workshop_name' placeholder='Workshop-Name' value='{$this->name} " . date("Y-m-d") . "'>
        Start <input name='start_time' value='" . date("Y-m-d H:i:s") . "'>
        <button class='btn btn-warning btn-sm'>Workshop ableiten</button>
      </form>
    </div>
    </div>";
  }

  /**
   * @return workshop_templates[]
   */
  public static function getAll() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("SELECT workshop_template_id FROM workshop_templates WHERE is_del = 0 ORDER BY name ASC");
    $stmt->execute();
    $dataArray = $stmt->fetchAll();
    $stmt->closeCursor();

    $item_array = [];
    foreach ($dataArray as $item) {
      $item_array[] = new self($item['workshop_template_id']);
    }
    return $item_array;
  }

  public static function handleApi() {
    global $rootDir;

    if (isset($_GET["editUi"])) {
      echo self::getEditInterface(new self($_GET["editUi"]));
      exit();
    }
    if (isset($_GET["details"])) {
      echo (new self($_GET["details"]))->getDetails();
      exit();
    }
    if (isset($_GET["askDel"])) {
      $id = intval($_GET["askDel"]);
      echo "<span class='btn btn-warning' hx-swap='outerHTML' hx-post='{$rootDir}/api_gpt?class=workshop_templates&doDel=$id' hx-target='#wt-card-id-$id'>Really Delete!</span>";
      exit();
    }
    if (isset($_GET["doDel"])) {
      (new self($_GET["doDel"]))->deleteFromDb();
      echo "<div class='alert alert-success'>Workshop-Template wurde gelöscht.</div>";
      exit();
    }
    if (isset($_GET["save"])) {
      $save_id = intval($_GET["save"]);
      if ($save_id == 0) {
        $save_id = self::insertToDb();
      }
      $theItem = new self($save_id);
      $theItem->updateFromPost();
      $theItem->saveToDb();
      echo (new self($save_id))->getDetails();
      exit();
    }
    if (isset($_GET["exportJson"])) {
      $theItem = new self($_GET["exportJson"]);
      workshop_json::sendDownload($theItem->getJson(), "workshop-template-WT{$theItem->workshop_template_id}-" . html_entity_decode($theItem->name, ENT_QUOTES));
    }
    if (isset($_GET["importJson"])) {
      try {
        $data = workshop_json::readUpload();
        $new_id = self::insertToDb($data["name"] ?? "Import " . date("Y-m-d H:i"), workshop_json::cleanLang($data), $data["beschreibung"] ?? "");
        $n = workshop_json::importPrompts($data, $new_id, 0);
        gpt_functions::addSessionStatusMeldung("success", "Workshop-Template WT$new_id mit $n Prompts angelegt.");
      } catch (\Exception $e) {
        gpt_functions::addSessionStatusMeldung("danger", $e->getMessage());
      }
      edTech::throw303SeeOther("$rootDir/admin_gpt/?template=1");
    }
    if (isset($_GET["derive"])) {
      $theItem = new self($_GET["derive"]);
      $start = strtotime($_POST["start_time"] ?? "") ?: time();
      $name = trim($_POST["workshop_name"] ?? "") ?: $theItem->name;
      $workshops_id = $theItem->deriveWorkshop($name, $start);
      gpt_functions::addSessionStatusMeldung("success", "Workshop W$workshops_id aus Template WT{$theItem->workshop_template_id} abgeleitet.");
      edTech::throw303SeeOther("$rootDir/admin_gpt/?workshops=1");
    }
  }
}
?>
