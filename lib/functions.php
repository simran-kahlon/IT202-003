<?php
//TODO 1: require db.php
require_once(__DIR__ . "/db.php");
$BASE_PATH = '/Project/'; //This is going to be a helper for redirecting to our base project path since it's nested in another folder
/** Safe Echo Function
 * Takes in a value and passes it through htmlspecialchars()
 * or
 * Takes an array, a key, and default value and will return the value from the array if the key exists or the default value.
 * Can pass a flag to determine if the value will immediately echo or just return so it can be set to a variable
 */
function se($v, $k = null, $default = "", $isEcho = true)
{
    if (is_array($v) && isset($k) && isset($v[$k])) {
        $returnValue = $v[$k];
    } else if (is_object($v) && isset($k) && isset($v->$k)) {
        $returnValue = $v->$k;
    } else {
        $returnValue = $v;
        //added 07-05-2021 to fix case where $k of $v isn't set
        //this is to kep htmlspecialchars happy
        if (is_array($returnValue) || is_object($returnValue)) {
            $returnValue = $default;
        }
    }
    if (!isset($returnValue)) {
        $returnValue = $default;
    }
    if ($isEcho) {
        //https://www.php.net/manual/en/function.htmlspecialchars.php
        echo htmlspecialchars($returnValue, ENT_QUOTES);
    } else {
        //https://www.php.net/manual/en/function.htmlspecialchars.php
        return htmlspecialchars($returnValue, ENT_QUOTES);
    }
}
//TODO 2: filter helpers
function sanitize_email($email = "")
{
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}
function is_valid_email($email = "")
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
}
//TODO 3: User Helpers
function is_logged_in($redirect = false, $destination = "login.php")
{
    $isLoggedIn = isset($_SESSION["user"]);
    if ($redirect && !$isLoggedIn) {
        flash("You must be logged in to view this page", "warning");
        die(header("Location: $destination"));
    }
    return $isLoggedIn; //se($_SESSION, "user", false, false);
}
function has_role($role)
{
    if (is_logged_in() && isset($_SESSION["user"]["roles"])) {
        foreach ($_SESSION["user"]["roles"] as $r) {
            if ($r["name"] === $role) {
                return true;
            }
        }
    }
    return false;
}
function get_username()
{
    if (is_logged_in()) { //we need to check for login first because "user" key may not exist
        return se($_SESSION["user"], "username", "", false);
    }
    return "";
}
function get_user_email()
{
    if (is_logged_in()) { //we need to check for login first because "user" key may not exist
        return se($_SESSION["user"], "email", "", false);
    }
    return "";
}
function get_user_id()
{
    if (is_logged_in()) { //we need to check for login first because "user" key may not exist
        return se($_SESSION["user"], "id", false, false);
    }
    return false;
}
function get_user_points()
{
    if (is_logged_in()) {
        return (int)se($_SESSION["user"], "points", 0, false);
    }
    return 0;
}
//TODO 4: Flash Message Helpers
function flash($msg = "", $color = "info")
{
    $message = ["text" => $msg, "color" => $color];
    if (isset($_SESSION['flash'])) {
        array_push($_SESSION['flash'], $message);
    } else {
        $_SESSION['flash'] = array();
        array_push($_SESSION['flash'], $message);
    }
}

function getMessages()
{
    if (isset($_SESSION['flash'])) {
        $flashes = $_SESSION['flash'];
        $_SESSION['flash'] = array();
        return $flashes;
    }
    return array();
}
//TODO generic helpers
function reset_session()
{
    session_unset();
    session_destroy();
    session_start();
}
function users_check_duplicate($errorInfo)
{
    if ($errorInfo[1] === 1062) {
        //https://www.php.net/manual/en/function.preg-match.php
        preg_match("/Users.(\w+)/", $errorInfo[2], $matches);
        if (isset($matches[1])) {
            flash("The chosen " . $matches[1] . " is not available.", "warning");
        } else {
            //TODO come up with a nice error message
            flash("<pre>" . var_export($errorInfo, true) . "</pre>");
        }
    } else {
        //TODO come up with a nice error message
        flash("<pre>" . var_export($errorInfo, true) . "</pre>");
    }
}
function get_url($dest)
{
    global $BASE_PATH;
    if (str_starts_with($dest, "/")) {
        //handle absolute path
        return $dest;
    }
    //handle relative path
    return $BASE_PATH . $dest;
}

