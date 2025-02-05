<?php
namespace gpt2024;

use \app_ed_tech\pdoDb;
use \app_ed_tech\edTech;

class fe {

  public static function main($title = "EdTech Chatbot") {
    $a = edTech::getHtmlHeader($title);
    $a .= "<main id='main'  hx-get='/api_gpt?start=1' hx-trigger='load'></main>
<script>
startNchan(2);
</script>";
    return $a;

  }


  public static function intro() {

    if (isset($_SESSION["username"])) {
      return "<p>Collective GPT (2024) User: {$_SESSION["username"]} 
      <span class='btn getin edit' hx-get='./api_gpt?resetUsername=1' hx-target='#main' ><i class='fas fa-pencil-alt'></i></span> <span class='btn disable edit' id='glasses' onclick='doAnimate=0;$(\"#glasses\").hide()' ><i class='fas fa-low-vision'></i></span></p>
      <script>
      session_id = '" . session_id() ."';
      </script>
      ";
    } else {
      return "<p>Collective GPT (2024)</p>";
    }
    
  }

  public static function getText() {
    $optionen = gpt_functions::getOptionen();

    if (strlen($optionen->h) > 1) {
        return "<div id='text'><div id='html_code'>
      " . $optionen->h . "
        </div></div>";
  
    } elseif (strlen($optionen->p) > 0) {

      $chart = "";
      if ($optionen->c == 1) {
        $chart = "<script>
        drawChart(" . json_encode(gpt_functions::getChartConfig()) . ");
        </script>";
      }

    return "
    <div id='text'>
    <form hx-post='/api_gpt?sendAnswer={$optionen->prompt_id}'  hx-target='#text' >
    <h2>{$optionen->p}&nbsp;___</h2>
    <div id='inputs'>
        <input value='{$_SESSION["username"]}' name='username'  type='hidden'>
        <input value='' name='answer' maxlength='250' autocomplete='off'>
        <button class='send btn' ><i class='fa fa-paper-plane' aria-hidden='true'></i></button>
        </div>
        </form>
        <div id='chart'></div>
    </div>
    " . $chart;
    } else {
      return "<div id='text'>
    <h2><i>Text wird vorbereitet...</i></h2>
    </div>";

    }

    
  }
  public static function getUi() {

    return fe::intro() . fe::getText();
    
  }
  
  public static function userName($reason = 1) {

    $h2 = "<h2>Username:</h2>";
    if ($reason == 2) {
      $h2 = "<h2>Username muss geändert werden:</h2>";
    }
    return fe::intro() . "$h2
    <form hx-post='./api_gpt?setSessionUsername=1'  hx-target='#main' >
    <div id='inputs'>
        <input name='username' value='' required=1>
        <button class='disable btn' id='getin' ><i class='fa fa-sign-in-alt' aria-hidden='true'></i></button>
        </div>
        <p><label> <input type='checkbox' class='checkbox' id='agb' required=1 onchange='getInCheckbox(this)' value='1'>Sie stimmen zu, dass die eingegebenen Daten zu wissenschaftlichen Zwecken gemäß den <a href='/datenschutz/' target='_blank'>Datenschutzinformationen</a> ausgewertet und verarbeitet werden dürfen.</label></p>
        </form>
        ";

  }

  
  

}