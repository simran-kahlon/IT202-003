<?php require(__DIR__ . "/../../partials/nav.php"); ?>
<?php
if (!is_logged_in()) {
    //this will redirect to login and kill the rest of this script (prevent it from executing)
    flash("You don't have permission to access this page");
    die(header("Location: login.php"));
}
?>
<?php
if (isset($_POST["name"])) {

    $cost = (int)$_POST["reward"];
    if ($cost <= 0) {
        $cost = 0;
    }
    $cost++;
    //TODO other validation
    $points = get_user_points();
    if ($cost > $points) {
        flash("You can't afford to create this competition", "warning");
    } else {
        change_points(-$cost, "create_comp", get_user_id());

        $db = getDB();
        $expires = new DateTime();
        $days = (int)$_POST["duration"];
        $expires->add(new DateInterval("P" . $days . "D"));
        $expires = $expires->format("Y-m-d H:i:s");
        $query = "INSERT INTO Competitions (name, duration, expires, join_fee, min_participants, min_score, 
        first_place_per, second_place_per, third_place_per, cost_to_create, starting_reward) 
        VALUES(:name, :duration, :expires, :fee, :participants, :min_score, :fp, :sp, :tp, :cost, :reward)";
        $stmt = $db->prepare($query);
        $params = [
            ":name" => $_POST["name"],
            ":duration" => $days,
            ":expires" => $expires,
            ":fee" => $_POST["fee"],
            ":participants" => $_POST["min_participants"],
            ":min_score" => $_POST["min_score"],
            ":cost" => $cost,
            ":reward" => $_POST["reward"]
        ];
        switch ((int)$_POST["split"]) {
                /* case 0:
                 break;  using default for this*/
            case 1:
                $params[":fp"] = .8;
                $params[":sp"] = .2;
                $params[":tp"] = 0;
                break;
            case 2:
                $params[":fp"] = .7;
                $params[":sp"] = .3;
                $params[":tp"] = 0;
                break;
            case 3:
                $params[":fp"] = .7;
                $params[":sp"] = .2;
                $params[":tp"] = .1;
                break;
            case 4:
                $params[":fp"] = .6;
                $params[":sp"] = .3;
                $params[":tp"] = .1;
                break;
            default:
                $params[":fp"] = 1;
                $params[":sp"] = 0;
                $params[":tp"] = 0;
                break;
        }

         if ((int)$_POST["min_participants"] < 3) {
            flash("Minimum Participants should be at least 3", "warning");
        }  
         if ((int)$_POST["reward"] < 1) {
            flash("Minimum reward must be at least 1", "warning");
        }
        if ((int)$_POST["fee"] < 0) {
            flash("Entry fee must be 0 or greater than 0", "warning");
        } 
         else {
                try { 
                    $r = $stmt->execute($params);
                    if ($r) {
                        flash("Successfully created competition", "success");
                        $competition_id =  $db->lastInsertId();
                        add_to_competition($competition_id, get_user_id());
                        //join_competition($competition_id, get_user_id(),0);
                        die(header("Location: #"));
                    } else {
                        flash("There was a problem creating a competition: " . var_export($stmt->errorInfo(), true), "danger");
                    }
                 } catch (PDOException $e) {
                    error_log("Error" . var_export($e->errorInfo, true));
                }
            } 
        }
    }

?>
<div class="container-fluid">
    <h3>Create Competition</h3>
    <form method="POST">
        <div class="form-group">
            <label for="name">Name</label>
            <input id="name" name="name" type = "text" class="form-control" required/>
        </div>
        <div class="form-group">
            <label for="d">Duration (in days)</label>
            <input id="d" name="duration" type="number" class="form-control" required/>
        </div>
        <div class="form-group">
            <label for="s">Minimum Required Score</label>
            <input id="s" name="min_score" type="number" class="form-control" required/>
        </div>
        <div class="form-group">
            <label for="p">Minimum Participants</label>
            <input id="p" name="min_participants" type="number" class="form-control" required/>
        </div>
        <div class="form-group">
            <label for="r">Reward Split (First, Second, Third)</label>
            <select id="r" name="split" type="number" class="form-control">
                <option value="0">100%</option>
                <option value="1">80%/20%</option>
                <option value="2">70%/30%</option>
                <option value="3">70%/20%/10%</option>
                <option value="4">60%/30%/10%</option>
            </select>
        </div>
        <div class="form-group">
            <label for="rw">Reward</label>
            <input id="rw" name="reward" type="number"  class="form-control" required/>
        </div>
        <div class="form-group">
            <label for="f">Entry Fee</label>
            <input id="f" name="fee" type="number" class="form-control" required/>
        </div>
        <input type="submit" class="btn btn-success" value="Create (Cost: 1)" />
    </form>
</div>
<?php require(__DIR__ . "/../../partials/flash.php"); ?>