<?php

  /**
   * Class to handle all db operations
   * This class will have CRUD methods for database tables
   *
   * @author Ravi Tamada
   */
  class DbHandlerUser {

      private $conn;

      function __construct() {
          // opening db connection
          $db = new DbConnect();
          $this->conn = $db->connect();
      }

      /* ------------- `users` table method ------------------ */

      /**
       * Creating new user
       * @param String $firstName
       * @param String $lastName
       * @param String $email User login email id
       * @param String $password User login password
       * @param String $age
       * @return String status User Create success/fail
       */
      public function createUser($first_name, $last_name, $email, $password, $age) {
          $response = array();

          // First check if user already existed in db
          if (!$this->isUserExists($email)) {
              // Generating password hash
              $password_hash = PassHash::hash($password);
              // Generating API key
              $api_key = $this->generateApiKey();
              // insert query
              $stmt = $this->conn->prepare("INSERT INTO users(first_name, last_name, email, password_hash, age, api_key, status) values (?, ?, ?, ?, ?, ?, 1)");
              $stmt->bind_param("ssssss", $first_name, $last_name, $email, $password_hash, $age, $api_key);
              $result = $stmt->execute();
              $stmt->close();

              // Check for successful insertion
              if ($result) {
                  // User successfully inserted
                  return USER_CREATED_SUCCESSFULLY;
              } else {
                  // Failed to create user
                  return FAILED_TO_CREATE;
              }
          } else {
              // User with same email already existed in the db
              return EMAIL_ALREADY_TAKEN;
          }

          return $response;
      }

      /**
       * Checking user login
       * @param String $email User login email id
       * @param String $password User login password
       * @return boolean User login status success/fail
       */
      public function checkLogin($email, $password) {
          // fetching user by email
          $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE email = ?");
          $stmt->bind_param("s", $email);
          $stmt->execute();
          $stmt->bind_result($password_hash);
          $stmt->store_result();

          if ($stmt->num_rows > 0) {
              // Found user with the email
              // Now verify the password

              $stmt->fetch();

              $stmt->close();

              if (PassHash::check_password($password_hash, $password)) {
                  // User password is correct
                  return TRUE;
              } else {
                  // user password is incorrect
                  return FALSE;
              }
          } else {
              $stmt->close();

              // user not existed with the email
              return FALSE;
          }
      }

      /**
       * Edit user
       * @param String $email User new email
       * @param String $first_name User new first_name
       * @param String $last_name User new last_name
       * @param String $age User new age
       * @return String User edit status success/fail
       */
      public function editUser($user_id, $email, $first_name, $last_name, $age) {
          $user = $this->getUserById($user_id);
          
          if (!$this->isUserExists($email) || $user['email'] == $email) {
            $stmt = $this->conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, age = ? Where id = ?");
            $stmt->bind_param("ssssi", $email, $first_name, $last_name, $age, $user_id);
            $stmt->execute();
            $num_affected_rows = $stmt->affected_rows;
            $stmt->close();
            if($num_affected_rows > 0){
                return USER_UPDATED;
            } else {
                return NO_CHANGE;
            }
          }else {
              return EMAIL_ALREADY_TAKEN;
          }
      }

       /**
       * Edit user password
       * @param String $old_password User old password
       * @param String $new_password User new password
       * @return String Password User edit status success/fail
       */
        public function editUserPassword($user_id, $old_password, $new_password) {
            // Generating API key
            $new_password_hash = PassHash::hash($new_password);
            $api_key = $this->generateApiKey();

            $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->bind_param("s", $user_id);
            $stmt->execute();
            $stmt->bind_result($password_hash);
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                // Found user with the email
                // Now verify the password

                $stmt->fetch();
                $stmt->close();

                if (PassHash::check_password($password_hash, $old_password)) {
                    if(PassHash::check_password($password_hash, $new_password)){
                        return NO_CHANGE;
                    }else{
                        $stmt = $this->conn->prepare("UPDATE users SET password_hash = ?, api_key = ? Where id = ?");
                        $stmt->bind_param("ssi", $new_password_hash, $api_key, $user_id);
                        $stmt->execute();
                        $num_affected_rows = $stmt->affected_rows;
                        $stmt->close();
                        return PASSWORD_IS_CHANGED;
                    }
                } else {
                    // user password is incorrect
                    return INCORRECT_CREDENTIALS;
                }
            } else {
                $stmt->close();
                return USER_DOESNT_EXIST;
            }
        }


      /**
       * Checking for duplicate user by email address
       * @param String $email email to check in db
       * @return boolean
       */
      private function isUserExists($email) {
          $stmt = $this->conn->prepare("SELECT id from users WHERE email = ?");
          $stmt->bind_param("s", $email);
          $stmt->execute();
          $stmt->store_result();
          $num_rows = $stmt->num_rows;
          $stmt->close();
          return $num_rows > 0;
      }

      /**
       * Fetching user by email
       * @param String $email User email id
       */
      public function getUserByEmail($email) {
          $stmt = $this->conn->prepare("SELECT id, first_name, last_name, email, age, phone, birthdate, gender, profile_picture_uri, created_at, api_key FROM users WHERE email = ?");
          $stmt->bind_param("s", $email);  
          if ($stmt->execute()) {
              $user = $stmt->get_result()->fetch_assoc();
              $stmt->close();
              return $user;
          } else {
              return NULL;
          }
      }

      /**
       * Fetching user by id
       * @param Int $id User id
       */
      public function getUserById($id) {
          $stmt = $this->conn->prepare("SELECT id, first_name, last_name, email, age, phone, birthdate, gender, profile_picture_uri, created_at, api_key FROM users WHERE id = ?");
          $stmt->bind_param("s", $id);  
          if ($stmt->execute()) {
              $user = $stmt->get_result()->fetch_assoc();
              $stmt->close();
              return $user;
          } else {
              return NULL;
          }
      }

      /**
       * Fetching user api key
       * @param String $user_id user id primary key in user table
       */
      public function getApiKeyById($user_id) {
          $stmt = $this->conn->prepare("SELECT api_key FROM users WHERE id = ?");
          $stmt->bind_param("i", $user_id);
          if ($stmt->execute()) {
              $result = $stmt->get_result()->fetch_assoc();
              $stmt->close();
              return $result['api_key'];
          } else {
              return NULL;
          }
      }

      /**
       * Fetching user id by api key
       * @param String $api_key user api key
       */
      public function getUserId($api_key) {
          $stmt = $this->conn->prepare("SELECT id FROM users WHERE api_key = ?");
          $stmt->bind_param("s", $api_key);
          if ($stmt->execute()) {
              $user_id = $stmt->get_result()->fetch_assoc();
              $stmt->close();
              return $user_id;
          } else {
              return NULL;
          }
      }

      /**
       * Validating user api key
       * If the api key is there in db, it is a valid key
       * @param String $api_key user api key
       * @return boolean
       */
      public function isValidApiKey($api_key) {
          $stmt = $this->conn->prepare("SELECT id from users WHERE api_key = ?");
          $stmt->bind_param("s", $api_key);
          $stmt->execute();
          $stmt->store_result();
          $num_rows = $stmt->num_rows;
          $stmt->close();
          return $num_rows > 0;
      }

      /**
       * Generating random Unique MD5 String for user Api key
       */
      private function generateApiKey() {
          return md5(uniqid(rand(), true));
      }
  }

?>
