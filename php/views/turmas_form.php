<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

if (!can_edit()) {
    header("Location: turmas.php");
    exit;
}

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : null;
$return_url = $_GET['return_url'] ?? '';
$turma = [
    'curso_id' => '',
    'ambiente_id' => '',
    'periodo' => '',
    'data_inicio' => '',
    'data_fim' => '',
    'tipo' => 'Presencial',
    'sigla' => '',
    'vagas' => '32',
    'docente_id1' => '',
    'docente_id2' => '',
    'docente_id3' => '',
    'docente_id4' => '',
    'local' => 'Sede',
    'dias_semana' => '',
    'horario_inicio' => '07:30',
    'horario_fim' => '11:30',
    'horario_almoco' => '02:00',
    'tipo_custeio' => 'Gratuidade',
    'previsao_despesa' => 0,
    'valor_turma' => 0,
    'numero_proposta' => '',
    'tipo_atendimento' => 'Balcão',
    'parceiro' => '',
    'contato_parceiro' => ''
];

if ($id) {
    $turma = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM turma WHERE id = '$id'"));
    if (!$turma) {
        header("Location: turmas.php");
        exit;
    }
}

// O componente espera que $id e $turma estejam definidos, 
// e carregará automaticamente os dados de cursos, ambientes e docentes via dependência interna do $conn.
?>

<div class="page-header">
    <h2><?= $id ? 'Editar Turma' : 'Nova Turma' ?></h2>
    <a href="<?= !empty($return_url) ? htmlspecialchars($return_url) : 'turmas.php' ?>" class="btn btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 700px; margin: 0 auto; padding: 30px;">
    <?php include __DIR__ . '/../components/form_turma_unificado.php'; ?>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>