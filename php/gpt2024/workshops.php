<?php
namespace gpt2024;

use \app_ed_tech\pdoDb;
use \app_ed_tech\edTech;

class workshops {

  public $workshops_id;
  public $start_time;
  public $workshop_name;

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
      $this->start_time = $data['start_time'];
      $this->workshop_name = $data['workshop_name'];
    } else {
      throw new \Exception("Workshops ($_workshops_id) not found", 404001);
    }
  }

  public function saveToDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE workshops SET
      start_time = :start_time,
      workshop_name = :workshop_name
    WHERE workshops_id = :workshops_id");
    
    $stmt->execute([
      'start_time' => gpt_functions::secureCharForMysql($this->start_time),
      'workshop_name' => gpt_functions::secureCharForMysql($this->workshop_name),
      'workshops_id' => gpt_functions::secureCharForMysql($this->workshops_id),
    ]);
    
    $stmt->closeCursor();
  }

  public function updateFromPost() {
    $this->start_time = strtotime($_POST["start_time"]);
    $this->workshop_name = $_POST["workshop_name"];
  }

  /**
   * @return int NewId
   */
  public static function insertToDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("INSERT INTO workshops 
    (start_time, workshop_name) VALUES 
    (:start_time, :workshop_name)");
    $stmt->execute([
      "start_time" => gpt_functions::secureCharForMysql(strtotime($_POST["start_time"])),
      "workshop_name" => gpt_functions::secureCharForMysql(""),
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

  public static function getEditInterface($theWorkshop = null) {
    $workshop_id = ($theWorkshop != null ? $theWorkshop->workshops_id : 0);
    global $rootDir;

    $a = "
    <div class='card mb-3' id='card-id-$workshop_id'>
    <h3 class='card-header'>Edit Workshop ID E$workshop_id</h3>
    <div class='card-body'>
    <form enctype='multipart/form-data' method='post' hx-post='{$rootDir}/api_gpt?class=workshops&save=" . $workshop_id . "' hx-target='#card-id-" . $workshop_id . "' hx-swap='outerHTML'>
    Workshop Name <input name='workshop_name' value='" . ($theWorkshop != null ? $theWorkshop->workshop_name : "") . "'><br>
    Start Time: <input name='start_time' value='" . date("Y-m-d H:i:s", ($theWorkshop != null ? $theWorkshop->start_time : time())) . "'><br>
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
    $a .= "</form>
    
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

    return $this->workshops_id;
  }

  public function getDetails() {

    global $rootDir;

    $h1 = "Workshop ID W" . $this->workshops_id;
    $btns = "
    <span class='btn btn-info' hx-get='{$rootDir}/api_gpt?class=workshops&editUi={$this->workshops_id}' hx-target='#card-id-{$this->workshops_id}' hx-swap='outerHTML'>Edit</span>
    <span class='btn btn-danger' hx-target='this' hx-swap='outerHTML' hx-post='{$rootDir}/api_gpt?class=workshops&askDel={$this->workshops_id}'>Delete?</span>
    <a class='btn btn-warning' href='{$rootDir}/admin_gpt/?workshops=1&active_workshop={$this->workshops_id}'>set Active</a>
    ";

    $a = "<div class='card mb-3' id='card-id-{$this->workshops_id}'>
    <h3 class='card-header'>$h1</h3>
    <div class='card-body'>
      Workshop Name: {$this->workshop_name}<br>
      Start Time: " . date("Y-m-d H:i:s", $this->start_time) . "<br>
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
        $save_id = self::insertToDb();
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

    if (isset($_GET["list"])) {
      $allItems = self::getAllWorkshops();
      $list = "<div id='workshop-list'>";
      foreach ($allItems as $item) {
        $list .= $item->getSimple();
      }
      $list .= "</div>";
      echo $list;
      exit();
    }
  }

}
?>
