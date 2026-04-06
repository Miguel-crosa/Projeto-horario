<?php
require_once __DIR__ . '/../configs/db.php';
$res = mysqli_query($conn, "SELECT id, name, date, end_date FROM holidays ORDER BY id ASC LIMIT 20");
echo "ID | NAME | DATE | END_DATE\n";
echo "---------------------------\n";
while($row = mysqli_fetch_assoc($res)) {
    echo "{$row['id']} | {$row['name']} | {$row['date']} | {$row['end_date']}\n";
}
?>
