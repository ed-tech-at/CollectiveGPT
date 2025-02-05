<?php
namespace gpt2024;
use \app_ed_tech\pdoDb;
use \app_ed_tech\edTech;

class send_prompts {

  public $prompt_id;
  public $prompt;
  public $send_time;
  public $chatgpt_json;
  public $html_code;
  public $selected_word;
  public $f_template_id;
  public $f_workshop_id;

  public function __construct($_prompt_id)
  {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare(
      'SELECT * FROM send_prompts WHERE prompt_id = :prompt_id'
    );
    $stmt->execute(['prompt_id' => $_prompt_id]);

    $data = $stmt->fetch();
    $stmt->closeCursor();

    if (!empty($data)) {
      $this->prompt_id = $data['prompt_id'];
      $this->prompt = $data['prompt'];
      $this->send_time = $data['send_time'];
      $this->chatgpt_json = $data['chatgpt_json'];
      $this->html_code = $data['html_code'];
      $this->selected_word = $data['selected_word'];
      $this->f_template_id = $data['f_template_id'];
      $this->f_workshop_id = $data['f_workshop_id'];
    } else {
      throw new \Exception("send_prompts ($_prompt_id) not found", 404001);
    }
  }

  public function saveToDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE send_prompts SET
      prompt = :prompt,
      send_time = :send_time,
      chatgpt_json = :chatgpt_json,
      html_code = :html_code,
      selected_word = :selected_word,
      f_workshop_id = :f_workshop_id,
      f_template_id = :f_template_id
    WHERE prompt_id = :prompt_id");
    
    $stmt->execute([
      'prompt' => gpt_functions::secureCharForMysql($this->prompt),
      'send_time' => gpt_functions::secureCharForMysql($this->send_time),
      'chatgpt_json' => ($this->chatgpt_json),
      'html_code' => ($this->html_code),
      'selected_word' => gpt_functions::secureCharForMysql($this->selected_word),
      'f_template_id' => gpt_functions::secureCharForMysql($this->f_template_id),
      'f_workshop_id' => gpt_functions::secureCharForMysql($this->f_workshop_id),
      'prompt_id' => gpt_functions::secureCharForMysql($this->prompt_id),
    ]);
    
