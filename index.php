<?php
namespace app_ed_tech;

include_once __DIR__ . "/php/incl.php";

edTech::specialPages();

$optionen = \gpt2024\gpt_functions::getOptionen();
if ( $optionen->active_workshop == -1 ) {
  echo edTech::getChatbotPaused();
  return;
}

echo \gpt2024\fe::main();
