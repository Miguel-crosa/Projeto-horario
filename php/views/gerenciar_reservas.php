<?php
/**
 * Gerenciar Reservas - Versão Refatorada
 * Suporta os statuses: PENDENTE, APROVADA, RECUSADA, CONCLUIDA
 */
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';

// Apenas gestores/admins e CRI (limitado) podem acessar. Gestores/Admins gerenciam, CRI apenas visualiza os seus.
if (!can_edit() && !isCRI()) {
    $path_parts = explode('/', trim($_SERVER['PHP_SELF'], '/'));
    $is_in_subdir = !empty(array_intersect(['views', 'controllers'], $path_parts));
    $prefix = $is_in_subdir ? '../../' : '';
    header('Location: ' . $prefix . 'index.php');
    exit;
}

$msg_success = $msg_error = '';

// Buscar reservas
$status_filter = $_GET['status'] ?? 'PENDENTE';
$owner_filter = $_GET['owner'] ?? 'all';
$reserva_id_target = isset($_GET['reserva_id']) ? (int) $_GET['reserva_id'] : null;

// Se temos um ID alvo, devemos garantir que estamos na aba de status correta
if ($reserva_id_target) {
    $st_check = $mysqli->prepare("SELECT status FROM reservas WHERE id = ?");
    $st_check->bind_param('i', $reserva_id_target);
    $st_check->execute();
    $res_check = $st_check->get_result()->fetch_assoc();
    if ($res_check) {
        $status_filter = $res_check['status'];
    }
}

if ($status_filter === 'APROVADA') {
    $where = "WHERE r.status IN ('APROVADA', 'CONCLUIDA')";
    $params = [];
    $types = '';
} else {
    $where = "WHERE r.status = ?";
    $params = [$status_filter];
    $types = 's';
}

if ($owner_filter === 'mine' || isCRI()) {
    $where .= " AND r.usuario_id = ?";
    $params[] = $auth_user_id;
    $types .= 'i';
}

