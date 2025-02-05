<?php
namespace gpt2024;
use \app_ed_tech\edTech;
use \app_ed_tech\pdoDb;

class admin_gpt {

public static function  getAdminHtml() {
echo admin_gpt::checkLogin();

if (isset($_GET["active_workshop"])) {
  $optionen = gpt_functions::getOptionen();

  $optionen->active_workshop = $_GET["active_workshop"];

  template_prompt::resetLastTimeUsed();

  gpt_functions::setOptionen($optionen);

  send_prompts::setPrompt("", "1");

  if ($_GET["active_workshop"] == "-1") {
    gpt_functions::sendWsPost("gpt2024p", ["a"=>"stop"]);
  }

}

if (isset($_GET["next_prompt_template"])) {
 

  send_prompts::setPrompt($_GET["prompt"], $_GET["next_prompt_template"]);
  return \app_ed_tech\edTech::throw303SeeOther("/admin_gpt/");

}

echo admin_gpt::getHtmlHeaderAdmin("GPT");
$optionen = gpt_functions::getOptionen();

if (isset($_GET["template"])) {

  echo template_prompt::getEditInterface(null);

  $all_templates = template_prompt::getAllPrompts(true);
  foreach ($all_templates as $template) {
    echo $template->getDetails();
  }

  return;
}
if (isset($_GET["workshops"])) {

  echo workshops::getEditInterface(null);

  $all_workshops = workshops::getAllworkshops();
  foreach ($all_workshops as $event) {
    echo $event->getDetails();
  }

  return;
}
if (isset($_GET["users"])) {

  echo "<h1>Usernames for Workshop W" . $optionen->active_workshop . "</h1>
  ";

  echo usernames::generateUsernamesListScript();
  

    
  echo "<script>
  startNchanAdmin(2);
  </script>";

  return;
}


$latestPrompt = send_prompts::getLatestPrompt();
echo $latestPrompt->getSetPromptHtml();

echo "<script>
startNchanAdmin(2);
</script>";

echo "<h2>Template</h2>";
$allItems = template_prompt::getAllPrompts(false);
$list = "<div id='template_prompt-list'>";
foreach ($allItems as $item) {
  $list .= $item->getSimple();
}
$list .= "</div>";
echo $list;

}



public static function checkLogin()
{

  if (session_status() != 2) {
    session_start();
  }
  

  global $cookie_pw;
  global $login_pw;
  global $siteName;
  global $rootDir;
  global $version;

	if (isset($_GET['logout']) && $_GET['logout'] == "1") {
		$_SESSION["login"] = 0;

    setcookie ( "login_gpt24", "", time() , "/", $_SERVER['HTTP_HOST'], true, true);
    $_COOKIE["login_gpt24"] = 0;
		session_destroy();
	}

	if (isset($_SESSION['login']) && $_SESSION['login'] == 1) {
		return;
	}

  if (isset($_COOKIE["login_gpt24"]) &&  $_COOKIE["login_gpt24"] == $cookie_pw) {
		return;
	}

	if (isset($_POST['login_pw']) && $_POST['login_pw'] == $login_pw) {
		$_SESSION["login"] = 1;
		if (isset($_POST['login_pw'])) {
			// echo "<div class=\"alert alert-success\" role=\"alert\">Login erfolgreich</div>";
      setcookie ( "login_gpt24", $cookie_pw, time() + (86400 * 365), "/", $_SERVER['HTTP_HOST'], true, true);

      gpt_functions::addSessionStatusMeldung("success", "Login erfolgreich");
      edTech::throw303SeeOther($rootDir."/admin_gpt/");
		}
		return;
	}

  echo "<!DOCTYPE html>
  <html lang='de'>
  <head>
    <meta charset=\"utf-8\">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>

    <title>GPT Login</title>
    <link rel='stylesheet' href='{$rootDir}/files/2024/bootstrap.united.min.css'>
    <link rel='stylesheet' href='{$rootDir}/files/fonts/fonts.css?$version'>
    </head>
  <body>
    <nav class='navbar navbar-expand-lg navbar-dark bg-dark bg-primary mb-1'>
  <div class='container-fluid container'> 
    <a class='navbar-brand' href='{$rootDir}/admin_gpt/' >gpt</a>
</div>
  </nav>
  <main class='container mainContainer'>
    ";


	if (isset($_POST['login_pw'])) {
		echo "<div class=\"alert alert-danger\" role=\"alert\">Passwort falsch</div>";
	}
	echo "<form method='post' action='{$rootDir}/admin_gpt/'><h3>Login $siteName:</h3> 
<div class=\"form-group\">
<label for=\"login_pw\">Passwort</label>
<input type=\"password\" class=\"form-control\" id=\"login_pw\" name='login_pw'>
<input type=\"submit\" class=\"btn btn-dark\">

</div>
</form>";

	exit();
}


public static function getHtmlHeaderAdmin($title)
  {
    global $version;
    global $rootDir;
    $optionen = gpt_functions::getOptionen();

    $db = pdoDb::getConnection();

    $stmt = $db->prepare("SELECT count(f_workshop_id) AS numberOfUsers  FROM usernames WHERE f_workshop_id = :f_workshop_id");
    $stmt->execute(['f_workshop_id' => $optionen->active_workshop]);
    $data = $stmt->fetch();

    $numberOfUsersInWorkshop = $data["numberOfUsers"];

    $htmlTitle = $title . " | Collective GPT Admin";
    
    
    $a = "<!DOCTYPE html>
  <html lang='de'>
  <head>
    <meta charset=\"utf-8\">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>

    <title>$htmlTitle</title>
    

    <script src='{$rootDir}/files/jquery_3.7.1.min.js' ></script>
    <script src='{$rootDir}/files/2024/bootstrap.bundle.min.js' ></script>
    
    <script src='{$rootDir}/files/htmx_2.0.1.min.js' ></script>

        <script src='{$rootDir}/files/NchanSubscriber.js' ></script>
    <script src='{$rootDir}/files/2024/gpt-admin-js.js' ></script>

    <link rel='stylesheet' href='{$rootDir}/files/2024/bootstrap.united.min.css'>
    <link rel='stylesheet' href='{$rootDir}/files/fonts/fontawesome-free-5.15.3-web/css/all.min.css'>
    
    <link rel='stylesheet' href='{$rootDir}/files/fonts/fonts.css?$version'>
    <link rel='stylesheet' href='{$rootDir}/files/2024/gpt-admin-css.css?$version'>
  
    <script src='{$rootDir}/files/chart_4.4.3.js'></script>

  </head>
  <body>
  <nav class='navbar navbar-expand-lg navbar-dark bg-dark bg-primary mb-1'>
  <div class='container-fluid container'> 
    <a class='navbar-brand' href='{$rootDir}/admin_gpt/' >gpt</a>

      <a class='navbar-brand' href='{$rootDir}/admin_gpt/'>Prompt</a>
      <a class='navbar-brand' href='{$rootDir}/admin_gpt/?users=1'>Users ($numberOfUsersInWorkshop)</a>
      <a class='navbar-brand' href='{$rootDir}/admin_gpt/?workshops=1'>Workshops (W{$optionen->active_workshop})</a>
      
      
      <button class='navbar-toggler' type='button' data-bs-toggle='collapse' data-bs-target='#navbarColor01' aria-controls='navbarColor01' aria-expanded='false' aria-label='Toggle navigation'>
      <span class='navbar-toggler-icon'></span>
      </button>
      
      <div class='collapse navbar-collapse' id='navbarColor01'>
      ";   
      $a .= "  

      <a class='navbar-brand' href='{$rootDir}/admin_gpt/?template=1'>Template</a>

      <a class='navbar-brand' href='{$rootDir}/admin_gpt/?logout=1'>Logout</a>
      
      ";
    
    

$a .= "
    </div>
    </div>
  </nav>
  <main class='container mainContainer'>

  " . gpt_functions::getSessionStatusMeldung();
    return $a;
  }
}