<?php
require_once __DIR__ . '/../configs/db.php';
$sql = "
    SELECT name, date, end_date, COUNT(*) as c
    FROM holidays
    GROUP BY name, date, end_date
    HAVING c > 1
";
$res = mysqli_query($conn, $sql);
echo "NOME | DATA | FIM | CONTAGEM\n";
echo "-------------------------------\n";
while($row = mysqli_fetch_assoc($res)) {
    echo "{$row['name']} | {$row['date']} | {$row['end_date']} | {$row['c']}\n";
}
?>
