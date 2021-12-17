<?php
require_once(__DIR__ . "/../../partials/nav.php");
is_logged_in(true);
$db = getDB();
//handle join
if (isset($_POST["join"])) {
    $user_id = get_user_id();
    $comp_id = se($_POST, "comp_id", 0, false);
    $fee = se($_POST, "join_fee", 0, false);
    $points = get_user_points();
    join_competition($comp_id, $user_id, $fee);
}
 $per_page = 5;
 paginate("SELECT count(1) as total FROM Competitions WHERE expires > current_timestamp() AND paid_out < 1 AND paid_out < 1");
//handle page load
//TODO fix join
$stmt = $db->prepare("SELECT Competitions.id, name, min_participants, current_participants, 
join_fee, starting_reward, current_reward, paid_out, duration, expires, min_score, IF(comp_id is null, 0, 1) as joined,
CONCAT(first_place_per,'% - ', second_place_per, '% - ', third_place_per, '%') as place FROM Competitions
LEFT JOIN (SELECT * FROM CompetitionParticipants WHERE user_id = :uid) as uc ON 
uc.comp_id = Competitions.id WHERE expires > current_timestamp() AND paid_out < 1 ORDER BY expires desc");
$results = [];
try {
    $stmt->execute([":uid" => get_user_id()]);
    $r = $stmt->fetchAll();
    if ($r) {
        $results = $r;
    }
} catch (PDOException $e) {
    flash("There was a problem fetching competitions, please try again later", "danger");
    error_log("List competitions error: " . var_export($e, true));
}
?>
<div class="container-fluid">
    <h1>List Competitions</h1>
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
            <th>Reward</th>
            <th>Min Score</th>
            <th>Expires</th>
            <th>Actions</th>
        </thead>
        <tbody>
            <?php if (count($results) > 0) : ?>
                <?php foreach ($results as $row) : ?>
                    <tr>
                        <td><?php se($row, "name"); ?></td>
                        <td><?php se($row, "current_participants"); ?>/<?php se($row, "min_participants"); ?></td>
                        <td><?php se($row, "current_reward"); ?><br>Payout: <?php se($row, "place", "-"); ?></td>
                        <td><?php se($row, "min_score"); ?></td>
                        <td><?php se($row, "expires", "-"); ?></td>
                        <td>
                            <?php if (se($row, "joined", 0, false)) : ?>
                                <button class="btn btn-primary disabled" onclick="event.preventDefault()" disabled>Already Joined</button>
                            <?php else : ?>
                                <form method="POST">
                                    <input type="hidden" name="comp_id" value="<?php se($row, 'id'); ?>" />
                                    <input type="hidden" name="cost" value="<?php se($row, 'join_fee', 0); ?>" />
                                    <input type="submit" name="join" class="btn btn-primary" value="Join (Fee: <?php se($row, "join_fee", 0) ?>)" />
                                </form>
                            <?php endif; ?>
                            <a class="btn btn-secondary" href="view_competition.php?id=<?php se($row, 'id'); ?>">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="100%">No active competitions</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>