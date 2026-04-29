<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';

if (!isAdmin() && !isGestor()) {
    header("Location: ../../index.php");
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // First, find all turmas of this course to delete their agendas
    $res_t = mysqli_query($conn, "SELECT id FROM turma WHERE curso_id = '$id'");
    while ($row_t = mysqli_fetch_assoc($res_t)) {
        $tid = $row_t['id'];
        mysqli_query($conn, "DELETE FROM agenda WHERE turma_id = '$tid'");
    }

    // Then delete the turmas
    mysqli_query($conn, "DELETE FROM turma WHERE curso_id = '$id'");

    // Finally delete the course
    mysqli_query($conn, "DELETE FROM curso WHERE id = '$id'");

    header("Location: ../views/cursos.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $area = mysqli_real_escape_string($conn, $_POST['area']);
    $carga_horaria = mysqli_real_escape_string($conn, $_POST['carga_horaria_total']);
    $semestral = isset($_POST['semestral']) ? 1 : 0;

    if ($id) {
        $query = "UPDATE curso SET tipo='$tipo', nome='$nome', area='$area', carga_horaria_total='$carga_horaria', semestral=$semestral WHERE id='$id'";
        mysqli_query($conn, $query);
    } else {
        $query = "INSERT INTO curso (tipo, nome, area, carga_horaria_total, semestral) VALUES ('$tipo', '$nome', '$area', '$carga_horaria', $semestral)";
        mysqli_query($conn, $query);
    }
    header("Location: ../views/cursos.php?msg=success");
    exit;
}
?>