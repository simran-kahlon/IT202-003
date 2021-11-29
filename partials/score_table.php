<?php
require_once(__DIR__ . "/../lib/functions.php");
if (!isset($duration)) {
    $duration = "day"; //choosing to default to day
}
$results = get_top_10($duration);

switch ($duration) {
    case "week":
        $title = "Top Scores This Week";
        break;
    case "month":
        $title = "Top Scores This Month";
        break;
    case "lifetime":
        $title = "All Time Top Scores";
        break;
    default:
        $title = "Invalid Scoreboard";
        break;
}
?>
<div class="card bg-dark">
    <div class="card-body">
        <style>
            .card-title{
                background-color: rgb(137, 187, 221);
                font-size: 24px; 
                font-weight: bold;
                padding: 10px;
            }
        </style>
        <div class="card-title">
            <div class="fw-bold fs-3">
                <?php se($title); ?>
            </div>
        </div>
        <div class="card-text">
            <style>
                table {
                    width: 100%;
                    text-align: center;
                    background-color: rgb(137, 187, 221);
                    padding: 10px;
                }

                th,td {
                    border-bottom: 1px solid black;
                    padding: 8px;
                }
                th {
                    font-weight: bold; 
                    font-size: 18px;
                }
            </style>
            <table class="table text-light">
                <thead>
                    <th>User</th>
                    <th>Score</th>
                    <th>Achieved</th>
                </thead>
                <tbody>
                    <?php if (!$results || count($results) == 0) : ?>
                        <tr>
                            <td colspan="100%">No scores available</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($results as $result) : ?>
                            <tr>
                                <td>
                                    <!--<a href="profile.php?id=<?php se($result, 'user_id'); ?>"><?php se($result, "username"); ?></a>-->
                                    <?php se($result, "username"); ?>
                                </td>
                                <td><?php se($result, "score"); ?></td>
                                <td><?php se($result, "created"); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>