$st = $mysqli->prepare("
    SELECT r.*, d.nome as professor_nome, d.area_conhecimento as especialidade, d.cor_agenda,
           u.nome as gestor_nome, c.nome as curso_nome, amb.nome as ambiente_nome
    FROM reservas r
    JOIN docente d ON r.docente_id = d.id
    JOIN usuario u ON r.usuario_id = u.id
    LEFT JOIN curso c ON r.curso_id = c.id
    LEFT JOIN ambiente amb ON r.ambiente_id = amb.id
    $where
    ORDER BY r.created_at DESC
");
if (!empty($types)) {
    $st->bind_param($types, ...$params);
}
$st->execute();
$reservas = $st->get_result()->fetch_all(MYSQLI_ASSOC);

// Contagem para badges (Total PENDENTE)
$count_pendente = $mysqli->query("SELECT COUNT(*) FROM reservas WHERE status = 'PENDENTE'")->fetch_row()[0];

$dow_map = ['Segunda-feira' => 'Seg', 'Terça-feira' => 'Ter', 'Quarta-feira' => 'Qua', 'Quinta-feira' => 'Qui', 'Sexta-feira' => 'Sex', 'Sábado' => 'Sáb', 'Domingo' => 'Dom'];

include __DIR__ . '/../components/header.php';
?>

<style>
    .reserva-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }

    @media (max-width: 480px) {
        .reserva-grid {
            grid-template-columns: 1fr;
        }
    }

    .reserva-card {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 0;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .reserva-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .status-banner {
        padding: 8px 15px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .status-PENDENTE {
        background: rgba(255, 179, 0, 0.1);
        color: #e65100;
        border-bottom: 1px solid rgba(255, 179, 0, 0.2);
    }

    .status-APROVADA {
        background: rgba(46, 125, 50, 0.1);
        color: #1b5e20;
        border-bottom: 1px solid rgba(46, 125, 50, 0.2);
    }

    .status-RECUSADA {
        background: rgba(211, 47, 47, 0.1);
        color: #b71c1c;
        border-bottom: 1px solid rgba(211, 47, 47, 0.2);
    }

    .status-CONCLUIDA {
        background: rgba(33, 150, 243, 0.1);
        color: #0d47a1;
        border-bottom: 1px solid rgba(33, 150, 243, 0.2);
    }

    .card-body {
        padding: 20px;
        flex-grow: 1;
    }

    .prof-info {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
    }

    .prof-avatar {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        background: #eee;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: #666;
    }

    .prof-name {
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--text-color);
        margin: 0;
        line-height: 1.2;
    }

    .prof-esp {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin: 0;
    }

    .course-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-color);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .course-title i {
        color: var(--primary-red);
    }

    .meta-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 15px;
    }

    .meta-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .meta-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        color: var(--text-muted);
        font-weight: 700;
    }

    .meta-value {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-color);
    }

    .dias-badge-container {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-top: 5px;
    }

    .dia-badge {
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.72rem;
        font-weight: 700;
        background: rgba(0, 0, 0, 0.05);
        color: #555;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    .card-footer {
        padding: 15px 20px;
        background: rgba(0, 0, 0, 0.02);
        border-top: 1px solid var(--border-color);
        display: flex;
        gap: 10px;
    }

    .btn-action {
        flex: 1;
        padding: 10px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        font-weight: 700;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-approve {
        background: #2e7d32;
        color: white;
        border-color: #1b5e20;
    }

    .btn-approve:hover {
        background: #1b5e20;
        transform: scale(1.02);
    }

    .btn-refuse {
        background: transparent;
        color: #d32f2f;
        border-color: #d32f2f;
    }

    .btn-refuse:hover {
        background: #feebeb;
    }

    .btn-pending {
        background: #ffb300;
        color: #5d4037;
        border-color: #ffa000;
        cursor: default;
    }

    .filter-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        background: var(--card-bg);
        padding: 15px 25px;
        border-radius: 15px;
        border: 1px solid var(--border-color);
    }

    @media (max-width: 768px) {
        .filter-bar {
            flex-direction: column;
            gap: 15px;
            padding: 15px;
            align-items: stretch;
        }
        .status-filters {
            overflow-x: auto;
            padding-bottom: 10px;
            -webkit-overflow-scrolling: touch;
        }
        .filter-chip {
            white-space: nowrap;
        }
    }

    .status-filters {
        display: flex;
        gap: 10px;
    }

    .filter-chip {
        padding: 8px 18px;
        border-radius: 20px;
        background: var(--bg-color);
        color: var(--text-color);
        font-weight: 700;
        font-size: 0.85rem;
        text-decoration: none;
        border: 1px solid var(--border-color);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .filter-chip i {
        color: inherit;
        opacity: 0.8;
    }

    .filter-chip:hover {
        background: var(--border-color);
        transform: translateY(-1px);
    }

    /* Cores específicas por Status (Ativo) */
    .filter-chip.active.chip-pendente {
        background: #ff9800;
        color: white;
        border-color: #ff9800;
        box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
    }

    .filter-chip.active.chip-aprovada {
        background: #4caf50;
        color: white;
        border-color: #4caf50;
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
    }

    .filter-chip.active.chip-concluida {
        background: #2196f3;
        color: white;
        border-color: #2196f3;
        box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
    }

    .filter-chip.active.chip-recusada {
        background: #ef5350;
        color: white;
        border-color: #ef5350;
        box-shadow: 0 4px 12px rgba(239, 83, 80, 0.3);
    }

    .filter-chip.active {
        transform: translateY(-2px);
    }

    .badge-count {
        background: rgba(255, 255, 255, 0.3);
        padding: 1px 6px;
        border-radius: 8px;
        font-size: 0.75rem;
        margin-left: 5px;
    }

    .empty-state {
        text-align: center;
        padding: 80px 20px;
    }

    .empty-icon {
        font-size: 4rem;
        color: var(--text-muted);
        opacity: 0.2;
        margin-bottom: 20px;
    }

    .reserva-card.highlight {
        border: 2px solid var(--primary-red);
        transform: translateY(-5px);
        box-shadow: 0 0 20px rgba(225, 29, 37, 0.2);
        animation: pulse-highlight 2s infinite;
    }

    @keyframes pulse-highlight {
        0% {
            box-shadow: 0 0 0 0 rgba(225, 29, 37, 0.4);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(225, 29, 37, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(225, 29, 37, 0);
        }
    }
</style>

<div class="page-header" style="margin-bottom: 30px;">
    <h2><i class="fas fa-bookmark" style="color: var(--primary-red);"></i> Gerenciar Reservas</h2>
    <p style="color: var(--text-muted);">Página para aprovação e acompanhamento de solicitações de reserva de horários.
    </p>
</div>

<div class="filter-bar">
    <div class="status-filters">
        <a href="?status=PENDENTE&owner=<?= $owner_filter ?>"
            class="filter-chip chip-pendente <?= $status_filter === 'PENDENTE' ? 'active' : '' ?>">
            <i class="fas fa-hourglass-half"></i> Pendentes <span class="badge-count"><?= $count_pendente ?></span>
        </a>
        <a href="?status=APROVADA&owner=<?= $owner_filter ?>"
            class="filter-chip chip-aprovada <?= ($status_filter === 'APROVADA' || $status_filter === 'CONCLUIDA') ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Aprovadas
        </a>
        <a href="?status=RECUSADA&owner=<?= $owner_filter ?>"
            class="filter-chip chip-recusada <?= $status_filter === 'RECUSADA' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Recusadas
        </a>
    </div>

    <?php if (!isCRI()): ?>
        <div class="owner-filters">
            <select onchange="location.href='?status=<?= $status_filter ?>&owner='+this.value"
                style="padding: 8px 15px; border-radius: 10px; border: 1px solid var(--border-color);">
                <option value="all" <?= $owner_filter === 'all' ? 'selected' : '' ?>>Todas as Reservas</option>
                <option value="mine" <?= $owner_filter === 'mine' ? 'selected' : '' ?>>Minhas Solicitações</option>
            </select>
        </div>
    <?php endif; ?>
</div>

<?php if (empty($reservas)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-calendar-times"></i></div>
        <h3 style="color: var(--text-muted);">Nenhuma reserva encontrada</h3>
        <p style="color: var(--text-muted);">Mude o filtro ou crie uma nova solicitação no calendário.</p>
    </div>
<?php else: ?>
    <div class="reserva-grid">
        <?php foreach ($reservas as $r):
            $is_highlight = ($reserva_id_target && $r['id'] == $reserva_id_target);
            ?>
            <div class="reserva-card <?= $is_highlight ? 'highlight' : '' ?>" id="reserva-card-<?= $r['id'] ?>">
                <div class="status-banner status-<?= $r['status'] ?>">
                    <span><?= $r['status'] ?></span>
                    <span>#<?= $r['id'] ?></span>
                </div>

                <div class="card-body">
                    <div class="prof-info">
                        <div class="prof-avatar" style="background: <?= $r['cor_agenda'] ?>33; color: <?= $r['cor_agenda'] ?>;">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div>
                            <p class="prof-name"><?= htmlspecialchars($r['professor_nome']) ?></p>
                            <p class="prof-esp"><?= htmlspecialchars($r['especialidade'] ?: 'Docente') ?></p>
                        </div>
                    </div>

                    <div class="course-title">
                        <i class="fas fa-graduation-cap"></i>
                        <?= htmlspecialchars($r['curso_nome']) ?>
                        <span
                            style="font-size: 0.7rem; background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 5px;"><?= htmlspecialchars($r['sigla']) ?></span>
                    </div>

                    <div class="meta-grid">
                        <div class="meta-item">
                            <span class="meta-label">Período</span>
                            <span class="meta-value"><?= date('d/m/Y', strtotime($r['data_inicio'])) ?> -
                                <?= date('d/m/Y', strtotime($r['data_fim'])) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Horário (<?= $r['periodo'] ?>)</span>
                            <span class="meta-value"><?= substr($r['hora_inicio'], 0, 5) ?> -
                                <?= substr($r['hora_fim'], 0, 5) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Ambiente</span>
                            <span class="meta-value"><?= htmlspecialchars($r['ambiente_nome'] ?: 'Não definido') ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Local / Vagas</span>
                            <span class="meta-value"><?= htmlspecialchars($r['local']) ?> / <?= $r['vagas'] ?></span>
                        </div>
                    </div>

                    <div class="meta-item">
                        <span class="meta-label">Solicitado por</span>
                        <span class="meta-value" style="font-size: 0.75rem;"><i class="fas fa-user-circle"></i>
                            <?= htmlspecialchars($r['gestor_nome']) ?></span>
                    </div>

                    <div class="dias-badge-container">
                        <?php
                        $dias = explode(',', $r['dias_semana']);
                        foreach ($dias as $d):
                            $short = $dow_map[trim($d)] ?? $d;
                            ?>
                            <span class="dia-badge"><?= $short ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card-footer">
                    <?php if ($r['status'] === 'PENDENTE'): ?>
                        <?php if (isAdmin() || isGestor()): ?>
                            <button class="btn-action btn-approve" onclick="manageReserva(<?= $r['id'] ?>, 'aprovar')">
                                <i class="fas fa-check"></i> Aprovar
                            </button>
                            <button class="btn-action btn-refuse" onclick="manageReserva(<?= $r['id'] ?>, 'recusar')">
                                <i class="fas fa-times"></i> Recusar
                            </button>
                        <?php else: ?>
                            <div class="btn-action btn-pending">
                                <i class="fas fa-hourglass-half"></i> Aguardando Aprovação
                            </div>
                        <?php endif; ?>
                    <?php elseif ($r['status'] === 'APROVADA' || $r['status'] === 'CONCLUIDA'): ?>
                        <div class="btn-action"
                            style="background: #e8f5e9; color: #2e7d32; border-color: #c8e6c9; cursor: default;">
                            <i class="fas fa-check-double"></i> Reserva Aprovada
                        </div>
                    <?php elseif ($r['status'] === 'RECUSADA'): ?>
                        <div class="btn-action"
                            style="background: #ffebee; color: #c62828; border-color: #ffcdd2; cursor: default;">
                            <i class="fas fa-ban"></i> Reserva Recusada
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
    async function manageReserva(id, action) {
        if (action === 'aprovar') {
            const result = await Swal.fire({
                title: 'Confirmar Aprovação',
                text: "Deseja aprovar esta reserva? Você pode escolher enviar um e-mail de confirmação para o docente.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2e7d32',
                cancelButtonColor: '#d33',
                confirmButtonText: '<i class="fas fa-envelope"></i> Aprovar e Enviar E-mail',
                denyButtonText: '<i class="fas fa-check"></i> Aprovar sem E-mail',
                showDenyButton: true,
                cancelButtonText: 'Cancelar'
            });

            if (result.isDismissed) return; // Cancelado

            const shouldSendEmail = result.isConfirmed;
            const apiAction = 'aprovar_reserva';
            const fd = new FormData();
            fd.append('action', apiAction);
            fd.append('reserva_id', id);
            fd.append('send_email', shouldSendEmail ? '1' : '0');

            try {
                const r = await fetch('../controllers/agenda_api.php', { method: 'POST', body: fd });
                const data = await r.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (e) {
                showNotification('Erro na comunicação com o servidor.', 'error');
            }
        } else {
            // Caso Recusar
            if (!confirm('Deseja realmente RECUSAR esta reserva?')) return;
            const fd = new FormData();
            fd.append('action', 'recusar_reserva');
            fd.append('reserva_id', id);

            try {
                const r = await fetch('../controllers/agenda_api.php', { method: 'POST', body: fd });
                const data = await r.json();
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (e) {
                showNotification('Erro na comunicação com o servidor.', 'error');
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const targetId = '<?= $reserva_id_target ?>';
        if (targetId) {
            const el = document.getElementById('reserva-card-' + targetId);
            if (el) {
                setTimeout(() => {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 500);
            }
        }
    });
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>