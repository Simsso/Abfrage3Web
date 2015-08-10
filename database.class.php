<?php
	require('dbconnect.inc.php');
	require('validation.class.php');
	
	class Database {
		static function register_user($firstname, $lastname, $email, $password, $confirmpassword) {
			if ($firstname == NULL) {
				throw new Exception("No first name given");
			} else if ($lastname == NULL) {
				throw new Exception("No last name given");
			} else if (!Validation::is_email($email)) {
				throw new Exception("Invalid email address");
			} else if (!Validation::is_password($password)) {
				throw new Exception("Invalid password");
			} else if ($password != $confirmpassword) {
				throw new Exception("Different passwords");
			} else if (!self::email_available($email)) {
				throw new Exception("Email already in use");
			} else {
				$salt = rand(0, 999999);
				$password = sha1($salt . $password);
				unset($confirmpassword);
				
				$email_confirmation_key = sha1($salt . $email . $password);
				$reg_time = time();
				
				global $con;
				$sql = "INSERT INTO `user` (`firstname`, `lastname`, `email`, `password`, `salt`, `reg_time`, `email_confirmation_key`) 
					VALUES ('" . $firstname . "', '" . $lastname . "', '" . $email . "', '" . $password . "', '" . $salt . "', '" . $reg_time . "', '" . $email_confirmation_key . "')";
				$query = mysqli_query($con, $sql);
				
				// send email
				Mail::get_email_confirmation_mail($firstname, $email, $email_confirmation_key)->send();
				return TRUE;
			}
		}
		
		static function email_available($email) {
			global $con; 
			$sql = "SELECT COUNT(`id`) AS `count` FROM `user` WHERE `email` = '" . $email . "'";
			$query = mysqli_query($con, $sql);
			$count = mysqli_fetch_object($query)->count;
			if ($count == 0) {
				return TRUE;
			} else {
				return FALSE;
			}
		}
		
		// checks a email password combination
		// returns:
		// 0: wrong combination
		// 1: right combination and email has been confirmed
		// 2: right combination and email has not been confirmed yet
		static function check_login_data($email, $password) {
			global $con;
			
			$password_hash = sha1(self::get_salt_by_email($email) . $password);
			$sql = "SELECT COUNT(`id`) AS `count` FROM `user` WHERE `email` = '" . $email . "' AND `password` = '" . $password_hash . "'";
			$query = mysqli_query($con, $sql);
			$count = mysqli_fetch_object($query)->count;
			if ($count == 0) {
				return 0;
			} else {
				$sql = "SELECT COUNT(`id`) AS `count` FROM `user` WHERE `email` = '" . $email . "' AND `password` = '" . $password_hash . "' AND `email_confirmed` = '0'";
				$query = mysqli_query($con, $sql);
				$count = mysqli_fetch_object($query)->count;
				if ($count == 1) {
					return 2;
				} else {
					return 1;
				}
			}
		}
		
		static function get_salt_by_email($email) {
			global $con;
			
			$sql = "SELECT `salt` FROM `user` WHERE `email` = '$email'";
			$query = mysqli_query($con, $sql);
			while ($row = mysqli_fetch_assoc($query)) { 
			  return $row['salt'];
			}
			return null;
		}
		
		static function email2id($email) {
			global $con;
			
			$sql = "SELECT `id` FROM `user` WHERE `email` = '$email'";
			$query = mysqli_query($con, $sql);
			while ($row = mysqli_fetch_assoc($query)) { 
			  return $row['id'];
			}
            return NULL;
		}
		
		static function get_user_by_id($id) {
			return new User($id);
		}
		
		static function confirm_email($email, $key) {
			global $con; 
			$sql = "SELECT COUNT(`id`) AS `count` FROM `user` WHERE `email` = '$email' AND `email_confirmation_key` = '$key'";
			$query = mysqli_query($con, $sql);
			$count = mysqli_fetch_object($query)->count;
			if ($count == 1) {
				$sql = "UPDATE `user` SET `email_confirmed` = '1' WHERE `id` = '" . self::email2id($email) . "'";
				$query = mysqli_query($con, $sql);
				return TRUE;
			}
			return FALSE;
		}
		
		static function add_login($id) {
			global $con;
			$ip = $_SERVER['REMOTE_ADDR']; 
			$time = time();
			
			$sql = "INSERT INTO `login` (`user`, `time`, `ip`) VALUES ('" . $id . "', '" . $time . "', '" . $ip . "')";
			$query = mysqli_query($con, $sql);
		}
		
		static function first_login_of_user($id) {
			global $con; 
			$sql = "SELECT COUNT(`id`) AS `count` FROM `user` WHERE `id` = '$id'";
			$query = mysqli_query($con, $sql);
			$count = mysqli_fetch_object($query)->count;
			if ($count == 1) {
				return TRUE;
			}
			return FALSE;
		}
		
		static function get_last_login_of_user($id) {
			global $con;
			$sql = "SELECT * FROM `login` WHERE `user` = '$id' ORDER BY `time` DESC LIMIT 1";
			$query = mysqli_query($con, $sql);
			while ($row = mysqli_fetch_assoc($query)) { 
			  return new Login($row['id'], $row['user'], $row['time'], $row['ip']);
			}
			return NULL;
		}
		
		static function get_next_to_last_login_of_user($id) {
			global $con;
			$sql = "SELECT * FROM `login` WHERE `user` = '$id' ORDER BY `time` DESC LIMIT 1,2";
			$query = mysqli_query($con, $sql);
			while ($row = mysqli_fetch_assoc($query)) { 
			  return new Login($row['id'], $row['user'], $row['time'], $row['ip']);
			}
			return NULL;
		}
		
		static function get_list_of_added_users_of_user($id) {
			global $con;
			$sql = "
            SELECT `user`.`id`, `user`.`firstname`, `user`.`lastname`, `user`.`email` 
            FROM `user`, `relationship` 
            WHERE `user`.`id` = `relationship`.`user2` AND `relationship`.`user1` = '" . $id . "' AND `relationship`.`type` = 1";
			$query = mysqli_query($con, $sql);
			$result = array();
			while ($row = mysqli_fetch_assoc($query)) { 
                $user = new SimpleUser($row['id'], $row['firstname'], $row['lastname'], $row['email']);
                $user->bidirectional = self::users_have_added_them_both($id, $row['id']);
                array_push($result, $user);
			}
			return $result;
		}
        
        static function get_list_of_users_who_have_added_user($id) {
			global $con;
			$sql = "
            SELECT `user`.`id`, `user`.`firstname`, `user`.`lastname`, `user`.`email` 
            FROM `user`, `relationship` 
            WHERE `user`.`id` = `relationship`.`user1` AND `relationship`.`user2` = '$id' AND `relationship`.`type` = 1";
			$query = mysqli_query($con, $sql);
			$result = array();
			while ($row = mysqli_fetch_assoc($query)) {  
                $user = new SimpleUser($row['id'], $row['firstname'], $row['lastname'], $row['email']);
                $user->bidirectional = self::users_have_added_them_both($id, $row['id']);
                array_push($result, $user);
			}
			return $result;
        }
        
        static function users_have_added_them_both($user1, $user2) {
			global $con; 
			$sql = "SELECT COUNT(`id`) AS `count` FROM `relationship` WHERE `user1` = '$user1' AND `user2` = '$user2' AND `relationship`.`type` = 1";
			$query = mysqli_query($con, $sql);
            if (mysqli_fetch_object($query)->count == 1) {
                $sql = "SELECT COUNT(`id`) AS `count` FROM `relationship` WHERE `user2` = '$user1' AND `user1` = '$user2' AND `relationship`.`type` = 1";
                $query = mysqli_query($con, $sql);
                if (mysqli_fetch_object($query)->count == 1) {
                    return true;
                }
            }
            return false;
        }
		
		static function get_number_of_registered_users() {
			global $con; 
			$sql = "SELECT COUNT(`id`) AS `count` FROM `user`";
			$query = mysqli_query($con, $sql);
			return mysqli_fetch_object($query)->count;
		}
		
		static function get_number_of_logins_during_last_time($time_in_seconds) {
			$time_min = time() - $time_in_seconds;
			global $con; 
			$sql = "SELECT COUNT(`id`) AS `count` FROM `login` WHERE `time` > '$time_min'";
			$query = mysqli_query($con, $sql);
			return mysqli_fetch_object($query)->count;
		}
        
        static function add_user($id, $email) {
            global $con;
            $added_user_id = self::email2id($email);
            
            if (is_null($added_user_id))
                return -1;
            
            if ($added_user_id == $id) 
                return 2;
            
            $time = time();
            // check if the user is already added
            if (!self::user_already_have_relationship($id, $added_user_id)) {
                $sql = "INSERT INTO `relationship` (`user1`, `user2`, `time`, `type`) VALUES ('$id', '$added_user_id', '$time', '1')";
                $query = mysqli_query($con, $sql);
                return 1;
            } else {
				$sql = "UPDATE `relationship` SET `type` = '1', `time`= '$time' WHERE `user1` = '$id' AND `user2` = '$added_user_id'";
				$query = mysqli_query($con, $sql);
				return 1;
            }
            return 0;
        }
        
        static function remove_user($user1, $user2) {
                global $con;
            
				$sql = "UPDATE `relationship` SET `type` = '0' WHERE `user1` = '$user1' AND `user2` = '$user2'";
				$query = mysqli_query($con, $sql);
				return 1;
        }
        
        static function user_already_have_relationship($user1, $user2) {
            global $con;
            
			$sql = "SELECT COUNT(`id`) AS `count` FROM `relationship` WHERE `user1` = '$user1' AND `user2` = '$user2'";
			$query = mysqli_query($con, $sql);
			if (mysqli_fetch_object($query)->count == 0) {
                return false;
            }
            return true;
        }
        
        
        // word lists
        
        static function add_word_list($id, $name) {
            // returns id and state
            global $con;
            $time = time();
            $sql = "INSERT INTO `list` (`name`, `creator`, `creation_time`) VALUES ('$name', '$id', '$time')";
            $query = mysqli_query($con, $sql);
            
            $result->state = 1;
            $result->id = mysqli_insert_id($con);
            return $result;
        }
        
        static function get_word_lists_of_user($id) {
			global $con;
			$sql = "
            SELECT `id`, `name`, `creator`, `comment`, `language1`, `language2`, `creation_time` 
            FROM `list`
            WHERE `creator` = '$id' AND `active` = '1'";
			$query = mysqli_query($con, $sql);
			$result = array();
			while ($row = mysqli_fetch_assoc($query)) {  
                $list = new BasicWordList($row['id'], $row['name'], $row['creator'], $row['comment'], $row['language1'], $row['language2'], $row['creation_time']);
                array_push($result, $list);
			}
			return $result;
        }
        
        static function get_word_list($user_id, $word_list_id) {
			global $con;
			$sql = "
            SELECT `list`.`id`, `list`.`name`, `list`.`creator`, `list`.`comment`, `list`.`language1`, `list`.`language2`, `list`.`creation_time` 
            FROM `list`, `share`
            WHERE (`list`.`creator` = '" . $user_id . "' OR `share`.`user` = '".$user_id."' AND `share`.`list` = '".$word_list_id."') AND `list`.`id` = '" . $word_list_id . "'";
			$query = mysqli_query($con, $sql);
			while ($row = mysqli_fetch_assoc($query)) {  
                return new WordList(
                    $row['id'], 
                    $row['name'], 
                    SimpleUser::get_by_id($row['creator']), 
                    $row['comment'], 
                    $row['language1'], 
                    $row['language2'], 
                    $row['creation_time'],
                    self::get_words_of_list($row['id']));
			}
        }
        
        static function rename_word_list($user_id, $list_id, $list_name) { 
            // TODO
        }
        static function get_words_of_list($list_id) {
            global $con;
			$sql = "SELECT * FROM `word` WHERE `list` = '$list_id' AND `status` = '1' ORDER BY `id` DESC";
			$query = mysqli_query($con, $sql);
            $output = array();
			while ($row = mysqli_fetch_assoc($query)) { 
                array_push($output, new Word($row['id'], $row['list'], $row['language1'], $row['language2']));
            }
            return $output;
        }
        
        static function delete_word_list($user_id, $word_list_id) {
            global $con;

            $sql = "UPDATE `list` SET `active` = '0' WHERE `id` = '$word_list_id' AND `creator` = '$user_id'";
            $query = mysqli_query($con, $sql);
            return 1;
        }
        
        static function add_word($user_id, $word_list_id, $lang1, $lang2) {
            global $con;
            $sql = "INSERT INTO `word` (`list`, `language1`, `language2`)
                VALUES ('" . $word_list_id . "', '" . $lang1 . "', '" . $lang2 . "')";
            $query = mysqli_query($con, $sql);
            return mysqli_insert_id($con);
        }
        
        static function update_word($user_id, $word_id, $lang1, $lang2) {
            global $con;
            // TODO: add check if word is owned by $user_id
            $sql = "UPDATE `word` SET `language1` = '$lang1', `language2` = '$lang2' WHERE `id` = '$word_id'";
            $query = mysqli_query($con, $sql);
            return 1;
        }
        
        static function remove_word($user_id, $word_id) {
            global $con;
            // TODO: add check if word is owned by $user_id
            $sql = "UPDATE `word` SET `status` = '0' WHERE `id` = '$word_id'";
            $query = mysqli_query($con, $sql);
            return 1;
        }
        
        static function get_list_of_shared_word_lists_of_user($id) {
            global $con;
			$sql = "SELECT `share`.`id` AS 'share_id', `list`.`id` AS 'list_id', `list`.`name`, `list`.`creator`, `list`.`comment`, `list`.`language1`, `list`.`language2`, `list`.`creation_time` FROM `share`, `list` WHERE `share`.`list` = `list`.`id` AND `list`.`creator` = '$id' AND `list`.`active` = '1'";
			$query = mysqli_query($con, $sql);
			$result = array();
			while ($row = mysqli_fetch_assoc($query)) {  
                $list = new BasicWordList($row['list_id'], $row['name'], $row['creator'], $row['comment'], $row['language1'], $row['language2'], $row['creation_time']);
                $list->sharing_id = $row['share_id'];
                array_push($result, $list);
			}
			return $result;
        }
        
        static function get_list_of_shared_word_lists_with_user($id) {
            global $con;
            $sql = "
                SELECT `share`.`id` AS 'share_id', `share`.`permissions`, `list`.`id` AS 'list_id', `list`.`name`, `list`.`creator`, `list`.`comment`, `list`.`language1`, `list`.`language2`, `list`.`creation_time`
                FROM `share`, `list`, `relationship` 
                WHERE `share`.`user` = '$id' AND `share`.`list` = `list`.`id` AND `list`.`active` = '1' AND `share`.`permissions` <> '0' 
                    AND `relationship`.`user1` = '$id' AND `relationship`.`user2` = `list`.`creator` AND `relationship`.`type` = '1'";
			$query = mysqli_query($con, $sql);
			$result = array();
			while ($row = mysqli_fetch_assoc($query)) {  
                $list = new BasicWordList($row['list_id'], $row['name'], $row['creator'], $row['comment'], $row['language1'], $row['language2'], $row['creation_time']);
                $list->permissions = $row['permissions'];
                $list->sharing_id = $row['share_id'];
                array_push($result, $list);
			}
			return $result;
        }
        
        static function set_sharing_permissions($user_id, $word_list_id, $email, $permissions) {
            $share_with_id = self::email2id($email);
			global $con; 
			$sql = "SELECT COUNT(`id`) AS `count` FROM `share` WHERE (`user` = '" . $share_with_id . "' OR `user` = '".$user_id."') AND `list` = '" . $word_list_id . "'";
			$query = mysqli_query($con, $sql);
			$count = mysqli_fetch_object($query)->count;
			if ($count == 0) {
				$sql = "INSERT INTO `share` (`user`, `list`, `permissions`) VALUES ('" . $share_with_id . "', '" . $word_list_id . "', '" . $permissions . "')";
				$query = mysqli_query($con, $sql);
            } else {
				$sql = "UPDATE `share` SET `permissions` = '$permissions' WHERE `list` = '" . $word_list_id . "' AND (`user` = '" . $share_with_id . "' OR `user` = '".$user_id."')";
				$query = mysqli_query($con, $sql);
            }
            return 1;
        }
        
        static function set_sharing_permissions_by_sharing_id($user_id, $id, $permissions) {
            global $con;
            
            $sql = "
                UPDATE `share`, `list`
                SET `share`.`permissions` = '$permissions' 
                WHERE `share`.`id` = '$id' AND (`list`.`id` = `share`.`list` AND `list`.`creator` = '" . $user_id . "' OR `share`.`user` = '" . $user_id . "')";
            $query = mysqli_query($con, $sql);
            return 1;
        }
        
        static function get_sharing_perimssions_of_list_with_user($list_owner, $word_list_id, $email) {
            $share_with_id = self::email2id($email);
            
            global $con;
			$sql = "SELECT `share`.`id`, `share`.`permissions` FROM `share`, `list` WHERE `list`.`id` = '$word_list_id' AND `share`.`list` = `list`.`id` AND `list`.`creator` = '$list_owner' AND `list`.`active` = '1' AND `list`.`user` = '$share_with_id'";
			$query = mysqli_query($con, $sql);
			while ($row = mysqli_fetch_assoc($query)) {  
                return new SharingInformation($row['id'], new SimpleUser($share_with_id, null, null, $email), $row['permissions']);
			}
        }
        
        static function get_sharing_info_of_list($user_id, $word_list_id) {
            global $con;
			$sql = "
                SELECT `share`.`permissions`, `share`.`id` AS 'share_id', `user`.`id` AS 'user_id', `user`.`firstname`, `user`.`lastname`, `user`.`email` 
                FROM `share`, `list`, `user` 
                WHERE `share`.`list` = `list`.`id` AND `share`.`user` = `user`.`id` AND 
                    `list`.`id` = '$word_list_id' AND `list`.`creator` = '$user_id' AND `list`.`active` = '1' AND `share`.`permissions` <> '0'";
			$query = mysqli_query($con, $sql);
			$result = array();
			while ($row = mysqli_fetch_assoc($query)) {  
                array_push($result, new SharingInformation($row['share_id'], new SimpleUser($row['user_id'], $row['firstname'], $row['lastname'], $row['email']), $row['permissions']));
			}
            return $result;
        }
        
        
        // word list labels
        
        static function add_label($user_id, $label_name, $parent_label_id) {
            global $con;
            // TODO
        }
        
        static function set_label_status($user_id, $label_id, $status) {
            global $con;
            $sql = "UPDATE `label` SET `status` = '".$status."' WHERE `id` = '".$label_id."' AND `user` = '".$user_id."'";
            $query = mysqli_query($con, $sql);
            return 1;
        }
        
        static function attach_list_to_label($user_id, $label_id, $list_id) {
            global $con;
            // TODO
        }
        
        static function get_labels_of_user($user_id) {
            global $con;
            // TODO
        }
        
        static function rename_label($user_id, $label_id, $label_name) {
            global $con;
            // TODO
        }
	}

    class Label {
        // TODO
    }

	class SimpleUser {
		public $id;
		public $firstname;
		public $lastname;
		public $email;
		
		public function __construct($id, $firstname, $lastname, $email) {
			$this->id = $id;
			$this->firstname = $firstname;
			$this->lastname = $lastname;
            $this->email = $email;
		}
		
		public function get_last_login() {
			return Database::get_last_login_of_user($this->id);
		}
		
		public function get_next_to_last_login() {
			return Database::get_next_to_last_login_of_user($this->id);
		}
        
        static function get_by_id($id) {
            global $con;
			
			$sql = "SELECT `firstname`, `lastname`, `email` FROM `user` WHERE `id` = '" . $id . "'";
			$query = mysqli_query($con, $sql);
			while ($row = mysqli_fetch_assoc($query)) { 
			  return new SimpleUser($id, $row['firstname'], $row['lastname'], $row['email']);
			}
        }
	}
		
		
	class User extends SimpleUser {
		public $password;
		public $salt;
		public $reg_time;
		public $email_confirmed;
		public $email_confirmation_key;
		
		public function __construct($id) {
			global $con;
			
			$sql = "SELECT * FROM `user` WHERE `id` = '" . $id . "'";
			$query = mysqli_query($con, $sql);
			while ($row = mysqli_fetch_assoc($query)) { 
			  $this->id = $id;
			  $this->firstname = $row['firstname'];
			  $this->lastname = $row['lastname'];
			  $this->email = $row['email'];
			  $this->password = $row['password'];
			  $this->salt = $row['salt'];
			  $this->reg_time = $row['reg_time'];
			  $this->email_confirmed = $row['email_confirmed'];
			  $this->email_confirmation_key = $row['email_confirmation_key'];
			}
		}
	}
	
	class Login {
		public $id;
		public $user_id;
		public $date;
		public $ip;
		
		public function __construct($id, $user_id, $date, $ip) {
			$this->id = $id;
			$this->user_id = $user_id;
			$this->date = $date;
			$this->ip = $ip;
		}
		
		public function get_date_string() {
			return date("r", $this->date);
		}
	}

    class BasicWordList {
        public $id;
        public $name;
        public $creator;
        public $comment;
        public $language1;
        public $language2;
        public $creation_time;
        
        public function __construct($id, $name, $creator, $comment, $language1, $language2, $creation_time) {
            $this->id = $id;
            $this->name = $name;
            $this->creator = $creator;
            $this->comment = $comment;
            $this->language1 = $language1;
            $this->language2 = $language2;
            $this->creation_time = $creation_time;
        }
    }

    class WordList extends BasicWordList {
        public $words;
        
        public function __construct($id, $name, $creator, $comment, $language1, $language2, $creation_time, $words) {
            $this->words = $words;
            parent::__construct($id, $name, $creator, $comment, $language1, $language2, $creation_time);
        }
    }

    class Word {
        public $id;
        public $list;
        public $language1;
        public $language2;
        
        public function __construct($id, $list, $language1, $language2) {
                $this->id = $id;
                $this->list = $list;
                $this->language1 = $language1;
                $this->language2 = $language2;
        }
        
        static function get_by_id($id) {
            global $con;
			$sql = "SELECT * FROM `word` WHERE `id` = '$id' ORDER BY `id` DESC";
			$query = mysqli_query($con, $sql);
			while ($row = mysqli_fetch_assoc($query)) { 
                return new Word($id, $row['list'], $row['language1'], $row['language2']);
            }
        }
    }

    class SharingInformation {
        public $id;
        public $user;
        public $permissions;
        
        public function __construct($id, SimpleUser $user, $permissions) {
            $this->id = $id;
            $this->user = $user;
            $this->permissions = $permissions;
        }
    }
?>