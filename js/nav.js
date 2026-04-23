document.addEventListener('DOMContentLoaded', () => {
    // Theme Management - A inicialização agora é feita pelo PHP no header.php para evitar flashes.

    // Sidebar Toggle
    const sidebarToggle = document.getElementById("mobile-sidebar-toggle");
    const sidebarOverlay = document.getElementById("sidebar-overlay");

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            if (window.innerWidth <= 768) {
                // Alternar no mobile (mostra navbar completa por cima)
                document.body.classList.toggle('sidebar-mobile-open');
            } else {
                // Alternar no desktop (retrair para versão de ícones)
                const isClosed = document.body.classList.contains('sidebar-closed');
                if (!isClosed) {
                    document.body.classList.add('sidebar-closed');
                    document.cookie = "sidebar=closed; path=/";
                } else {
                    document.body.classList.remove('sidebar-closed');
                    document.cookie = "sidebar=open; path=/";
                }
            }
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            document.body.classList.remove('sidebar-mobile-open');
        });
    }

    // Dropdown menus toggle (Planejamento / Exportar-Importar)
    document.querySelectorAll('.menu-manutencao .manutencao-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const menu = btn.closest('.menu-manutencao');
            const submenu = menu ? menu.querySelector('.submenu') : null;
            if (!menu || !submenu) return;
            
            const isOpen = submenu.classList.toggle('aberto');
            menu.classList.toggle('aberto');

            // Persistência via cookie para o PHP reconhecer no reload
            const menuId = menu.dataset.menuId;
            if (menuId) {
                document.cookie = `menu_open_${menuId}=${isOpen ? 'open' : 'closed'}; path=/; max-age=${60 * 60 * 24 * 30}`;
            }
        });
    });
});

function changeTheme() {
    let current = document.documentElement.getAttribute("data-tema");
    let next = current === 'escuro' ? 'claro' : 'escuro';

    document.documentElement.setAttribute("data-tema", next);
    localStorage.setItem('tema', next);
    
    // Define o cookie para que o PHP reconheça o tema no próximo carregamento (expira em 1 ano)
    document.cookie = `tema=${next}; path=/; max-age=${60 * 60 * 24 * 365}`;
    
    updateThemeIcon(next);
}

function updateThemeIcon(tema) {
    const btn = document.getElementById("tema");
    if (btn) {
        btn.innerHTML = tema === 'escuro'
            ? '<i class="bi bi-moon-stars-fill"></i>'
            : '<i class="bi bi-brightness-high-fill"></i>';
    }
}

function changeSairBtn(state) {
    const btn = document.querySelector(".sair");
    if (btn) {
        btn.innerHTML = state === 'open'
            ? 'Sair <i class="bi bi-door-open-fill"></i>'
            : 'Sair <i class="bi bi-door-closed-fill"></i>';
    }
}

// Global Modal Helpers
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

// Global click-outside listener helper
let backdropClickStart = null;
window.addEventListener('mousedown', (e) => {
    backdropClickStart = e.target;
});

window.addEventListener('click', (e) => {
    if (e.target === backdropClickStart && e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

/**
 * Global Notification System (Toast)
 */
window.showNotification = function (msg, type = 'info') {
    document.querySelectorAll('.toast-notification').forEach(t => t.remove());
    const t = document.createElement('div');
    t.className = `toast-notification toast-${type}`;
    t.innerHTML = `<div class="toast-content">
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
        <span>${msg}</span>
        <button class="toast-close" onclick="this.parentElement.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>`;
    document.body.prepend(t);
    setTimeout(() => {
        t.classList.add('toast-exit');
        setTimeout(() => t.remove(), 300);
    }, 5000);
};
