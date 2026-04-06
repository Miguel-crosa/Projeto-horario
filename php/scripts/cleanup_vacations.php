<?php
require_once __DIR__ . '/../configs/db.php';

// SQL para deletar duplicatas mantendo apenas o registro com menor ID
$sql = "
    DELETE v1 FROM vacations v1
    INNER JOIN vacations v2 
    WHERE v1.id > v2.id 
      AND v1.type = v2.type
      AND v1.start_date = v2.start_date
      AND v1.end_date = v2.end_date
      AND (v1.teacher_id = v2.teacher_id OR (v1.teacher_id IS NULL AND v2.teacher_id IS NULL))
";

if (mysqli_query($conn, $sql)) {
    $affected = mysqli_affected_rows($conn);
    echo "Sucesso: $affected registros duplicados foram removidos.\n";
} else {
    echo "Erro ao limpar duplicatas: " . mysqli_error($conn) . "\n";
}
?>
