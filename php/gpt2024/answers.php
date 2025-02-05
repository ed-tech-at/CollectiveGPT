<?php
namespace gpt2024;

use \app_ed_tech\pdoDb;
use \app_ed_tech\edTech;
class answers {

  public $answer_id;
  public $for_prompt_id;
  public $username;
  public $answer;
  public $submit_time;

  public function __construct($_answer_id) {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare(
      'SELECT * FROM answers WHERE answer_id = :answer_id'
    );
    $stmt->execute(['answer_id' => $_answer_id]);

    $data = $stmt->fetch();
    $stmt->closeCursor();

    if (!empty($data)) {
      $this->answer_id = $data['answer_id'];
      $this->for_prompt_id = $data['for_prompt_id'];
      $this->username = $data['username'];
      $this->answer = $data['answer'];
      $this->submit_time = $data['submit_time'];
    } else {
      throw new \Exception("answers ($_answer_id) not found", 404001);
    }
  }

  public function saveToDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE answers SET
      for_prompt_id = :for_prompt_id,
      username = :username,
      answer = :answer,
      submit_time = :submit_time
    WHERE answer_id = :answer_id");
    
    $stmt->execute([
      'for_prompt_id' => gpt_functions::secureCharForMysql($this->for_prompt_id),
      'username' => gpt_functions::secureCharForMysql($this->username),
      'answer' => gpt_functions::secureCharForMysql($this->answer),
      'submit_time' => gpt_functions::secureCharForMysql($this->submit_time),
      'answer_id' => gpt_functions::secureCharForMysql($this->answer_id),
    ]);
    
    $stmt->closeCursor();
  }

  public function updateFromPostAndGet() {
    $this->for_prompt_id = $_GET["sendAnswer"];
    $this->username = $_POST["username"];
    $this->answer = $_POST["answer"];
  }

  /**
   * @return int NewId
   */
  public static function insertToDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("INSERT INTO answers 
    (submit_time, session_id) VALUES 
    (:submit_time, :session_id)");
    $stmt->execute([
      
      "submit_time" => gpt_functions::secureCharForMysql(time()),
      "session_id" => gpt_functions::secureCharForMysql(session_id())
    ]);
    $new_id = $db->lastInsertId();
    $stmt->closeCursor();
    return $new_id;
  }

  public static function generateAnswersListScript($for_prompt_id) {
    $db = pdoDb::getConnection();



    $stmt = $db->prepare("SELECT * FROM answers WHERE for_prompt_id = :for_prompt_id");
    $stmt->execute(["for_prompt_id" => $for_prompt_id]);
    $answers = $stmt->fetchAll();
    $stmt->closeCursor();

    // Create the answersList array in PHP
    $answersList = [];
    foreach ($answers as $answer) {
      $answersList[] = [
        'answer' => $answer['answer'],
        'username' => $answer['username']
      ];
    }

    // Convert the array to JSON
    $answersListJson = json_encode($answersList);

    // Echo the <script> tag with the answersList
    return "<script>
      var answersList = $answersListJson;
      setTimeout(parseAnswersList, 300);
    </script>";
  }

}