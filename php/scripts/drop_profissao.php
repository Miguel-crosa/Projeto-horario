<?php
require_once __DIR__ . '/../configs/db.php';
mysqli_query($conn, "ALTER TABLE docente DROP COLUMN IF EXISTS profissao");
echo "Coluna profissao removida (se existia).\n";
?>
