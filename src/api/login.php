<?php
class ERR
{
    public $error = false;
    public $errorMessage;

    public function __construct($errMsg = "")
    {
        if ($errMsg !== "") {
            $this->error = true;
            $this->errorMessage = $errMsg;
        }
    }
}

class LOGIN
{
    // Variables
    private $conn;
    private $username;
    private $password0;
    private $password1;
    //Contstructor
    public function __construct()
    {
        session_start();
        //Check if content is in the POST request
        if (isset($_POST["username"])) {
            $this->username = $_POST["username"];
        }
        if (isset($_POST["password0"])) {
            $this->password0 = $_POST["password0"];
        }
        if (isset($_POST["password1"])) {
            $this->password1 = $_POST["password1"];
        }
        require __DIR__ . "/../config.php";

        // Create connection
        $this->conn = new mysqli($dbServername, $dbUsername, $dbPassword, $dbName);

        // Check connection
        if ($this->conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
    }

    public function __destruct()
    {
        $this->conn->close();
    }

    /*

    USER

     */

    public function addUser($username, $password)
    {
        if ($this->getSettingByName("register") !== 0) {
            // Hash password
            $hashOptions = [
                'memory_cost' => $this->getSettingByName("memory_cost"),
                'time_cost' => $this->getSettingByName("time_cost"),
                'threads' => $this->getSettingByName("threads"),
            ];
            $password = password_hash($password, PASSWORD_ARGON2ID, $hashOptions);

            $stmt = $this->conn->prepare("INSERT INTO user (user, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $password);
            $stmt->execute();
        } else {
            $this->throwError("Registration is not allowed!");
        }
    }

    public function addUserWithPermissions($username, $password)
    {
        $this->addUser($username, $password);
        $permission = array($username);
        $args = func_get_args();
        for ($i = 2; $i < count($args); $i++) {
            array_push($permission, $args[$i]);
        }
        call_user_func_array(array($this, "addPermissions"), $permission);
    }

    public function deleteUser($username)
    {
        $stmt = $this->conn->prepare("DELETE FROM `user` WHERE `user` = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();

        // prüfen ob mehrere nutzer mit gleichen namen existieren
    }

    public function deleteUserByID($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM `user` WHERE `ID` = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    public function renameUser($id, $username)
    {
        $stmt = $this->conn->prepare("UPDATE `user` SET user=? WHERE `ID` = ?");
        $stmt->bind_param("si", $username, $id);
        $stmt->execute();
    }

    public function checkUser($user)
    {
        $stmt = $this->conn->prepare('SELECT user FROM user WHERE user = ?');
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();
        $output = $result->fetch_assoc();
        if (isset($output["user"])) {
            return true;
        } else {
            return false;
        }
    }

    public function checkUserByID($id)
    {
        $stmt = $this->conn->prepare('SELECT user FROM user WHERE ID = ?');
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $output = $result->fetch_assoc();
        if (isset($output["user"])) {
            return true;
        } else {
            return false;
        }
    }

    public function getUser($user = "", $limit = -1)
    {
        $user = '%' . $user . '%';
        if ($limit < 0) {
            $stmt = $this->conn->prepare('SELECT user, permissions, creationDate, ID FROM user WHERE user LIKE ?');
            $stmt->bind_param("s", $user);
        } else {
            $stmt = $this->conn->prepare('SELECT user, permissions, creationDate, ID FROM user WHERE user LIKE ? LIMIT ?');
            $stmt->bind_param("si", $user, $limit);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $output = $result->fetch_all(MYSQLI_ASSOC);
        for ($i = 0; $i < count($output); $i++) {
            $output[$i]["permissions"] = json_decode($output[$i]["permissions"]);
        }
        return $output;
    }

    public function getUserByID($id)
    {
        $stmt = $this->conn->prepare('SELECT user, permissions, creationDate, ID FROM user WHERE ID = ?');
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $output = $result->fetch_all(MYSQLI_ASSOC);
        $output = $output[0];
        if (isset($output["permissions"])) {
            $output["permissions"] = json_decode($output["permissions"]);
        }
        return $output;
    }

    public function countUser()
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) FROM user');
        $stmt->execute();
        $result = $stmt->get_result();
        $outp = $result->fetch_all(MYSQLI_ASSOC);
        $output["count"] = $outp[0]["COUNT(*)"];
        return $output;
    }

    /*

    PERMISSONS

     */

    public function getPermissions($user)
    {
        // prepare and bind
        $stmt = $this->conn->prepare('SELECT permissions FROM user WHERE user = ?');
        $stmt->bind_param("s", $user);
        $stmt->execute();

        $result = $stmt->get_result();
        $output = $result->fetch_assoc();
        $permissions = json_decode($output["permissions"]);

        return $permissions;
    }

    public function checkPermission($user, $permissions)
    {
        $oldPermissons = $this->getPermissions($user);
        if (isset($oldPermissons)) {
            if (in_array($permissions, $oldPermissons)) {
                $output = true;
            } else {
                $output = false;
            }
        } else {
            $output = false;
        }

        return $output;
    }

    public function checkPermissions($user)
    {
        $oldPermissons = $this->getPermissions($user);
        $args = func_get_args();
        $output = true;

        for ($i = 1; $i < count($args); $i++) {
            $permission = $args[$i];

            if ($this->checkPermission($user, $permission)) {
                $output = true;
            } else {
                $output = false;
                break;
            }
        }
        return $output;
    }

    public function addPermissions($user)
    {
        $oldPermissons = $this->getPermissions($user);
        if (!isset($output["permissions"])) {
            $oldPermissons = array();
        }
        $args = func_get_args();
        for ($i = 1; $i < count($args); $i++) {
            $permission = $args[$i];
            if ($this->checkPermission($user, $permission) === false) {
                array_push($oldPermissons, $permission);
            }
        }
        $oldPermissons = array_unique($oldPermissons);
        $permissions = json_encode($oldPermissons);

        // prepare and bind
        $stmt = $this->conn->prepare("UPDATE `user` SET `permissions` = ? WHERE `user` = ?;");
        $stmt->bind_param("ss", $permissions, $user);
        $stmt->execute();
    }

    public function deletePermissions($user)
    {
        $oldPermissons = $this->getPermissions($user);
        $args = func_get_args();
        for ($i = 1; $i < count($args); $i++) {
            $permission = $args[$i];
            if ($this->checkPermission($user, $permission) === true) {
                // array_push($oldPermissons, $permission);
                $index = array_search($permission, $oldPermissons);
                unset($oldPermissons[$index]);
                $oldPermissons = array_values($oldPermissons);
            }
        }

        $permissions = json_encode($oldPermissons);

        // prepare and bind
        $stmt = $this->conn->prepare("UPDATE `user` SET `permissions` = ? WHERE `user` = ?;");
        $stmt->bind_param("ss", $permissions, $user);
        $stmt->execute();
    }

    /*

    REQUEST

     */

    public function checkUsername($username)
    {

        // check if username empty
        if (empty($username)) {
            $this->throwError("Username can't be empty!");
            return false;
        }
        // check if username is too long
        else if (strlen($username) > 64) {
            $this->throwError("Username is too long!");
            return false;
        }
        // check if user already exsits
        else if ($this->checkUser($username)) {
            $this->throwError("Username already exsits!");
            return false;
        } else {
            return true;
        }
    }

    public function getUsername()
    {
        if ($this->checkUsername($this->username)) {
            return $this->username;
        }
    }
    public function getLoginUsername()
    {
        return $this->username;
    }
    public function getLoginPassword()
    {
        return $this->password0;
    }

    public function getPassword()
    {
        if ($this->checkPassword($this->password0) and $this->comparePasswords($this->password0, $this->password1)) {
            return $this->password0;
        }
    }

    public function checkPassword($password)
    {
        // check if password empty
        if (!empty($password)) {
            return true;
        } else {
            $this->throwError("Password can't be empty!");
            return false;
        }
    }

    public function comparePasswords($password0, $password1)
    {
        // check if passwords are equal
        if ($password0 === $password1) {
            return true;
        } else {
            $this->throwError("Passwords are not equal!");
            return false;
        }
    }

    public function login()
    {
        $stmt = $this->conn->prepare('SELECT password FROM user WHERE user = ?');
        $stmt->bind_param("s", $this->username);
        $stmt->execute();
        $result = $stmt->get_result();
        $output = $result->fetch_assoc();
        $_SESSION["login"] = false;
        if ($result->num_rows === 1) {
            if (password_verify($this->password0, $output["password"])) {
                $_SESSION["login"] = true;
                $_SESSION["username"] = $this->username;

                return true;
            }
        }
        return false;
    }

    public function throwError($errorMessage, $printError = true)
    {
        $error = new ERR($errorMessage);

        if ($printError) {
            header("Content-Type: application/json");
            $json = json_encode($error);
            print($json);
            exit();
        }

    }

    public function throwErrorPage($errorMessage, $errorcode = 500)
    {
        http_response_code($errorcode);
        exit($errorMessage);
    }

    public function throwSuccess()
    {
        $error = new ERR();

        header("Content-Type: application/json");
        $json = json_encode($error);
        print($json);
        exit();
    }
    /*

    LOGIN CHECKER

     */
    public function checkLogin()
    {

        if (!$_SESSION["login"]) {
            $this->throwErrorPage("<h1>Access denied</h1>", 403);
        }
    }
    public function checkLoginWithPermissions($username)
    {
        $this->checkLogin();

        $args = func_get_args();
        if (!call_user_func_array(array($this, "checkPermissions"), $args)) {
            $this->throwErrorPage("<h1>Access denied</h1>", 403);
        }
    }
    public function logout()
    {
        session_destroy();
    }
    /*

    SETTINGS

     */
    public function getSettings()
    {
        $sql = 'SELECT * FROM settings';
        $result = $this->conn->query($sql);
        $output = $result->fetch_all(MYSQLI_ASSOC);
        return $output;
    }

    public function addSetting($name, $default)
    {
        $stmt = $this->conn->prepare("INSERT INTO settings (name, value, defaultValue) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $name, $default, $default);
        $stmt->execute();
    }

    public function getSettingByName($name)
    {
        $stmt = $this->conn->prepare('SELECT value FROM settings WHERE name = ?');
        $stmt->bind_param("s", $name);
        $stmt->execute();

        $result = $stmt->get_result();
        $output = $result->fetch_assoc();

        $output = json_decode($output["value"]);
        return $output;
    }

    public function setSetting($name, $value)
    {
        $value = (int) $value;
        $stmt = $this->conn->prepare("UPDATE settings SET value=? WHERE name = ?");
        $stmt->bind_param("is", $value, $name);
        $stmt->execute();
    }
    /*

    AUTO AUTHENTICATION

     */
    public function setAuthenticationCookie()
    {
        if (isset($_SESSION["login"]) and $_SESSION["login"]) {
            $random = bin2hex(random_bytes(53));
            $uniqid = uniqid('', true);
            $uniqidPlain = str_replace(".", "", $uniqid);
            $hash = hash('sha512', $uniqidPlain . $random);
            $expiringDate = time() + 2629800;
            setcookie("pla-data", $hash, $expiringDate, "/", "", true, true);
            //Database insertion for the cookie
            $stmt = $this->conn->prepare('INSERT INTO `autoLogin` (`username`, `cookie`, `login`, `expires`) VALUES (?, ?, ?, ?) '); # weiter mit timestamp
            $login = true;
            $stmt->bind_param("ssii", $_SESSION["username"], $hash, $login, $expiringDate);
            $stmt->execute();
        }
    }

    public function checkAuthenticationCookie()
    {

    }

    public function getUsernameByCookie($cookie)
    {
        $stmt = $this->conn->prepare('SELECT login FROM autoLogin WHERE cookie = ?');
        $stmt->bind_param("s", $cookie);
        $stmt->execute();

        $result = $stmt->get_result();
        $output = $result->fetch_assoc();

        $output = json_decode($output["username"]);
        return $output;
    }
}

$login = new LOGIN();
