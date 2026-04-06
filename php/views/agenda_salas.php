<?php
/**
 * Agenda de Salas (Ambientes)
 * Mostra ocupação semanal por ambiente.
 */
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

// Helper for date navigation
$view_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$sala_id = isset($_GET['sala_id']) ? (int) $_GET['sala_id'] : null;

// Get Monday of the week
$monday = date('Y-m-d', strtotime('monday this week', strtotime($view_date)));
$week_days = [];
for ($i = 0; $i < 6; $i++) {
    $date = date('Y-m-d', strtotime("$monday +$i days"));
    $week_days[$date] = [
        'name' => ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][$i],
        'date' => $date
    ];
}

// Buscar ambientes (tabela: ambiente no Miguel, era salas no Parafal)
$salas = mysqli_query($conn, "SELECT id, nome FROM ambiente ORDER BY nome ASC");
$salas_arr = [];
while ($row = mysqli_fetch_assoc($salas)) {
    $salas_arr[] = $row;
}

if ($sala_id) {
    // Buscar agenda para este ambiente nesta semana
    // Tabelas: agenda, turma (era turmas), docente (era professores)
    $sunday = date('Y-m-d', strtotime("$monday +6 days"));
    $stmt = $mysqli->prepare("
        SELECT a.*, t.sigla as turma_nome, d.nome as professor_nome, d.cor_agenda 
        FROM agenda a 
        JOIN turma t ON a.turma_id = t.id 
        JOIN docente d ON a.docente_id = d.id 
        WHERE a.ambiente_id = ? AND a.data BETWEEN ? AND ?
        ORDER BY a.horario_inicio ASC
    ");
    $stmt->bind_param('iss', $sala_id, $monday, $sunday);
    $stmt->execute();
    $aulas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Group by date
    $agenda_data = [];
    foreach ($aulas as $aula) {
        $agenda_data[$aula['data']][] = $aula;
    }
}
?>

<div class="page-header"
    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2><i class="fas fa-door-open" style="color: var(--primary-red, #e63946); margin-right: 8px;"></i>Agenda de
        Ambientes</h2>
    <div style="display: flex; gap: 10px;">
        <select onchange="window.location.href='?sala_id=' + this.value" class="form-input" style="min-width: 220px;">
            <option value="">Selecione um Ambiente...</option>
            <?php foreach ($salas_arr as $s): ?>
                <option value="<?php echo $s['id']; ?>" <?php echo $sala_id == $s['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($s['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if ($sala_id): ?>
    <div style="display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 20px;">
        <a href="?sala_id=<?php echo $sala_id; ?>&date=<?php echo date('Y-m-d', strtotime("$monday -7 days")); ?>"
            class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <i class="fas fa-chevron-left"></i> Semana Anterior
        </a>
        <span style="font-weight: 600; font-size: 1.1rem;">
            <?php echo date('d/m', strtotime($monday)); ?> -
            <?php echo date('d/m', strtotime($sunday)); ?>
        </span>
        <a href="?sala_id=<?php echo $sala_id; ?>&date=<?php echo date('Y-m-d', strtotime("$monday +7 days")); ?>"
            class="btn" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            Próxima Semana <i class="fas fa-chevron-right"></i>
        </a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 15px; overflow-x: auto;">
        <?php foreach ($week_days as $date => $info): ?>
            <div style="min-width: 150px;">
                <div
                    style="text-align: center; margin-bottom: 10px; padding: 10px; background: var(--card-bg); border-radius: 8px; border-bottom: 3px solid var(--primary-red, #e63946);">
                    <strong>
                        <?php echo $info['name']; ?>
                    </strong><br>
                    <small style="color: var(--text-muted);">
                        <?php echo date('d/m', strtotime($date)); ?>
                    </small>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php if (isset($agenda_data[$date])): ?>
                        <?php foreach ($agenda_data[$date] as $a): ?>
                            <div class="card"
                                style="border-left: 5px solid <?php echo htmlspecialchars($a['cor_agenda'] ?? '#ed1c24'); ?>; padding: 12px; font-size: 0.9rem;">
                                <div
                                    style="font-weight: 800; color: <?php echo htmlspecialchars($a['cor_agenda'] ?? '#ed1c24'); ?>; margin-bottom: 5px;">
                                    <?php echo substr($a['horario_inicio'], 0, 5); ?> -
                                    <?php echo substr($a['horario_fim'], 0, 5); ?>
                                </div>
                                <div style="font-weight: 600; margin-bottom: 3px;">
                                    <?php echo htmlspecialchars($a['turma_nome']); ?>
                                </div>
                                <div style="color: var(--text-muted); font-size: 0.8rem;">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($a['professor_nome']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div
                            style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 0.8rem; border: 1px dashed var(--border-color); border-radius: 8px;">
                            Ambiente Livre
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card" style="text-align: center; padding: 100px;">
        <i class="fas fa-door-open" style="font-size: 3rem; color: var(--border-color); margin-bottom: 20px;"></i>
        <h3>Selecione um ambiente para visualizar a ocupação semanal.</h3>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../components/footer.php'; ?>