function save_score($score, $user_id, $showFlash = false)
{
    if ($user_id < 1) {
        flash("Error saving score, you may not be logged in", "warning");
        return;
    }
    if ($score <= 0) {
        flash("Scores of zero are not recorded", "warning");
        return;
    }
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO BGD_Scores (score, user_id) VALUES (:score, :uid)");
    try {
        $stmt->execute([":score" => $score, ":uid" => $user_id]);
        if ($showFlash) {
            flash("Saved score of $score", "success");
        }
    } catch (PDOException $e) {
        flash("Error saving score: " . var_export($e->errorInfo, true), "danger");
    }
}

function paginate($query, $params = [], $per_page = 10)
{
    global $page; //will be available after function is called
    try {
        $page = (int)se($_GET, "page", 1, false);
    } catch (Exception $e) {
        //safety for if page is received as not a number
        $page = 1;
    }
    $db = getDB();
    $stmt = $db->prepare($query);
    try {
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("paginate error: " . var_export($e, true));
    }
    $total = 0;
    if (isset($result)) {
        $total = (int)se($result, "total", 0, false);
    }
    global $total_pages; //will be available after function is called
    $total_pages = ceil($total / $per_page);
    global $offset; //will be available after function is called
    $offset = ($page - 1) * $per_page;
}
//updates or inserts page into query string while persisting anything already present
function persistQueryString($page)
{
    $_GET["page"] = $page;
    return http_build_query($_GET);
}

function get_latest_scores($user_id, $limit = 10)
{
    /*     if ($limit < 1 || $limit > 50) {
        $limit = 10;
    } */
    $query = "SELECT score, created from BGD_Scores where user_id = :id ORDER BY created desc LIMIT :limit";
    $db = getDB();
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $stmt = $db->prepare($query);
    try {
        $stmt->execute([":id" => $user_id, ":limit" => $limit]);
        $r = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($r) {
            return $r;
        }
    } catch (PDOException $e) {
        error_log("Error getting latest $limit scores for user $user_id: " . var_export($e->errorInfo, true));
    }
    return [];
}

function get_top_10($duration = "week")
{
    $d = "week";
    if (in_array($duration, ["week", "month", "lifetime"])) {
        //variable is safe
        $d = $duration;
    }
    $db = getDB();
    $query = "SELECT user_id,username, score, BGD_Scores.created from BGD_Scores join Users on BGD_Scores.user_id = Users.id";
    if ($d !== "lifetime") {
        //be very careful passing in a variable directly to SQL, I ensure it's a specific value from the in_array() above
        $query .= " WHERE BGD_Scores.created >= DATE_SUB(NOW(), INTERVAL 1 $d)";
    }
    //remember to prefix any ambiguous columns (Users and Scores both have created)
    $query .= " ORDER BY score Desc, BGD_Scores.created desc LIMIT 10"; //newest of the same score is ranked higher
    error_log($query);
    $stmt = $db->prepare($query);
    $results = [];
    try {
        $stmt->execute();
        $r = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($r) {
            $results = $r;
        }
    } catch (PDOException $e) {
        error_log("Error fetching scores for $d: " . var_export($e->errorInfo, true));
    }
    return $results;
}
function refresh_points($user_id)
{
    if (is_logged_in()) {
        $query = "UPDATE Users set points = (SELECT IFNULL(SUM(point_change),0) from PointsHistory WHERE user_id = :uid) where id = :uid";
        $db = getDB();
        $stmt = $db->prepare($query);
        $stmt->execute([":uid" => $user_id]);
        if ($user_id == get_user_id()) {
            $query = "SELECT points from Users where id = :uid";
            $db = getDB();
            $stmt = $db->prepare($query);
            $stmt->execute([":uid" => $user_id]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                $_SESSION["user"]["points"] = se($r, "points", 0, false);
            }
        }
    }
}

