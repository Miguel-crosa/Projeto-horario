<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$erro = '';
$sessao_erro = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

if (isset($_GET['erro'])) {
    if ($_GET['erro'] == 'email') {
        $erro = "<div class='div-msg-erro'><p>E-mail não encontrado! Tente novamente</p></div>";
    } else if ($_GET['erro'] == 'senha') {
        $erro = "<div class='div-msg-erro'><p>Senha incorreta! Tente novamente</p></div>";
    } else if ($_GET['erro'] == "maquinaNM") {
        $erro = "<div class='div-msg-erro'><p>Máquina não encontrada ou em manutenção.</p></div>";
    } else if ($_GET['erro'] == "maquinaN") {
        $erro = "<div class='div-msg-erro'><p>Matrícula não encontrada.</p></div>";
    }
} else if ($sessao_erro) {
    $erro = "<div class='div-msg-erro'><p>" . htmlspecialchars($sessao_erro) . "</p></div>";
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-tema="escuro">
<!-- NÃO TIRA O DATA-TEMA DE JEITO NENHUM -->

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SENAI HORARIO</title>

    <!-- Estilização, BootstrapIcons e Favicon -->
    <!-- As pastas foram alteradas para '../..' devido ao local do arquivo: php/views/login.php -->
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/login.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="shortcut icon" href="../../favicon.ico" type="image/x-icon">

    <!-- Biblioteca do QRCODE -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <!-- Biblioteca das Particulas -->
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
</head>

<body class="body_login">

    <div class="login-bg">
        <div id="particles-js"></div>
        <div class="login-box">
            <!-- Rota corrigida para o controller de login -->
            <form class="login-form" action="../controllers/login_process.php" method="POST">
                <input type="hidden" name="action" value="login">

                <div class="div-img">
                    <img src="../../assets/images/senailogo.png" alt="Logo Senai" id="senai-logo" style="width: 70%;">
                </div>

                <?php if ($erro)
                    echo $erro; ?>

                <div class="div-inputs-chefe">
                    <div class="div-input">
                        <i class="bi bi-envelope-fill"></i>
                        <input type="email" id="email" name="email" placeholder="E-mail" class="input" required
                            autofocus>
                        <button type="button" style="visibility: hidden;" id="btnScan1" class="btnEsp"><i
                                class="bi bi-qr-code-scan"></i></button>
                    </div>
                    <div class="div-input">
                        <i class="bi bi-shield-fill"></i>
                        <input type="password" id="senhaLogin" name="senha" placeholder="*****" class="input" required>
                        <button type="button" onclick="showPass()" id="btnEyeLogin" class="btnEsp"><i
                                class="bi bi-eye-fill"></i></button>
                    </div>

                    <div class="div-btn">
                        <button type="submit" class="btn">Entrar <i class="bi bi-box-arrow-in-right"></i></button>
                    </div>
                </div>

            </form>
        </div>
    </div>



    <!-- Carregando Js na página -->
    <script>
        particlesJS("particles-js", {
            particles: {
                number: { value: 80, density: { enable: true, value_area: 800 } },
                color: { value: "#ff2b2b" },
                shape: { type: "circle" },
                opacity: { value: 0.5 },
                size: { value: 3, random: true },
                line_linked: { enable: true, distance: 150, color: "#ff2b2b", opacity: 0.4, width: 1 },
                move: { enable: true, speed: 2.5, direction: "none", out_mode: "out" }
            },
            interactivity: {
                detect_on: "canvas",
                events: {
                    onhover: { enable: true, mode: "grab" },
                    onclick: { enable: true, mode: "push" },
                    resize: true
                },
                modes: {
                    grab: { distance: 140, line_linked: { opacity: 1 } },
                    push: { particles_nb: 4 }
                }
            },
            retina_detect: true
        });

        // Toggle Password Visibility
        function showPass() {
            const input = document.getElementById('senhaLogin');
            const icon = document.querySelector('#btnEyeLogin i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye-fill', 'bi-eye-slash-fill');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash-fill', 'bi-eye-fill');
            }
        }

    </script>

</body>

</html>