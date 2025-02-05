<?php
namespace gpt2024;

use \app_ed_tech\pdoDb;
use \app_ed_tech\edTech;

class template_prompt {

  public $template_id;
  public $prompt;
  public $favorite;
  public $last_time_used;
  public $chatgpt_json;
  public $html_code;

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
      $this->prompt = json_decode($data['prompt']);
      $this->favorite = $data['favorite'];
      $this->chatgpt_json = $data['chatgpt_json'];
      $this->html_code = $data['html_code'];
      $this->last_time_used = $data['last_time_used'];
    } else {
      throw new \Exception("template_prompt ($_template_id) not found", 404001);
    }
  }

  public function saveToDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE template_prompt SET
      prompt = :prompt,
      chatgpt_json = :chatgpt_json,
      html_code = :html_code,
      favorite = :favorite
    WHERE template_id = :template_id");
    
    $stmt->execute([
      'prompt' => json_encode(gpt_functions::secureCharForMysql($this->prompt)),
      'favorite' => gpt_functions::secureCharForMysql($this->favorite),
      'chatgpt_json' => ($this->chatgpt_json),
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
  
  public static function resetLastTimeUsed() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE template_prompt SET
      last_time_used = 0");
    
    $stmt->execute([
    ]);
    
    $stmt->closeCursor();
  }

  public function updateFromPost() {
    $this->prompt = $_POST["prompt"];
    $this->favorite = $_POST["favorite"];
    $this->html_code = $_POST["html_code"];
  }

  /**
   * @return int NewId
   */
  public static function insertToDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("INSERT INTO template_prompt 
    (prompt, favorite) VALUES 
    (:prompt, :favorite)");
    $stmt->execute([
      "prompt" => json_encode(gpt_functions::secureCharForMysql($_POST["prompt"])),
      "favorite" => gpt_functions::secureCharForMysql($_POST["favorite"]),
    ]);
    $new_id = $db->lastInsertId();
    $stmt->closeCursor();
    return $new_id;
  }


  public static function getEditInterface($thePrompt = null) {

    admin_gpt::checkLogin();
global $py_api;

    $template_id = ($thePrompt != null ? $thePrompt->template_id : 0);
    global $rootDir;

    $a = "
    <div class='card mb-3' id='card-id-$template_id'>
    <h3 class='card-header'>Edit Template Prompt ID T$template_id</h3>
    <div class='card-body'>
    <form enctype='multipart/form-data' method='post' hx-post='{$rootDir}/api_gpt?class=template_prompt&save=" . $template_id . "' hx-target='#card-id-" . $template_id . "' hx-swap='outerHTML'>
    Prompt <textarea name='prompt'  style='width: 100%'>" . ($thePrompt != null ? $thePrompt->prompt : "") . "</textarea><br>
    Favorite <input name='favorite' value='" . ($thePrompt != null ? $thePrompt->favorite : "0") . "'><br>
    HTML <textarea name='html_code' style='width: 100%; min-height: 10rem'>" . ($thePrompt != null ? $thePrompt->html_code : "") . "</textarea><br>
    <input class='btn btn-success' value='Save' type='submit'>
    <span id='indicator_$template_id' class='htmx-indicator'>Saving... <i class='fas fa-spinner fa-pulse'></i> </span>";
    if ($thePrompt != null) {
      $a .= "
<br>
      GPT <span class='btn btn-info' onclick='loadGptAnswer( \"$py_api\", \"{$thePrompt->prompt}\", 0, $template_id)' >(new answer)</span>:
      <div id='gpt'></div>
      <div id='gpt-graph'></div>

      ";
      if (strlen($thePrompt->chatgpt_json) > 5 ) {
        $a .= "<script>
        processGptData({$thePrompt->chatgpt_json}, 0, " . $thePrompt->chatgpt_json . ");
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

    $a = "<div class='card mb-3' id='card-id-{$this->template_id}'>
    <h3 class='card-header'>$h1</h3>
    <div class='card-body'>
      Prompt: {$this->prompt}<br>
      Favorite: {$this->favorite}<br>
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

    $a = "<div class='template_prompt' id='template_prompt-{$this->template_id}'>
    <p><a href='/admin_gpt/?next_prompt_template={$this->template_id}&prompt={$this->prompt}' class='btn btn-success'>Fav {$this->favorite}</a>{$this->prompt} <span class='badge bg-$class badge-pointer' onclick='selectTemplate(this, \"{$this->prompt}\", \"{$this->template_id}\")'>Fav {$this->favorite}, ID T{$this->template_id}$lastTime</span></p>
    </div>";
    return $a;
  }

  /**
   * @return template_prompt[]
   */
  static public function getAllPrompts($forEditing = false) { 
    $db = pdoDb::getConnection();

    if ($forEditing) {
      $stmt = $db->prepare("SELECT template_id FROM template_prompt WHERE is_del = 0 ORDER BY favorite ASC");
    } else {
      $stmt = $db->prepare("SELECT template_id FROM template_prompt WHERE is_del = 0 AND favorite > 0 ORDER BY last_time_used ASC, favorite ASC");
    }

    $stmt->execute();
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
    
    // if (isset($_GET["create"])) {
    //   $save_id = self::insertToDb();
    //   $theItem = new self($save_id);
    //   $theItem->prompt = $_POST["prompt"];
    //   $theItem->favorite = $_POST["favorite"];
    //   $theItem->saveToDb();

    //   echo $theItem->getEditInterface();
    //   exit();
    // }

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

    if (isset($_GET["list"])) {
      $allItems = self::getAllPrompts(true);
      $list = "<div id='prompt-list'>";
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
