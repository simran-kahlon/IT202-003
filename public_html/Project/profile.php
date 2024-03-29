<?php
require_once(__DIR__ . "/../../partials/nav.php");
//is_logged_in(true);
$user_id = se($_GET, "id", get_user_id(), false);
error_log("user id $user_id");
$isMe = $user_id === get_user_id();
$edit = !!se($_GET, "edit", false, false);
if ($user_id < 1) {
    flash("Invalid user", "danger");
    redirect("home.php");
}
?>
<?php
if (isset($_POST["save"]) && $isMe && $edit) {
    //$db = getDB();
    $email = se($_POST, "email", null, false);
    $username = se($_POST, "username", null, false);
    $visibility = !!se($_POST, "visibility", false, false) ? 1 : 0;
    $email = sanitize_email($email);
    $params = [":email" => $email, ":username" => $username, ":id" => get_user_id(), ":vis" => $visibility];
    $db = getDB();
    $stmt = $db->prepare("UPDATE Users set email = :email, username = :username, visibility = :vis where id = :id");
    try {
        $stmt->execute($params);
    } catch (Exception $e) {
        users_check_duplicate($e->errorInfo);
    }
    //select fresh data from table
    $stmt = $db->prepare("SELECT id, email, IFNULL(username, email) as `username` from Users where id = :id LIMIT 1");
    try {
        $stmt->execute([":id" => get_user_id()]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            //$_SESSION["user"] = $user;
            $_SESSION["user"]["email"] = $user["email"];
            $_SESSION["user"]["username"] = $user["username"];
        } else {
            flash("User doesn't exist", "danger");
        }
    } catch (Exception $e) {
        flash("An unexpected error occurred, please try again", "danger");
        //echo "<pre>" . var_export($e->errorInfo, true) . "</pre>";
    }


    //check/update password
    $current_password = se($_POST, "currentPassword", null, false);
    $new_password = se($_POST, "newPassword", null, false);
    $confirm_password = se($_POST, "confirmPassword", null, false);
    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        if ($new_password === $confirm_password) {
            //TODO validate current
            $stmt = $db->prepare("SELECT password from Users where id = :id");
            try {
                $stmt->execute([":id" => get_user_id()]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if (isset($result["password"])) {
                    if (password_verify($current_password, $result["password"])) {
                        $query = "UPDATE Users set password = :password where id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            ":id" => get_user_id(),
                            ":password" => password_hash($new_password, PASSWORD_BCRYPT)
                        ]);

                        flash("Password reset", "success");
                    } else {
                        flash("Current password is invalid", "warning");
                    }
                }
            } catch (Exception $e) {
                echo "<pre>" . var_export($e->errorInfo, true) . "</pre>";
            }
        } else {
            flash("New passwords don't match", "warning");
        }
    }
}
?>

