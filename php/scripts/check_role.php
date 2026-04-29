<?php
require_once __DIR__ . '/../configs/db.php';
$r = mysqli_query($conn, 'DESCRIBE usuario');
while($f = mysqli_fetch_assoc($r)) {
    if($f['Field'] == 'role') {
        print_r($f);
    }
}
