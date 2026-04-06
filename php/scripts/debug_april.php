<?php
require_once __DIR__ . '/../configs/db.php';
$res = mysqli_query($conn, "SELECT id, name, date, end_date FROM holidays WHERE date LIKE '2026-04-%' OR end_date LIKE '2026-04-%'");
echo "ID | NAME | DATE | END_DATE\n";
echo "---------------------------\n";
while($row = mysqli_fetch_assoc($res)) {
    echo "{$row['id']} | " . substr($row['name'], 0, 15) . " | {$row['date']} | {$row['end_date']}\n";
}
?>
