<?php
require_once 'php/configs/db.php';
$res = mysqli_query($conn, "DESCRIBE reservas");
echo "RESERVAS:\n";
while($row = mysqli_fetch_assoc($res)) { echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n"; }
echo "\nTURMA:\n";
$res = mysqli_query($conn, "DESCRIBE turma");
while($row = mysqli_fetch_assoc($res)) { echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n"; }