<?php
$email = get_user_email();
$username = get_username();
//$user_id = get_user_id();
$points = get_user_points();
$created =  "";
$public = false;
$db = getDB();
$stmt = $db->prepare("SELECT username, created, visibility from Users where id = :id");
try {
    $stmt->execute([":id" => $user_id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("user: " . var_export($r, true));
    $username = se($r, "username", "", false);
    $created = se($r, "created", "", false);
    $public = se($r, "visibility", 0, false) > 0;
    if (!$public && !$isMe) {
        flash("User's profile is private", "warning");
        redirect("home.php");
        //die(header("Location: home.php"));
    }
} catch (Exception $e) {
    echo "<pre>" . var_export($e->errorInfo, true) . "</pre>";
}
?>
<div class="container-fluid">
    <h1>Profile</h1>
    <?php if ($isMe) : ?>
        <?php if ($edit) : ?>
            <a class="btn btn-primary" href="?">View</a>
        <?php else : ?>
            <a class="btn btn-primary" href="?edit=true">Edit</a>
        <?php endif; ?>
    <?php endif; ?>
    <br>
    <br>
    <?php if ($isMe)
        echo ("Points: $points ");
    ?>
    <br>
    <?php if (!$edit) : ?>
        <br>
        <div>Username: <?php se($username); ?></div>
        <div>Joined: <?php se($created); ?></div>
        <!-- TODO any other public info -->
        <div>
            <?php
            $records_per_page = 10;
            $query = "SELECT count(1) as total FROM BGD_Scores";
            $stmt->execute([":id" => $user_id]);
            paginate($query, [], $records_per_page);

            $base_query = "SELECT score, created from BGD_Scores where user_id = :id ORDER BY created desc";
            $query = " LIMIT :offset, :limit";
            $params[":offset"] = $offset;
            $params[":limit"] = $records_per_page;
            $params[":id"] = $user_id;
            $stmt = $db->prepare($base_query . $query);
            $scores = [];
            foreach ($params as $key => $value) {
                $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $type);
            }
            $params = null; //set it to null to avoid issues

            try {
                $stmt->execute($params); //dynamically populated params to bind
                $score = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if ($score) {
                    $scores = $score;
                }
            } catch (PDOException $e) {
                error_log("Error getting latest scores for user $user_id: " . var_export($e->errorInfo, true));
            }
            ?>
            <br>

            <h1>Score History</h1>
            <style>
                table {
                    border: 1px solid black;
                    width: 50%;
                    text-align: center;
                }

                th,
                td {
                    border-bottom: 1px solid black;
                    border-right: 1px solid black;
                }

                tr:hover {
                    background-color: rgb(157, 115, 236);
                }

                th {
                    background-color: rebeccapurple;
                    color: white;
                }
            </style>
            <table class="table text-light">
                <thead>
                    <th>Score</th>
                    <th>Time</th>
                </thead>
                <tbody>
                <?php if (count($scores) > 0) : ?>
                    <?php foreach ($scores as $score) : ?>
                        <tr>
                            <td><?php se($score, "score", 0); ?></td>
                            <td><?php se($score, "created", "-"); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="100%">No scores to show</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <li class="page-item <?php echo ($page - 1) < 1 ? "disabled" : ""; ?>">
                        <a class="page-link" href="?<?php se(persistQueryString($page - 1)); ?>" tabindex="-1">Previous</a>
                    </li>
                    <?php for ($i = 0; $i < $total_pages; $i++) : ?>
                        <li class="page-item <?php echo ($page - 1) == $i ? "active" : ""; ?>"><a class="page-link" href="?<?php se(persistQueryString($i + 1)); ?>"><?php echo ($i + 1); ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page) >= $total_pages ? "disabled" : ""; ?>">
                        <a class="page-link" href="?<?php se(persistQueryString($page + 1)); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
    <br>
    <?php if ($isMe && $edit) : ?>
        <details>
            <summary> Update Profile </summary>
            <form method="POST" onsubmit="return validate(this);">
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input name="visibility" class="form-check-input" type="checkbox" id="flexSwitchCheckDefault" <?php if ($public) echo "checked"; ?>>
                        <label class="form-check-label" for="flexSwitchCheckDefault">Make Profile Public</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" value="<?php se($email); ?>" />
                </div>
                <div class="mb-3">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" value="<?php se($username); ?>" />
                </div>
                <!-- DO NOT PRELOAD PASSWORD -->
                <div>Password Reset</div>
                <div class="mb-3">
                    <label for="cp">Current Password</label>
                    <input type="password" name="currentPassword" id="cp" />
                </div>
                <div class="mb-3">
                    <label for="np">New Password</label>
                    <input type="password" name="newPassword" id="np" />
                </div>
                <div class="mb-3">
                    <label for="conp">Confirm Password</label>
                    <input type="password" name="confirmPassword" id="conp" />
                </div>
                <input type="submit" value="Update Profile" name="save" />
            </form>

        <?php endif; ?>

        <script>
            function validate(form) {
                let pw = form.newPassword.value;
                let con = form.confirmPassword.value;
                let isValid = true;
                //TODO add other client side validation....

                //example of using flash via javascript
                //find the flash container, create a new element, appendChild
                if (pw !== con) {
                    //find the container
                    /*let flash = document.getElementById("flash");
                    //create a div (or whatever wrapper we want)
                    let outerDiv = document.createElement("div");
                    outerDiv.className = "row justify-content-center";
                    let innerDiv = document.createElement("div");
                    //apply the CSS (these are bootstrap classes which we'll learn later)
                    innerDiv.className = "alert alert-warning";
                    //set the content
                    innerDiv.innerText = "Password and Confirm password must match";
                    outerDiv.appendChild(innerDiv);
                    //add the element to the DOM (if we don't it merely exists in memory)
                    flash.appendChild(outerDiv);*/
                    flash("Password and Confirm password must match", "warning");
                    isValid = false;
                }
                return isValid;
            }
        </script>
        <?php
        require_once(__DIR__ . "/../../partials/flash.php");
        ?>