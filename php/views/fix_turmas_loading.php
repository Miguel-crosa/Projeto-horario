<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
include __DIR__ . '/../components/header.php';

// Busca todas as turmas para saber o total
$res = mysqli_query($conn, "SELECT id, sigla FROM turma");
$turmas = mysqli_fetch_all($res, MYSQLI_ASSOC);
$total = count($turmas);
?>

<div class="page-container" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 70vh; text-align: center;">
    
    <div class="loading-card" style="background: var(--bg-card); padding: 40px; border-radius: 20px; box-shadow: var(--shadow-lg); max-width: 600px; width: 100%; border: 1px solid var(--border-color); position: relative; overflow: hidden;">
        
        <!-- Background Animation Effect -->
        <div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(237,28,36,0.05) 0%, transparent 70%); animation: pulse 8s infinite linear; pointer-events: none;"></div>

        <div class="icon-box" style="font-size: 4rem; color: var(--primary-red); margin-bottom: 25px; animation: bounce 2s infinite ease-in-out;">
            <i class="fas fa-magic"></i>
        </div>

        <h2 style="font-weight: 800; margin-bottom: 10px; font-size: 1.8rem; color: var(--text-color);">Ajustando Horários</h2>
        <p style="color: var(--text-muted); margin-bottom: 30px; font-size: 1rem;">O sistema está recalculando as datas de término e regenerando as agendas para garantir a precisão total contra feriados e férias.</p>

        <div class="progress-outer" style="background: rgba(255,255,255,0.05); height: 12px; border-radius: 10px; width: 100%; margin-bottom: 15px; position: relative; overflow: hidden; border: 1px solid var(--border-color);">
            <div id="progress-inner" style="background: linear-gradient(90deg, var(--primary-red), #ff5252); height: 100%; width: 0%; transition: width 0.3s ease; border-radius: 10px; box-shadow: 0 0 15px rgba(237,28,36,0.4);"></div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <span id="status-text" style="font-size: 0.85rem; font-weight: 600; color: var(--primary-red);">Iniciando...</span>
            <span id="percent-text" style="font-size: 0.85rem; font-weight: 700; color: var(--text-color);">0%</span>
        </div>

        <div id="log-container" style="background: rgba(0,0,0,0.2); border-radius: 10px; height: 120px; overflow-y: auto; padding: 15px; text-align: left; font-family: 'Consolas', monospace; font-size: 0.75rem; color: #aaa; border: 1px solid rgba(255,255,255,0.05);">
            <div style="color: #666; margin-bottom: 5px;">> Aguardando processamento...</div>
        </div>
    </div>

    <div id="finish-actions" style="display: none; margin-top: 30px; animation: fadeIn 0.5s ease;">
        <a href="turmas.php" class="btn btn-primary" style="padding: 12px 30px; font-size: 1rem; border-radius: 50px; box-shadow: 0 4px 15px rgba(237,28,36,0.3);">
            <i class="fas fa-check-circle"></i> Concluir e Voltar
        </a>
    </div>

</div>

<style>
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    @keyframes pulse {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    #log-container::-webkit-scrollbar { width: 4px; }
    #log-container::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }
</style>

<script>
const turmas = <?= json_encode($turmas) ?>;
const total = turmas.length;
let current = 0;

async function processNext() {
    if (current >= total) {
        finish();
        return;
    }

    const turma = turmas[current];
    updateUI(`Processando: ${turma.sigla || 'Turma #'+turma.id}`, current);

    try {
        const response = await fetch('../controllers/fix_all_turmas_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${turma.id}`
        });
        const result = await response.json();
        
        if (result.success) {
            log(`[OK] ${turma.sigla || 'Turma #'+turma.id} ajustada.`);
        } else {
            log(`[ERRO] ${turma.sigla || 'Turma #'+turma.id}: ${result.message}`, true);
        }
    } catch (e) {
        log(`[FALHA] Erro de rede na Turma ${turma.id}`, true);
    }

    current++;
    processNext();
}

function updateUI(status, idx) {
    const progress = Math.round((idx / total) * 100);
    document.getElementById('progress-inner').style.width = progress + '%';
    document.getElementById('percent-text').innerText = progress + '%';
    document.getElementById('status-text').innerText = status;
}

function log(msg, isError = false) {
    const container = document.getElementById('log-container');
    const entry = document.createElement('div');
    entry.style.color = isError ? '#ff5252' : '#81c784';
    entry.style.marginBottom = '3px';
    entry.innerText = `> ${msg}`;
    container.appendChild(entry);
    container.scrollTop = container.scrollHeight;
}

function finish() {
    updateUI('Concluído!', total);
    document.getElementById('finish-actions').style.display = 'block';
    log('Processamento de todas as turmas finalizado com sucesso.');
}

window.onload = () => {
    if (total === 0) {
        log('Nenhuma turma encontrada para ajustar.');
        finish();
    } else {
        log(`Iniciando ajuste de ${total} turmas...`);
        setTimeout(processNext, 1000);
    }
};
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
