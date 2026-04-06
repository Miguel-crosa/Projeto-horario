<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

if (!isAdmin() && !isGestor()) {
    header("Location: ../../index.php");
    exit;
}
?>

<div class="page-header">
    <h2><i class="bi bi-cloud-download-fill"></i> Exportar / Importar Dados</h2>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header"
        style="margin-bottom: 30px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; text-align: center;">
        <h3 style="display: flex; align-items: center; gap: 12px; justify-content: center;">
            <i class="bi bi-file-earmark-arrow-down" style="color: var(--primary-red); font-size: 1.8rem;"></i>
            Exportação Unificada
        </h3>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 10px;">Baixe todos os dados do sistema (Turmas
            e Agendas) em um único arquivo.</p>
    </div>

    <div style="display: flex; flex-direction: column; gap: 20px; padding: 0 20px 20px;">
        <!-- Unified Export Button -->
        <div
            style="background: rgba(46, 125, 50, 0.05); padding: 25px; border-radius: 15px; border: 1px solid rgba(46, 125, 50, 0.1); text-align: center;">
            <h4 style="margin-bottom: 15px; color: #2e7d32; font-weight: 700;">Excel Completo</h4>
            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 20px;">Gera uma planilha Excel (.xls)
                com abas separadas para Usuários, Cargos, Turmas, Reservas, Agenda, Cursos, Docentes e Ambientes.</p>
            <a href="../controllers/export_excel.php?tipo=excel" class="btn btn-primary"
                style="background: #2e7d32; border: none; justify-content: center; width: 100%; padding: 15px; font-size: 1.1rem; border-radius: 10px; box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2);">
                <i class="bi bi-file-earmark-excel" style="margin-right: 8px;"></i> Exportar para Excel
            </a>
        </div>
    </div>

</div>

<style>
    .btn:hover {
        transform: translateY(-2px);
        filter: brightness(1.1);
    }
</style>

<?php include __DIR__ . '/../components/footer.php'; ?>