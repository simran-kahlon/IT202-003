<?php require(__DIR__ . "/../../partials/nav.php"); ?>
<?php
if (!is_logged_in()) {
    //this will redirect to login and kill the rest of this script (prevent it from executing)
    flash("You don't have permission to access this page");
    die(header("Location: login.php"));
}
?>
<?php
$db = getDB();

$stmt = $db->prepare("SELECT c.*, UC.user_id as reg, CONCAT(first_place_per,'% - ', second_place_per, '% - ', third_place_per, '%') as place FROM Competitions c LEFT JOIN (SELECT * FROM CompetitionParticipants where user_id = :id) 
as UC on c.id = UC.comp_id WHERE (UC.user_id = :id) ORDER BY expires ASC");
$r = $stmt->execute([":id" => get_user_id(),]);
if ($r) {
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    flash("There was a problem looking up competitions: " . var_export($stmt->errorInfo(), true), "danger");
}

$records_per_page =10;
$query = "SELECT count(1) as total FROM Competitions";
paginate($query, [], $records_per_page);

$base_query = "SELECT c.*, UC.user_id as reg, CONCAT(round(first_place_per*100),'% - ', round(second_place_per*100), '% - ', round(third_place_per*100), '%') as place FROM Competitions c LEFT JOIN (SELECT * FROM CompetitionParticipants where user_id = :id) 
as UC on c.id = UC.comp_id WHERE  (UC.user_id = :id) ORDER BY expires desc";
$query = " LIMIT :offset, :limit";
$params[":offset"] = $offset;
$params[":limit"] = $records_per_page;
$params[":id"] = get_user_id();
$stmt = $db->prepare($base_query. $query);
$results = [];
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $params = null; //set it to null to avoid issues
    
    try {
        $stmt->execute($params); //dynamically populated params to bind
        $r = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($r) {
            $results = $r;
        }
    } catch (PDOException $e) {
        flash("<pre>" . var_export($e, true) . "</pre>");
    } 

?>
<div class="container-fluid">
    <h3>My Competitions</h3> <?php if (isset($results) && count($results)) : ?>
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
                <th>Name</th>
                <th>Participants</th>
                <th>Required Score</th>
                <th>Reward</th>
                <th>Expires</th>
                <th>Actions</th>
            </thead>
            
            <tbody>
                <?php foreach ($results as $r) : ?>
                    <tr>
                    <td><?php se($r, "name"); ?></td>
                    <!-- <?php if ($r["user_id"] == get_user_id()) : ?>
                                    (Created)
                                <?php endif; ?> -->
                    <td><?php se($r, "current_participants"); ?>/<?php se($r, "min_participants"); ?></td>
                    <td><?php echo ($r["min_score"]); ?></td>
                    <td><?php se($r, "current_reward"); ?><br>Payout: <?php se($r, "place", "-"); ?></td>
                    <!--TODO show payout-->
                    <td><?php echo ($r["expires"]); ?></td>
                    <td>
                        <?php if ($r["reg"] != get_user_id()) : ?>
                            <form method="POST">
                                <input type="hidden" name="cid" value="<?php echo ($r["id"]); ?>" />
                                <input type="submit" name="join" class="btn btn-primary" value="Join (Cost: <?php echo ($r["fee"]); ?>)" />
                            </form>
                        <?php else : ?>
                            Already Registered
                        <?php endif; ?>
                    </td>
                        </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="100%">No competitions available right now</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
</div>
<?php include(__DIR__ . "/../../partials/pagination.php"); ?>
<?php
require_once(__DIR__ . "/../../partials/flash.php");
?>