<?php
require_once __DIR__ . '/../configs/db.php';
$res = mysqli_query($conn, "SELECT id, date, end_date FROM holidays ORDER BY date ASC, id ASC LIMIT 30");
echo "ID | DATE | END_DATE\n";
echo "--------------------\n";
while($row = mysqli_fetch_assoc($res)) {
    echo "{$row['id']} | {$row['date']} | {$row['end_date']}\n";
}
?>
