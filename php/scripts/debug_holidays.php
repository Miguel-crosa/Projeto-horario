<?php
require_once __DIR__ . '/../configs/db.php';
$res = mysqli_query($conn, "SELECT *, HEX(name) as hname FROM holidays");
echo "ID | NAME (HEX) | DATE | END_DATE\n";
echo "---------------------------\n";
while($row = mysqli_fetch_assoc($res)) {
    echo "{$row['id']} | {$row['hname']} | {$row['date']} | {$row['end_date']}\n";
}
echo "\nTotal feriados: " . mysqli_num_rows($res) . "\n";
?>
