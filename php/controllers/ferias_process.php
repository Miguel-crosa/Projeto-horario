<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';

if (!isAdmin() && !isGestor()) {
    header("Location: ../../index.php");
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = (int) $_GET['id'];
    mysqli_query($conn, "DELETE FROM vacations WHERE id = $id");
    header("Location: ../views/ferias.php?msg=deleted");
    exit;
}

if ($action == 'delete_all') {
    if (isAdmin()) {
        mysqli_query($conn, "DELETE FROM vacations");
        header("Location: ../views/ferias.php?msg=deleted_all");
    } else {
        header("Location: ../views/ferias.php?msg=unauthorized");
    }
    exit;
}

if ($action == 'delete_bulk') {
    $ids = $_POST['ids'] ?? [];
    if (!empty($ids) && is_array($ids)) {
        $ids_string = implode(',', array_map('intval', $ids));
        mysqli_query($conn, "DELETE FROM vacations WHERE id IN ($ids_string)");
        header("Location: ../views/ferias.php?msg=deleted_bulk&count=" . count($ids));
    } else {
        header("Location: ../views/ferias.php?msg=no_selection");
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $user_id = $auth_user_id;

    if ($id) {
        $teacher_id = ($type === 'individual') ? (int) $_POST['teacher_id'] : 'NULL';
        if ($type === 'collective' && isset($_POST['collective_teacher_ids']) && is_array($_POST['collective_teacher_ids'])) {
            $teacher_id = (int) $_POST['collective_teacher_ids'][0];
        }
        mysqli_query($conn, "UPDATE vacations SET type = '$type', teacher_id = $teacher_id, start_date = '$start_date', end_date = '$end_date' WHERE id = $id");
        if (isset($_POST['ajax'])) {
            echo json_encode(['status' => 'success']);
            exit;
        }
        header("Location: ../views/ferias.php?msg=updated");
    } else {
        if ($type === 'collective') {
            $collective_ids = $_POST['collective_teacher_ids'] ?? [];
            if (empty($collective_ids)) {
                // Apply to everyone (NULL)
                $check = mysqli_query($conn, "SELECT id FROM vacations WHERE type = 'collective' AND teacher_id IS NULL AND start_date = '$start_date' AND end_date = '$end_date'");
                if (mysqli_num_rows($check) == 0) {
                    mysqli_query($conn, "INSERT INTO vacations (type, teacher_id, start_date, end_date, created_by) VALUES ('$type', NULL, '$start_date', '$end_date', $user_id)");
                }
            } else {
                // Apply ONLY to specific selected multiple teachers
                foreach ($collective_ids as $tid) {
                    $tid = (int) $tid;
                    $check = mysqli_query($conn, "SELECT id FROM vacations WHERE type = 'collective' AND teacher_id = $tid AND start_date = '$start_date' AND end_date = '$end_date'");
                    if (mysqli_num_rows($check) == 0) {
                        mysqli_query($conn, "INSERT INTO vacations (type, teacher_id, start_date, end_date, created_by) VALUES ('$type', $tid, '$start_date', '$end_date', $user_id)");
                    }
                }
            }
        } else {
            // Individual
            $teacher_id = (int) $_POST['teacher_id'];
            $check = mysqli_query($conn, "SELECT id FROM vacations WHERE type = 'individual' AND teacher_id = $teacher_id AND start_date = '$start_date' AND end_date = '$end_date'");
            if (mysqli_num_rows($check) == 0) {
                mysqli_query($conn, "INSERT INTO vacations (type, teacher_id, start_date, end_date, created_by) VALUES ('$type', $teacher_id, '$start_date', '$end_date', $user_id)");
            }
        }
        if (isset($_POST['ajax'])) {
            echo json_encode(['status' => 'success']);
            exit;
        }
        header("Location: ../views/ferias.php?msg=created");
    }
    exit;
}

header("Location: ../views/ferias.php");
?>