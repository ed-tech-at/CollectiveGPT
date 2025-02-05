<?php
namespace gpt2024;
use \app_ed_tech\pdoDb;
use \app_ed_tech\edTech;

class api_gpt {

  public static function doApi () {

  if (isset($_GET["getText"])) {
    $optionen = gpt_functions::getOptionen();

    header("etagb: " . $optionen->prompt_id);


    return fe::getText();
  }
  if (isset($_GET["sendAnswer"])) {
    $optionen = gpt_functions::getOptionen();

    $answer_id = answers::insertToDb();
    $answer = new answers($answer_id);
    $answer->updateFromPostAndGet();
    $answer->saveToDb();

    $answer_text = gpt_functions::secureCharForMysql($_POST["answer"]);
    gpt_functions::sendWsPost("gpt2024a", ["a"=>"a", "answer" => $answer_text, "answer_id" => $answer->answer_id]);

    $chart = "";
    if ($optionen->c == 1) {
      $chart = "<script>
      drawChart(" . json_encode(gpt_functions::getChartConfig()) . ");
      </script>";
    }

    return "<div id='text'>
    <form>
    <h2>{$optionen->p}&nbsp;___</h2>
    <div id='inputs'>
        <input value='$answer_text' autocomplete='off' disabled>
        <span class='btn disable' ><i class='fa fa-check' aria-hidden='true'></i></span>
        </div>
        </form>
        <div id='chart'></div>

    </div>" . $chart;


    
  }

  if (isset($_GET["class"])) {
    admin_gpt::checkLogin();
    if ($_GET["class"] == "template_prompt") {
      return template_prompt::handleApi();
    }
    if ($_GET["class"] == "send_prompts") {
      return send_prompts::handleApi();
    }
    if ($_GET["class"] == "workshops") {
      return workshops::handleApi();
    }
    if ($_GET["class"] == "usernames") {
      return usernames::handleApi();
    }
    return;
  }

  if (isset($_GET["setSessionUsername"])) {
    $username = gpt_functions::secureCharForMysql($_POST["username"]);
    $_SESSION["username"] = $username;

    usernames::updateUsername(session_id(), $username);
    gpt_functions::sendWsPost("gpt2024a", ["a"=>"newUser", "session_id" => session_id(), "username" => $username]);

    return fe::getUi();
  }

  if (isset($_GET["start"])) {
    if (isset($_SESSION["username"])) {
      return fe::getUi();
    } else {
      return fe::userName();
    }
  }

  if (isset($_GET["resetUsername"])) {
    $_SESSION["username"] = null;
    
    gpt_functions::sendWsPost("gpt2024a", ["a"=>"userReseted", "session_id" => session_id()]);


    return fe::userName($_GET["resetUsername"]);
  }



  if (isset($_GET["setPrompt"])) {
    return send_prompts::setPrompt($_POST["prompt"], $_POST["template"]);
  }



  if (isset($_GET["setNextWord"])) {
    admin_gpt::checkLogin();

    $old_prompt = new send_prompts($_POST["old_prompt_id"]);
    $old_prompt->selected_word = $_POST["nextword"];
    $old_prompt->saveToDb();

    // var_dump($old_prompt);
    $prompt_id = send_prompts::insertToDb();
    $send_promt = new send_prompts($prompt_id);

    $new_prompt = trim($old_prompt->prompt) . " " .  $_POST["nextword"];

    $optionen = gpt_functions::getOptionen();
    $optionen->p = $new_prompt;
    $send_promt->prompt = $new_prompt;
    // $optionen->t = $old_prompt->f_template_id ?? 0;
    $send_promt->f_template_id = $old_prompt->f_template_id ?? 0;
    // $optionen->time = time();

    $optionen->prompt_id = $prompt_id;
    $optionen->c = 0;
    gpt_functions::setOptionen($optionen);

    $send_promt->saveToDb();

    gpt_functions::sendWsPost("gpt2024p", ["a"=>"p"]);
    $send_promt = new send_prompts($prompt_id);

    return $send_promt->getSetPromptHtml();
  }

  if (isset($_GET["sendGraphToUsers"])) {
    admin_gpt::checkLogin();

    gpt_functions::setChartConfig(json_decode($_POST["chartConfig"]));

    $optionen = gpt_functions::getOptionen();
    $optionen->c = 1;
    gpt_functions::setOptionen($optionen);

    gpt_functions::sendWsPost("gpt2024p", ["a"=>"chart"]);
    // $send_promt = new send_prompts($prompt_id);

    return 1;
  }

}
}
