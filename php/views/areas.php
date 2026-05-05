<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

if (!isAdmin() && !isGestor()) {
    header("Location: ../../index.php");
    exit;
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <div>
        <h2 style="margin: 0; font-weight: 800; color: var(--text-color);"><i class="fas fa-layer-group" style="color: var(--primary-red); margin-right: 12px;"></i> Gerenciar Áreas</h2>
        <p style="color: var(--text-muted); margin: 5px 0 0 0;">Cadastre e organize as áreas de conhecimento do sistema.</p>
    </div>
    <button onclick="openAreaModal()" class="btn btn-primary" style="font-weight: 700; height: 42px; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-plus"></i> NOVA ÁREA
    </button>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div style="padding: 20px; border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.01);">
        <input type="text" id="area-search" placeholder="🔍 Buscar área..." class="form-input" style="max-width: 300px; margin: 0;" onkeyup="filterAreas()">
    </div>
    <div class="table-responsive">
        <table class="table" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th style="padding-left: 25px;">Nome da Área</th>
                    <th style="width: 150px; text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody id="areas-table-body">
                <tr>
                    <td colspan="2" style="text-align: center; padding: 40px; color: var(--text-muted);">Carregando áreas...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para Cadastro/Edição de Área -->
<div class="modal-overlay" id="area-modal" style="display: none; z-index: 9999;">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="justify-content: space-between; display: flex; align-items: center;">
            <h3 id="modal-title"><i class="fas fa-plus-circle" style="color: var(--primary-red); margin-right: 10px;"></i> Nova Área</h3>
            <button class="modal-close" onclick="closeAreaModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <form id="area-form">
                <input type="hidden" id="area-id" name="id">
                <div class="form-group">
                    <label class="form-label">Nome da Área</label>
                    <input type="text" id="area-nome" name="nome" class="form-input" placeholder="Ex: Informática" required>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="button" onclick="closeAreaModal()" class="btn btn-secondary" style="flex: 1;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="flex: 2;">Salvar Área</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
async function loadAreas() {
    try {
        const res = await fetch('../controllers/area_api.php?action=list');
        const data = await res.json();
        
        const tbody = document.getElementById('areas-table-body');
        if (data.success && data.areas.length > 0) {
            tbody.innerHTML = data.areas.map(area => `
                <tr class="area-row" data-nome="${area.nome.toLowerCase()}">
                    <td style="padding-left: 25px; font-weight: 600; color: var(--text-color);">${area.nome}</td>
                    <td style="text-align: center;">
                        <div style="display: flex; gap: 8px; justify-content: center;">
                            <button onclick="editArea(${area.id}, '${area.nome}')" class="btn btn-sm" style="background: var(--bg-hover); color: var(--text-color); padding: 5px 10px;" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteArea(${area.id}, '${area.nome}')" class="btn btn-sm" style="background: rgba(237, 28, 36, 0.1); color: var(--primary-red); padding: 5px 10px;" title="Excluir">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="2" style="text-align: center; padding: 40px; color: var(--text-muted);">Nenhuma área cadastrada.</td></tr>';
        }
    } catch (e) {
        document.getElementById('areas-table-body').innerHTML = '<tr><td colspan="2" style="text-align: center; padding: 40px; color: var(--primary-red);">Erro ao carregar áreas.</td></tr>';
    }
}

function filterAreas() {
    const query = document.getElementById('area-search').value.toLowerCase();
    document.querySelectorAll('.area-row').forEach(row => {
        row.style.display = row.dataset.nome.includes(query) ? '' : 'none';
    });
}

function openAreaModal(id = '', nome = '') {
    document.getElementById('area-id').value = id;
    document.getElementById('area-nome').value = nome;
    document.getElementById('modal-title').innerHTML = id ? `<i class="fas fa-edit" style="color: var(--primary-red); margin-right: 10px;"></i> Editar Área` : `<i class="fas fa-plus-circle" style="color: var(--primary-red); margin-right: 10px;"></i> Nova Área`;
    document.getElementById('area-modal').style.display = 'flex';
    document.getElementById('area-nome').focus();
}

function closeAreaModal() {
    document.getElementById('area-modal').style.display = 'none';
}

function editArea(id, nome) {
    openAreaModal(id, nome);
}

async function deleteArea(id, nome) {
    Swal.fire({
        title: 'Excluir Área?',
        text: `Deseja realmente excluir a área "${nome}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ed1c24',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            
            const res = await fetch('../controllers/area_api.php', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                Swal.fire('Excluído!', data.message, 'success');
                loadAreas();
            } else {
                Swal.fire('Erro!', data.message, 'error');
            }
        }
    });
}

document.getElementById('area-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'save');
    
    try {
        const res = await fetch('../controllers/area_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            closeAreaModal();
            loadAreas();
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Erro!', data.message, 'error');
        }
    } catch (e) {
        Swal.fire('Erro!', 'Erro na conexão com o servidor.', 'error');
    }
});

document.addEventListener('DOMContentLoaded', loadAreas);
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
