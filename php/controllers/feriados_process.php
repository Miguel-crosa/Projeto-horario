<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';

// Apenas admin/gestor
if (!isAdmin() && !isGestor()) {
    header("Location: ../../index.php");
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = (int) $_GET['id'];
    mysqli_query($conn, "DELETE FROM holidays WHERE id = $id");
    header("Location: ../views/feriados.php?msg=deleted");
    exit;
}

if ($action == 'delete_all') {
    if (isAdmin()) {
        mysqli_query($conn, "DELETE FROM holidays");
        header("Location: ../views/feriados.php?msg=deleted_all");
    } else {
        header("Location: ../views/feriados.php?msg=unauthorized");
    }
    exit;
}

if ($action == 'delete_bulk') {
    $ids = $_POST['ids'] ?? [];
    if (!empty($ids) && is_array($ids)) {
        $ids_string = implode(',', array_map('intval', $ids));
        mysqli_query($conn, "DELETE FROM holidays WHERE id IN ($ids_string)");
        header("Location: ../views/feriados.php?msg=deleted_bulk&count=" . count($ids));
    } else {
        header("Location: ../views/feriados.php?msg=no_selection");
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $user_id = $auth_user_id;

    if ($id) {
        // Update
        mysqli_query($conn, "UPDATE holidays SET name = '$name', date = '$date', end_date = '$end_date' WHERE id = $id");
        header("Location: ../views/feriados.php?msg=updated");
    } else {
        // Insert
        // Evitar duplicidade
        $check = mysqli_query($conn, "SELECT id FROM holidays WHERE name = '$name' AND date = '$date' AND (end_date = '$end_date' OR (end_date IS NULL AND '$end_date' = ''))");
        if (mysqli_num_rows($check) == 0) {
            mysqli_query($conn, "INSERT INTO holidays (name, date, end_date, created_by) VALUES ('$name', '$date', '$end_date', $user_id)");
            header("Location: ../views/feriados.php?msg=created");
        } else {
            header("Location: ../views/feriados.php?msg=duplicate");
        }
    }
    exit;
}

header("Location: ../views/feriados.php");
?>