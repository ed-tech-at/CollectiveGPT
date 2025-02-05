<?php
namespace gpt2024;
use \app_ed_tech\pdoDb;
use \app_ed_tech\edTech;

class usernames {

  public $usernames_id;
  public $session_id;
  public $username;
  public $created;
  public $f_workshop_id;

  public function __construct($_usernames_id) {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare(
      'SELECT * FROM usernames WHERE usernames_id = :usernames_id'
    );
    $stmt->execute(['usernames_id' => $_usernames_id]);

    $data = $stmt->fetch();
    $stmt->closeCursor();

    if (!empty($data)) {
      $this->usernames_id = $data['usernames_id'];
      $this->session_id = $data['session_id'];
      $this->username = $data['username'];
      $this->created = $data['created'];
      $this->f_workshop_id = $data['f_workshop_id'];
    } else {
      throw new \Exception("Usernames ($_usernames_id) not found", 404001);
    }
  }

  public function saveToDb() {
    $db = pdoDb::getConnection();

    $stmt = $db->prepare("UPDATE usernames SET
      session_id = :session_id,
      username = :username,
      f_workshop_id = :f_workshop_id,
      created = :created
    WHERE usernames_id = :usernames_id");
    
    $stmt->execute([
      'session_id' => gpt_functions::secureCharForMysql($this->session_id),
      'username' => gpt_functions::secureCharForMysql($this->username),
      'f_workshop_id' => gpt_functions::secureCharForMysql($this->f_workshop_id),
      'created' => gpt_functions::secureCharForMysql($this->created),
      'usernames_id' => gpt_functions::secureCharForMysql($this->usernames_id),
    ]);
    
    $stmt->closeCursor();
  }

  public function updateFromPostAndGet() {
    $this->session_id = $_GET["sendSession"];
    $this->username = $_POST["username"];
  }

  /**
   * @return int NewId
   */
  public static function insertToDb() {
    $db = pdoDb::getConnection();

    $optionen = gpt_functions::getOptionen();



    $stmt = $db->prepare("INSERT INTO usernames 
    (created, f_workshop_id) VALUES 
    (:created, :f_workshop_id)");
    $stmt->execute([
      "created" => gpt_functions::secureCharForMysql(time()),
      "f_workshop_id" => gpt_functions::secureCharForMysql($optionen->active_workshop)
    ]);
    $new_id = $db->lastInsertId();
    $stmt->closeCursor();
    return $new_id;
  }

  public static function updateUsername($session_id, $username) {
    $db = pdoDb::getConnection();
    $optionen = gpt_functions::getOptionen();


    $stmt = $db->prepare(
      'SELECT usernames_id FROM usernames WHERE session_id = :session_id AND f_workshop_id = :f_workshop_id'
    );
    $stmt->execute(['session_id' => $session_id, 
    'f_workshop_id' => $optionen->active_workshop]);

    $data = $stmt->fetch();
    $stmt->closeCursor();

    if (!empty($data)) {
      $stmt = $db->prepare("UPDATE usernames SET
        username = :username
      WHERE usernames_id = :usernames_id");
      
      $stmt->execute([
        'username' => gpt_functions::secureCharForMysql($username),
        'usernames_id' => gpt_functions::secureCharForMysql($data['usernames_id'])
      ]);
      
      $stmt->closeCursor();
    } else {
      $stmt = $db->prepare("INSERT INTO usernames 
      (session_id, username, created, f_workshop_id) VALUES 
      (:session_id, :username, :created, :f_workshop_id)");
      $stmt->execute([
        'session_id' => gpt_functions::secureCharForMysql($session_id),
        'username' => gpt_functions::secureCharForMysql($username),
        'created' => gpt_functions::secureCharForMysql(time()),
        'f_workshop_id' => gpt_functions::secureCharForMysql($optionen->active_workshop)
      ]);
      $stmt->closeCursor();
    }
  }

  public static function generateUsernamesListScript() {
    $db = pdoDb::getConnection();

    $optionen = gpt_functions::getOptionen();

    $stmt = $db->prepare("SELECT * FROM usernames WHERE f_workshop_id = :f_workshop_id");
    $stmt->execute(['f_workshop_id' => $optionen->active_workshop]);
    $usernames = $stmt->fetchAll();
    $stmt->closeCursor();

    // Create the usernamesList array in PHP
    $usernamesList = [];
    foreach ($usernames as $user) {
      $usernamesList[] = [
        'session_id' => $user['session_id'],
        'username' => $user['username'],
        'userReseted' => false // assuming all users are not reset initially
      ];
    }

    // Convert the array to JSON
    $usernamesListJson = json_encode($usernamesList);

    // Echo the <script> tag with the usernamesList
    echo "Usercount: <span class='usercounter'>" . count($usernamesList) . "</span><br>

  <div id='usernames-list'></div>
  <script>
      var usernamesList = $usernamesListJson;
      parseUsernamesList();
    </script>";
  }

  public static function handleApi() {
    global $rootDir;

    if (isset($_GET["reset_session_id"])) {
      gpt_functions::sendWsPost("gpt2024p", ["a" => "reset", "session_id" => $_GET["reset_session_id"]]);
      exit();
    }
  }

}
?>
