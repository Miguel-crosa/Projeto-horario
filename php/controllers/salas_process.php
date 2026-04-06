<?php
require_once __DIR__ . '/../configs/db.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    mysqli_query($conn, "DELETE FROM ambiente WHERE id = '$id'");
    header("Location: ../views/salas.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    
    // Se for Outros, pega do campo de texto, senão do select (ambos têm o mesmo name="area_vinculada")
    // O navegador deve enviar apenas o que não está disabled, mas vamos garantir o trim.
    $area_vinculada = mysqli_real_escape_string($conn, trim($_POST['area_vinculada']));
    $cidade = mysqli_real_escape_string($conn, $_POST['cidade']);
    $capacidade = mysqli_real_escape_string($conn, $_POST['capacidade']);

    if ($id) {
        // Update
        $query = "UPDATE ambiente SET 
                  nome = '$nome', 
                  tipo = '$tipo', 
                  area_vinculada = '$area_vinculada', 
                  cidade = '$cidade', 
                  capacidade = '$capacidade' 
                  WHERE id = '$id'";
        mysqli_query($conn, $query);
        header("Location: ../views/salas.php?msg=updated");
    } else {
        // Insert
        $query = "INSERT INTO ambiente (nome, tipo, area_vinculada, cidade, capacidade) 
                  VALUES ('$nome', '$tipo', '$area_vinculada', '$cidade', '$capacidade')";
        mysqli_query($conn, $query);
        header("Location: ../views/salas.php?msg=created");
    }
    exit;
}

header("Location: ../views/salas.php");
?>