function change_points($points, $reason, $user_id)
{
    if ($points != 0) {
        $query = "INSERT INTO PointsHistory (user_id, point_change, reason)
            VALUES(:uid, :pc, :r)";
        $params[":uid"] = $user_id;
        $params[":r"] = $reason;
        $params[":pc"] = ($points);
        $db = getDB();
        $stmt = $db->prepare($query);
        try {
            $stmt->execute($params);
            refresh_points($user_id);
            return true;
        } catch (PDOException $e) {
            flash("Could not change points: " . var_export($e->errorInfo, true), "danger");
        }
    }
}
function redirect($path)
{ //header headache
    //https://www.php.net/manual/en/function.headers-sent.php#90160
    /*headers are sent at the end of script execution otherwise they are sent when the buffer reaches it's limit and emptied */
    if (!headers_sent()) {
        //php redirect
        die(header("Location: " . get_url($path)));
    }
    //javascript redirect
    echo "<script>window.location.href='" . get_url($path) . "';</script>";
    //metadata redirect (runs if javascript is disabled)
    echo "<noscript><meta http-equiv=\"refresh\" content=\"0;url=" . get_url($path) . "\"/></noscript>";
    die();
}
function update_participants($competition_id)
{
    $db = getDB();
    $stmt = $db->prepare("UPDATE Competitions set current_participants = (SELECT IFNULL(COUNT(1),0)
     FROM CompetitionParticipants WHERE comp_id = :cid), 
    current_reward = IF(join_fee > 0, current_reward + CEILING(join_fee * 0.5), current_reward) WHERE id = :cid");
    try {
        $stmt->execute([":cid" => $competition_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Update competition participant error: " . var_export($e, true));
    }
    return false;
}
function add_to_competition($comp_id, $user_id)
{
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO CompetitionParticipants (user_id, comp_id) VALUES (:uid, :cid)");
    try {
        $stmt->execute([":uid" => $user_id, ":cid" => $comp_id]);
        update_participants($comp_id);
        return true;
    } catch (PDOException $e) {
        error_log("Join Competition error: " . var_export($e, true));
    }
    return false;
}
function join_competition($comp_id, $user_id, $cost)
{
    $points = get_user_points();
    if ($comp_id > 0) {
        if ($points >= $cost) {
            $db = getDB();
            $stmt = $db->prepare("SELECT name, join_fee from Competitions where id = :id");
            try {
                $stmt->execute([":id" => $comp_id]);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($r) {
                    $cost = (int)se($r, "join_fee", 0, false);
                    $name = se($r, "name", "", false);
                    if ($points >= $cost) {
                        if (change_points(-$cost, "join-comp", get_user_id())) {
                            if (add_to_competition($comp_id, $user_id)) {
                                flash("Successfully joined $name", "success");
                            }
                        } else {
                            flash("Failed to pay for competition", "danger");
                        }
                    } else {
                        flash("You can't afford to join this competition", "warning");
                    }
                }
            } catch (PDOException $e) {
                error_log("Comp lookup error " . var_export($e, true));
                flash("There was an error looking up the competition", "danger");
            }
        } else {
            flash("You can't afford to join this competition", "warning");
        }
    } else {
        flash("Invalid competition, please try again", "danger");
    }
}
function get_top_scores_for_comp($comp_id, $limit = 10)
{
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM (SELECT s.user_id, s.score,s.created, a.id as 
    user_id, DENSE_RANK() OVER (PARTITION BY s.user_id ORDER BY s.score desc) as `rank` FROM BGD_Scores s
    JOIN CompetitionParticipants uc on uc.user_id = s.user_id
    JOIN Competitions c on uc.comp_id = c.id
    WHERE c.id = :cid AND s.created BETWEEN uc.created AND c.expires
    )as t where `rank` = 1 ORDER BY score desc LIMIT :limit");
    $scores = [];
    try {
        $stmt->bindValue(":cid", $comp_id, PDO::PARAM_INT);
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        $r = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($r) {
            $scores = $r;
        }
    } catch (PDOException $e) {
        flash("There was a problem fetching scores, please try again later", "danger");
        error_log("List competition scores error: " . var_export($e, true));
    }
    return $scores;
}
function elog($data)
{
    echo "<br>" . var_export($data, true) . "<br>";
    error_log(var_export($data, true));
}
function calc_winners()
{
    $db = getDB();
    elog("Starting winner calc");
    $calced_comps = [];
    $stmt = $db->prepare("SELECT c.id,c.name, first_place_per, second_place_per, third_place_per, current_reward 
    from Competitions c where expires <= CURRENT_TIMESTAMP() 
    AND paid_out = 0 AND current_participants >= min_participants LIMIT 10");
    try {
        $stmt->execute();
        $r = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($r) {
            $rc = $stmt->rowCount();
            elog("Validating $rc comps");
            foreach ($r as $row) {
                $fp = floatval(se($row, "first_place_per", 0, false) / 100);
                $sp = floatval(se($row, "second_place_per", 0, false) / 100);
                $tp = floatval(se($row, "third_place_per", 0, false) / 100);
                $reward = (int)se($row, "current_reward", 0, false);
                $name = se($row, "name", "-", false);
                $fpr = ceil($reward * $fp);
                $spr = ceil($reward * $sp);
                $tpr = ceil($reward * $tp);
                $competition_id = se($row, "id", -1, false);

                try {
                    $r = get_top_scores_for_comp($competition_id, 3);
                    if ($r) {
                        $atleastOne = false;
                        foreach ($r as $index => $row) {
                            $score = se($row, "score", 0, false);
                            $user_id = se($row, "user_id", -1, false);
                            if ($index == 0) {
                                if (change_points($fpr, "won-comp", $user_id)) {
                                    $atleastOne = true;
                                }
                                elog("User $user_id First place in $name with score of $score");
                            } else if ($index == 1) {
                                if (change_points($spr, "won-comp", $user_id)) {
                                    $atleastOne = true;
                                }
                                elog("User $user_id Second place in $name with score of $score");
                            } else if ($index == 2) {
                                if (change_points($tpr, "won-comp", $user_id)) {
                                    $atleastOne = true;
                                }
                                elog("User $user_id Third place in $name with score of $score");
                            }
                        }
                        if ($atleastOne) {
                            array_push($calced_comps, $competition_id);
                        }
                    } else {
                        elog("No eligible scores");
                    }
                } catch (PDOException $e) {
                    error_log("Getting winners error: " . var_export($e, true));
                }
            }
        } else {
            elog("No competitions ready");
        }
    } catch (PDOException $e) {
        error_log("Getting Expired Comps error: " . var_export($e, true));
    }
    //closing calced comps
    if (count($calced_comps) > 0) {
        $query = "UPDATE Competitions set paid_out = 1 WHERE id in ";
        $query = "(" . str_repeat("?,", count($calced_comps) - 1) . "?)";
        elog("Close query: $query");
        $stmt = $db->prepare($query);
        try {
            $stmt->execute($calced_comps);
            $updated = $stmt->rowCount();
            elog("Marked $updated comps complete and calced");
        } catch (PDOException $e) {
            error_log("Closing valid comps error: " . var_export($e, true));
        }
    } else {
        elog("No competitions to calc");
    }
    //close invalid comps
    $stmt = $db->prepare("UPDATE Competitions set paid_out = 1 WHERE expires <= CURRENT_TIMESTAMP() AND current_participants < min_participants AND paid_out = 0");
    try {
        $stmt->execute();
        $rows = $stmt->rowCount();
        elog("Closed $rows invalid competitions");
    } catch (PDOException $e) {
        error_log("Closing invalid comps error: " . var_export($e, true));
    }
    elog("Done calc winners");
}