    $stmt->closeCursor();
  }

  public function updateFromPost() {
    $this->prompt = $_POST["prompt"];
    $this->send_time = $_POST["send_time"];
    $this->chatgpt_json = $_POST["chatgpt_json"];
    $this->selected_word = $_POST["selected_word"];
    $this->f_template_id = $_POST["f_template_id"];
  }

  public function checkTemplateGpt() {
    if ($this->f_template_id == 0) {
      return;
    }

    $template = new template_prompt($this->f_template_id);
    if ($template->prompt != $this->prompt) {
      return;
    }
    $this->chatgpt_json = $template->chatgpt_json;
    $this->html_code = $template->html_code;
    $template->updateLastTimeUsed();

  }

  /**
   * @return int NewId
   */
  public static function insertToDb() {
    $db = pdoDb::getConnection();
    $optionen = gpt_functions::getOptionen();

    $stmt = $db->prepare("INSERT INTO send_prompts 
    (send_time, f_workshop_id) VALUES 
    (:send_time, :f_workshop_id)");
    $stmt->execute([
      "send_time" => gpt_functions::secureCharForMysql(time()),
      "f_workshop_id" => gpt_functions::secureCharForMysql($optionen->active_workshop)
    ]);
    $new_id = $db->lastInsertId();
    $stmt->closeCursor();
    return $new_id;
  }

  
  public function deleteFromDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE send_prompts SET is_del = 1 WHERE prompt_id = :prompt_id");
    $stmt->execute(["prompt_id" => $this->prompt_id]);
    $stmt->closeCursor();

    return $this->prompt_id;
  }


  public function getSimple() {
    $a = "<div class='send_prompt' id='send_prompt-{$this->prompt_id}'>
    <p>Prompt: {$this->prompt}</p>
    </div>";
    return $a;
  }

  /**
   * @return send_prompts[]
   */
  static public function getAllPrompts() { 
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("SELECT prompt_id FROM send_prompts WHERE is_del = 0 ORDER BY send_time DESC");
    $stmt->execute();
    $dataArray = $stmt->fetchAll();
    $stmt->closeCursor();

    $item_array = [];
    foreach ($dataArray as $item) {
      try {
        $item_array[] = new self($item['prompt_id']);
      } catch (\Exception $e) {
        var_dump($e);
      }
    }
    return $item_array;
  }

  /**
   * @return send_prompts
   */
  static public function getLatestPrompt() { 
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("SELECT prompt_id FROM send_prompts ORDER BY prompt_id DESC LIMIT 1");
    $stmt->execute();
    $data = $stmt->fetch();
    return new self($data['prompt_id']);
  }


  public function getSetPromptHtml() {

    admin_gpt::checkLogin();

    global $py_api;
    $a = "
    <div id='prompt' data-prompt_id='{$this->prompt_id}'>
    <h2>Prompt ID {$this->prompt_id} : " . date("Y-m-d H:i:s", $this->send_time) . "</h2>

    <form hx-post='/api_gpt?setPrompt=1'  hx-target='#prompt' >
    <div id='inputs'>
        T<input name='template' id='input-template' style='width: 40px' value='{$this->f_template_id}'>
        : <input name='prompt'  id='input-prompt' style='width: 350px'  value='{$this->prompt}'>
        <button class='btn btn-success' >Prompt senden</button>
        <span onclick='$(\"#input-template\").val(\"0\");$(\"#input-prompt\").val(\"\");' class='btn btn-secondary' >X</span>
        </div>
        </form>

        <form hx-post='/api_gpt?setNextWord=1'  hx-target='#prompt' >

        <input type='hidden' name='old_prompt_id' value='{$this->prompt_id}' />
        nächstes Wort: <input name='nextword'  id='input-nextword' style='width: 150px' >
        <button class='btn btn-dark' >anhängen und prompt senden</button>
        
                </form>


        
<div class='flex' id='answers'>
<div id='users'>";

if (strlen($this->html_code) > 1) {
  $a .= "<h3>HTML</h3>" . $this->html_code;
}

$a .= "
<h3>Users (<span id='numberOfAnswers'></span>)</h3>
<div id='users-answer-list'></div>
" . answers::generateAnswersListScript($this->prompt_id) . "
<span class='btn btn-info' onclick='generateChart(1)'>User Graph</span>
<span class='btn btn-info' onclick='generateChart(2)'>GPT Graph</span>
<span class='btn btn-info' onclick='generateChart(3)'>Combined Graph</span>
<br>
<span class='btn btn-warning btnSendGraphToUsers' onclick='sendGraphToUsers(this)'>Send Graph to Users</span>
</div>

<div id='gpt'>
<h3>GPT</h3>
";


if (strlen($this->chatgpt_json) > 5) {
  $a .= "
  <script>
  processGptData({$this->chatgpt_json}, {$this->prompt_id}, 0);
  </script>
  ";
} else if (strlen($this->prompt) > 1 && strlen($this->html_code) < 2) {
  $a .= "
  <script>
  loadGptAnswer('{$py_api}', '{$this->prompt}', {$this->prompt_id}, 0);
  </script>
  ";
}


$a .= "
</div>
</div>
        </div>


";


return $a;

  }


  public static function setPrompt($prompt, $template) {
    admin_gpt::checkLogin();
    
    $prompt_id = send_prompts::insertToDb();
    $send_promt = new send_prompts($prompt_id);

    $optionen = gpt_functions::getOptionen();
    $optionen->p = $prompt;
    $send_promt->prompt = $prompt;
    // $optionen->t = $_POST["template"] ?? 0;
    $send_promt->f_template_id = $template ?? 0;
    // $optionen->time = time();
    $send_promt->checkTemplateGpt();
    $send_promt->saveToDb();


    $optionen->prompt_id = $prompt_id;
    $optionen->h = $send_promt->html_code;
    $optionen->c = 0;
    gpt_functions::setOptionen($optionen);

    // var_dump($send_promt);


    gpt_functions::sendWsPost("gpt2024p", ["a"=>"p"]);
    $send_promt = new send_prompts($prompt_id);

    return $send_promt->getSetPromptHtml();
  }


  public static function handleApi() {
    global $rootDir;

    
    if (isset($_GET["saveGptJson"])) {

      $theItem = new self($_GET["saveGptJson"]);
      $theItem->chatgpt_json = json_encode($_POST["gpt"]);
      
      $theItem->saveToDb();
      exit();
    }

    if (isset($_GET["list"])) {
      $allItems = self::getAllPrompts();
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
