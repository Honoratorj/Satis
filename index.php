<?php
require_once __DIR__ . '/config.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Formata uma data para exibição no padrão brasileiro DD/MM/AA (com HH:MM
// quando houver horário). Mantém o valor original se não conseguir interpretar.
function formatDataBR($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }
    return date(strpos($value, ':') !== false ? 'd/m/y H:i' : 'd/m/y', $ts);
}

$ticket_id = $_GET['ticket_id'] ?? '';
$ticket_name = $_GET['ticket_name'] ?? '';
$ticket_createdate = $_GET['ticket_createdate'] ?? '';
$ticket_solvedate = $_GET['ticket_solvedate'] ?? '';

$tecnico = '';
$cargo = '';
$requerente = '';
$tecnico_id = null;
$foto_url = null;

if ($ticket_id !== '') {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $sql = "
            SELECT 
                u.id AS tecnico_id,
                CASE
                    WHEN TRIM(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.realname, ''))) <> ''
                    THEN TRIM(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.realname, '')))
                    ELSE u.name
                END AS tecnico_nome,
                ut.name AS cargo_nome
            FROM glpi_tickets_users tu
            INNER JOIN glpi_users u ON u.id = tu.users_id
            LEFT JOIN glpi_usertitles ut ON ut.id = u.usertitles_id
            WHERE tu.tickets_id = ?
              AND tu.type = 2
            ORDER BY tu.id ASC
            LIMIT 1
        ";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $ticket_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $tecnico_id = (int)$row['tecnico_id'];
                $tecnico = $row['tecnico_nome'] ?? '';
                $cargo = $row['cargo_nome'] ?? '';
            }
            $stmt->close();
        }

        $sql_requerente = "
            SELECT
                CASE
                    WHEN TRIM(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.realname, ''))) <> ''
                    THEN TRIM(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.realname, '')))
                    WHEN TRIM(COALESCE(tu.alternative_email, '')) <> ''
                    THEN TRIM(tu.alternative_email)
                    ELSE COALESCE(u.name, '')
                END AS requerente_nome
            FROM glpi_tickets_users tu
            LEFT JOIN glpi_users u ON u.id = tu.users_id
            WHERE tu.tickets_id = ?
              AND tu.type = 1
            ORDER BY tu.id ASC
            LIMIT 1
        ";
        if ($stmt = $conn->prepare($sql_requerente)) {
            $stmt->bind_param('i', $ticket_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $requerente = $row['requerente_nome'] ?? '';
            }
            $stmt->close();
        }

        $conn->close();
    } catch (mysqli_sql_exception $e) {
        // Falha de conexão/consulta não deve derrubar a página; o técnico e o
        // requerente simplesmente ficam em branco.
        error_log('Pesquisa/index.php - falha ao buscar dados do ticket: ' . $e->getMessage());
    }
}

if ($tecnico_id) {
    $extensions = ['jpg', 'jpeg', 'png', 'webp'];
    foreach ($extensions as $ext) {
        $relative = '/fotos_tecnicos/' . $tecnico_id . '.' . $ext;
        $absolute = __DIR__ . $relative;
        if (file_exists($absolute)) {
            $foto_url = $relative;
            break;
        }
    }
}

$inicial = $tecnico !== '' ? mb_strtoupper(mb_substr($tecnico, 0, 1, 'UTF-8'), 'UTF-8') : 'T';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesquisa de Satisfação</title>
    <style>
        :root {
            --bg: #f3f5f7;
            --card: #ffffff;
            --text: #17212b;
            --muted: #667085;
            --line: #e4e7ec;
            --primary: #ad1a05;
            --primary-soft: #fff1ee;
            --accent: #f98224;
            --shadow: 0 12px 36px rgba(16, 24, 40, 0.08);
            --radius: 22px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 16px;
            overflow-x: hidden;
        }

        /* ---------- Fundo animado ---------- */
        #bg-canvas {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            display: block;
        }

        .aurora {
            position: fixed;
            inset: -20vmax;
            z-index: -1;
            pointer-events: none;
            filter: blur(60px);
            opacity: 0.55;
        }

        .aurora span {
            position: absolute;
            display: block;
            width: 46vmax;
            height: 46vmax;
            border-radius: 50%;
            mix-blend-mode: multiply;
            animation: drift 22s ease-in-out infinite;
        }

        .aurora .b1 { background: radial-gradient(circle, #ffd3c2 0%, transparent 65%); top: -10vmax; left: -6vmax; }
        .aurora .b2 { background: radial-gradient(circle, #ffe1b3 0%, transparent 65%); top: 10vmax; right: -12vmax; animation-delay: -7s; animation-duration: 27s; }
        .aurora .b3 { background: radial-gradient(circle, #ffc7bb 0%, transparent 65%); bottom: -14vmax; left: 20vmax; animation-delay: -13s; animation-duration: 31s; }

        @keyframes drift {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33%      { transform: translate(8vmax, 6vmax) scale(1.12); }
            66%      { transform: translate(-6vmax, 4vmax) scale(0.94); }
        }

        .page {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
            position: relative;
        }

        /* ---------- Revelação escalonada ---------- */
        .reveal {
            opacity: 0;
            transform: translateY(26px) scale(0.98);
            animation: rise 0.7s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            animation-delay: calc(var(--i, 0) * 90ms + 120ms);
        }

        @keyframes rise {
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .card {
            background: var(--card);
            border: 1px solid rgba(16, 24, 40, 0.06);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transform-style: preserve-3d;
            transition: transform 0.25s ease, box-shadow 0.3s ease;
            will-change: transform;
        }

        /* Barra de progresso fixa no topo do card */
        .progress {
            position: sticky;
            top: 0;
            z-index: 5;
            height: 5px;
            background: rgba(173, 26, 5, 0.08);
            overflow: hidden;
        }

        .progress .bar {
            height: 100%;
            width: 0;
            border-radius: 0 4px 4px 0;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            box-shadow: 0 0 12px rgba(249, 130, 36, 0.6);
            transition: width 0.5s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .hero {
            padding: 20px 20px 12px;
            background: linear-gradient(180deg, #fff 0%, #fff8f6 100%);
            border-bottom: 1px solid var(--line);
        }

        .logo-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 72px;
            margin-bottom: 14px;
        }

        .logo-wrap img {
            max-width: min(220px, 70vw);
            max-height: 70px;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .title {
            text-align: center;
            margin: 0;
            font-size: clamp(1.25rem, 4vw, 1.9rem);
            line-height: 1.2;
        }

        .subtitle {
            text-align: center;
            margin: 8px auto 0;
            color: var(--muted);
            max-width: 540px;
            font-size: clamp(0.92rem, 2.8vw, 1rem);
            line-height: 1.5;
        }

        .content {
            padding: 18px;
        }

        .tech-box, .ticket-box, .question, .comment-box {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fff;
        }

        .tech-box {
            display: grid;
            grid-template-columns: 84px minmax(0, 1fr);
            gap: 14px;
            align-items: center;
            padding: 14px;
            margin-bottom: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #fafafa 100%);
        }

        .avatar, .avatar img {
            width: 84px;
            height: 84px;
            border-radius: 50%;
        }

        .avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 2rem;
            font-weight: 700;
            overflow: hidden;
            border: 1px solid rgba(173, 26, 5, 0.1);
            position: relative;
            animation: float 5s ease-in-out infinite;
        }

        /* Anel giratório ao redor do avatar */
        .avatar::before {
            content: "";
            position: absolute;
            inset: -5px;
            border-radius: 50%;
            padding: 3px;
            background: conic-gradient(from 0deg, var(--primary), var(--accent), #ffd27a, var(--primary));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask-composite: exclude;
            animation: spin 6s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-6px); }
        }

        .avatar img {
            display: block;
            object-fit: cover;
        }

        .tech-label {
            color: var(--muted);
            font-size: 0.86rem;
            margin-bottom: 4px;
        }

        .tech-name {
            font-size: clamp(1rem, 3vw, 1.25rem);
            font-weight: 700;
            line-height: 1.2;
        }

        .tech-role {
            color: var(--muted);
            margin-top: 4px;
            font-size: 0.95rem;
        }

        .ticket-box {
            padding: 14px;
            margin-bottom: 18px;
            background: #fbfcfc;
        }

        .ticket-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .item span {
            display: block;
            color: var(--muted);
            font-size: 0.82rem;
            margin-bottom: 5px;
        }

        .item strong {
            display: block;
            font-size: 0.95rem;
            line-height: 1.45;
            word-break: break-word;
        }

        form {
            display: grid;
            gap: 14px;
        }

        .question {
            padding: 16px;
        }

        .question h3 {
            margin: 0 0 12px;
            font-size: 1rem;
            line-height: 1.35;
        }

        .radios {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .choice {
            position: relative;
            flex: 1 1 140px;
            min-width: 120px;
        }

        .choice input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .choice label {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 50px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #fff;
            cursor: pointer;
            font-weight: 700;
            transition: transform 0.18s ease, background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .choice label:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(16, 24, 40, 0.08); }
        .choice label:active { transform: scale(0.97); }

        .choice input:checked + label {
            background: var(--primary-soft);
            border-color: rgba(173, 26, 5, 0.35);
            color: var(--primary);
            animation: pop 0.4s cubic-bezier(0.22, 1.4, 0.4, 1);
        }

        @keyframes pop {
            0% { transform: scale(1); }
            45% { transform: scale(1.06); }
            100% { transform: scale(1); }
        }

        .rating-buttons {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
        }

        .rating {
            position: relative;
        }

        .rating input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .rating label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 92px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            cursor: pointer;
            transition: transform 0.2s cubic-bezier(0.22, 1.4, 0.4, 1), background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
            user-select: none;
        }

        .rating label:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 22px rgba(16, 24, 40, 0.1);
        }

        .rating label:hover .emoji { transform: scale(1.25) rotate(-6deg); }

        .rating .emoji {
            font-size: clamp(1.5rem, 6vw, 2rem);
            line-height: 1;
            transition: transform 0.25s cubic-bezier(0.22, 1.4, 0.4, 1);
        }

        .rating input:checked + label .emoji {
            animation: bounce 0.6s cubic-bezier(0.22, 1.4, 0.4, 1);
        }

        @keyframes bounce {
            0% { transform: scale(1); }
            30% { transform: scale(1.4) rotate(8deg); }
            60% { transform: scale(0.9) rotate(-4deg); }
            100% { transform: scale(1.15); }
        }

        /* Dim nos emojis não selecionados quando há uma escolha */
        .rating-buttons.has-choice .rating input:not(:checked) + label { opacity: 0.5; }
        .rating-buttons.has-choice .rating input:not(:checked) + label:hover { opacity: 1; }

        /* Banner de feedback dinâmico */
        .mood {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 12px;
            min-height: 28px;
            font-weight: 700;
            color: var(--primary);
            opacity: 0;
            transform: translateY(6px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .mood.show { opacity: 1; transform: translateY(0); }
        .mood .mood-emoji { font-size: 1.4rem; }

        .rating .note {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--muted);
        }

        .rating input:checked + label {
            background: var(--primary-soft);
            border-color: rgba(173, 26, 5, 0.35);
            transform: translateY(-1px);
        }

        .comment-box {
            padding: 16px;
        }

        .comment-box h3 {
            margin: 0 0 10px;
            font-size: 1rem;
        }

        textarea {
            width: 100%;
            min-height: 120px;
            border-radius: 14px;
            border: 1px solid var(--line);
            padding: 14px;
            resize: vertical;
            font: inherit;
            color: var(--text);
            background: #fff;
        }

        textarea:focus {
            outline: none;
            border-color: rgba(173, 26, 5, 0.4);
            box-shadow: 0 0 0 4px rgba(173, 26, 5, 0.08);
        }

        .hint {
            margin-top: 8px;
            color: var(--muted);
            font-size: 0.86rem;
        }

        .submit {
            position: relative;
            width: 100%;
            min-height: 54px;
            border: 0;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 50%, var(--primary) 100%);
            background-size: 220% 100%;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            overflow: hidden;
            box-shadow: 0 10px 24px rgba(173, 26, 5, 0.18);
            transition: transform 0.18s ease, box-shadow 0.25s ease, background-position 0.6s ease;
            /* Fade-in de entrada (só opacidade, sem travar o transform do hover)
               + brilho contínuo. */
            opacity: 0;
            animation: fade-in-btn 0.6s ease 0.8s forwards, shine 6s linear infinite;
        }

        @keyframes fade-in-btn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes shine {
            0% { background-position: 0% 0; }
            100% { background-position: 220% 0; }
        }

        .submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(173, 26, 5, 0.28);
        }

        .submit:active { transform: translateY(-1px) scale(0.99); }

        .submit .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.45);
            transform: translate(-50%, -50%) scale(0);
            animation: ripple 0.6s ease-out forwards;
            pointer-events: none;
        }

        @keyframes ripple {
            to { transform: translate(-50%, -50%) scale(24); opacity: 0; }
        }

        .submit.loading { pointer-events: none; }
        .submit.loading .label { visibility: hidden; }

        .submit .spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 22px;
            height: 22px;
            margin: -11px 0 0 -11px;
            border: 3px solid rgba(255, 255, 255, 0.4);
            border-top-color: #fff;
            border-radius: 50%;
            display: none;
            animation: spin 0.7s linear infinite;
        }

        .submit.loading .spinner { display: block; }

        /* ---------- Confete ---------- */
        #confetti {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 50;
        }

        /* Respeita quem prefere menos movimento */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.001ms !important;
            }
            .reveal { opacity: 1; transform: none; }
            .submit { opacity: 1; }
        }

        .footer {
            text-align: center;
            color: var(--muted);
            font-size: 0.84rem;
            margin-top: 14px;
            line-height: 1.45;
        }

        @media (max-width: 640px) {
            body {
                padding: 10px;
            }

            .hero {
                padding: 16px 16px 10px;
            }

            .content {
                padding: 14px;
            }

            .tech-box {
                grid-template-columns: 1fr;
                text-align: center;
                justify-items: center;
            }

            .ticket-grid {
                grid-template-columns: 1fr;
            }

            .rating-buttons {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 420px) {
            .rating-buttons {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .choice {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <canvas id="bg-canvas"></canvas>
    <div class="aurora" aria-hidden="true">
        <span class="b1"></span>
        <span class="b2"></span>
        <span class="b3"></span>
    </div>
    <canvas id="confetti" aria-hidden="true"></canvas>

    <div class="page">
        <div class="card" id="card">
            <div class="progress" aria-hidden="true"><div class="bar" id="progress-bar"></div></div>
            <div class="hero reveal" style="--i:0">
                <div class="logo-wrap">
                    <img src="<?= h(COMPANY_LOGO) ?>" alt="Logo da empresa">
                </div>
                <h1 class="title">Pesquisa de satisfação</h1>
                <p class="subtitle">Sua avaliação é rápida e ajuda a melhorar continuamente o atendimento prestado.</p>
            </div>

            <div class="content">
                <div class="tech-box reveal" style="--i:1">
                    <div class="avatar">
                        <?php if ($foto_url): ?>
                            <img src="<?= h($foto_url) ?>" alt="Foto do técnico">
                        <?php else: ?>
                            <?= h($inicial) ?>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="tech-label">Técnico avaliado</div>
                        <div class="tech-name"><?= h($tecnico !== '' ? $tecnico : 'Técnico não identificado') ?></div>
                        <div class="tech-role"><?= h($cargo !== '' ? $cargo : 'Cargo não informado') ?></div>
                    </div>
                </div>

                <div class="ticket-box reveal" style="--i:2">
                    <div class="ticket-grid">
                        <div class="item">
                            <span>Chamado</span>
                            <strong><?= h($ticket_id) ?></strong>
                        </div>
                        <div class="item">
                            <span>Título</span>
                            <strong><?= h($ticket_name) ?></strong>
                        </div>
                        <div class="item">
                            <span>Requerente</span>
                            <strong><?= h($requerente !== '' ? $requerente : 'Requerente não identificado') ?></strong>
                        </div>
                        <div class="item">
                            <span>Abertura</span>
                            <strong><?= h(formatDataBR($ticket_createdate)) ?></strong>
                        </div>
                        <div class="item">
                            <span>Solução</span>
                            <strong><?= h(formatDataBR($ticket_solvedate)) ?></strong>
                        </div>
                    </div>
                </div>

                <form method="POST" action="salvar_pesquisa.php">
                    <input type="hidden" name="ticket_id" value="<?= h($ticket_id) ?>">
                    <input type="hidden" name="ticket_name" value="<?= h($ticket_name) ?>">
                    <input type="hidden" name="ticket_createdate" value="<?= h($ticket_createdate) ?>">
                    <input type="hidden" name="ticket_solvedate" value="<?= h($ticket_solvedate) ?>">
                    <input type="hidden" name="tecnico" value="<?= h($tecnico) ?>">
                    <input type="hidden" name="cargo" value="<?= h($cargo) ?>">
                    <input type="hidden" name="requerente" value="<?= h($requerente) ?>">

                    <div class="question reveal" style="--i:3">
                        <h3>Seu chamado foi solucionado?</h3>
                        <div class="radios">
                            <div class="choice">
                                <input type="radio" id="solucao_sim" name="solucao_chamado" value="Sim" required>
                                <label for="solucao_sim">Sim</label>
                            </div>
                            <div class="choice">
                                <input type="radio" id="solucao_nao" name="solucao_chamado" value="Não">
                                <label for="solucao_nao">Não</label>
                            </div>
                        </div>
                    </div>

                    <div class="question reveal" style="--i:4">
                        <h3>A solução apresentada foi satisfatória?</h3>
                        <div class="radios">
                            <div class="choice">
                                <input type="radio" id="satisfacao_sim" name="satisfacao" value="Sim" required>
                                <label for="satisfacao_sim">Sim</label>
                            </div>
                            <div class="choice">
                                <input type="radio" id="satisfacao_nao" name="satisfacao" value="Não">
                                <label for="satisfacao_nao">Não</label>
                            </div>
                        </div>
                    </div>

                    <div class="question reveal" style="--i:5">
                        <h3>Como você avalia este atendimento?</h3>
                        <div class="rating-buttons" id="rating-buttons">
                            <div class="rating">
                                <input type="radio" id="rate1" name="avaliacao" value="1" required>
                                <label for="rate1"><span class="emoji">😠</span><span class="note">1</span></label>
                            </div>
                            <div class="rating">
                                <input type="radio" id="rate2" name="avaliacao" value="2">
                                <label for="rate2"><span class="emoji">😟</span><span class="note">2</span></label>
                            </div>
                            <div class="rating">
                                <input type="radio" id="rate3" name="avaliacao" value="3">
                                <label for="rate3"><span class="emoji">😐</span><span class="note">3</span></label>
                            </div>
                            <div class="rating">
                                <input type="radio" id="rate4" name="avaliacao" value="4">
                                <label for="rate4"><span class="emoji">😀</span><span class="note">4</span></label>
                            </div>
                            <div class="rating">
                                <input type="radio" id="rate5" name="avaliacao" value="5">
                                <label for="rate5"><span class="emoji">😁</span><span class="note">5</span></label>
                            </div>
                        </div>
                        <div class="mood" id="mood" aria-live="polite">
                            <span class="mood-emoji"></span><span class="mood-text"></span>
                        </div>
                    </div>

                    <div class="comment-box reveal" style="--i:6">
                        <h3>Crítica, elogio ou sugestão</h3>
                        <textarea name="comentario" placeholder="Escreva aqui sua opinião sobre o atendimento."></textarea>
                        <div class="hint">Campo opcional.</div>
                    </div>

                    <button class="submit" type="submit" id="submit-btn">
                        <span class="label">Enviar avaliação</span>
                        <span class="spinner" aria-hidden="true"></span>
                    </button>
                </form>

                <div class="footer reveal" style="--i:8">
                    &copy; 2026 Consórcio Monto Mendes Júnior. Todos os direitos reservados.
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        "use strict";
        var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

        /* ---------- Fundo: rede de partículas ---------- */
        (function particleBackground() {
            var canvas = document.getElementById("bg-canvas");
            if (!canvas || reduceMotion) { if (canvas) canvas.style.display = "none"; return; }
            var ctx = canvas.getContext("2d");
            var w, h, dpr, points = [];

            function resize() {
                dpr = Math.min(window.devicePixelRatio || 1, 2);
                w = canvas.width = Math.floor(window.innerWidth * dpr);
                h = canvas.height = Math.floor(window.innerHeight * dpr);
                canvas.style.width = window.innerWidth + "px";
                canvas.style.height = window.innerHeight + "px";
                var target = Math.round((window.innerWidth * window.innerHeight) / 26000);
                target = Math.max(28, Math.min(90, target));
                points = [];
                for (var i = 0; i < target; i++) {
                    points.push({
                        x: Math.random() * w,
                        y: Math.random() * h,
                        vx: (Math.random() - 0.5) * 0.28 * dpr,
                        vy: (Math.random() - 0.5) * 0.28 * dpr,
                        r: (Math.random() * 1.6 + 0.8) * dpr
                    });
                }
            }

            var mouse = { x: -9999, y: -9999 };
            window.addEventListener("mousemove", function (e) { mouse.x = e.clientX * dpr; mouse.y = e.clientY * dpr; });
            window.addEventListener("mouseout", function () { mouse.x = mouse.y = -9999; });

            function tick() {
                ctx.clearRect(0, 0, w, h);
                var linkDist = 130 * dpr;
                for (var i = 0; i < points.length; i++) {
                    var p = points[i];
                    p.x += p.vx; p.y += p.vy;
                    if (p.x < 0 || p.x > w) p.vx *= -1;
                    if (p.y < 0 || p.y > h) p.vy *= -1;

                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                    ctx.fillStyle = "rgba(173, 26, 5, 0.35)";
                    ctx.fill();

                    for (var j = i + 1; j < points.length; j++) {
                        var q = points[j];
                        var dx = p.x - q.x, dy = p.y - q.y;
                        var dist = Math.sqrt(dx * dx + dy * dy);
                        if (dist < linkDist) {
                            ctx.beginPath();
                            ctx.moveTo(p.x, p.y);
                            ctx.lineTo(q.x, q.y);
                            ctx.strokeStyle = "rgba(249, 130, 36," + (0.16 * (1 - dist / linkDist)) + ")";
                            ctx.lineWidth = dpr;
                            ctx.stroke();
                        }
                    }

                    var mdx = p.x - mouse.x, mdy = p.y - mouse.y;
                    var mdist = Math.sqrt(mdx * mdx + mdy * mdy);
                    if (mdist < linkDist * 1.6) {
                        ctx.beginPath();
                        ctx.moveTo(p.x, p.y);
                        ctx.lineTo(mouse.x, mouse.y);
                        ctx.strokeStyle = "rgba(173, 26, 5," + (0.22 * (1 - mdist / (linkDist * 1.6))) + ")";
                        ctx.lineWidth = dpr;
                        ctx.stroke();
                    }
                }
                requestAnimationFrame(tick);
            }
            resize();
            window.addEventListener("resize", resize);
            requestAnimationFrame(tick);
        })();

        /* ---------- Tilt 3D no card ---------- */
        (function tilt() {
            var card = document.getElementById("card");
            if (!card || reduceMotion || window.matchMedia("(pointer: coarse)").matches) return;
            var raf = null;
            card.addEventListener("mousemove", function (e) {
                var rect = card.getBoundingClientRect();
                var px = (e.clientX - rect.left) / rect.width - 0.5;
                var py = (e.clientY - rect.top) / rect.height - 0.5;
                if (raf) cancelAnimationFrame(raf);
                raf = requestAnimationFrame(function () {
                    card.style.transform = "perspective(1200px) rotateX(" + (-py * 3) + "deg) rotateY(" + (px * 3) + "deg)";
                });
            });
            card.addEventListener("mouseleave", function () {
                if (raf) cancelAnimationFrame(raf);
                card.style.transform = "perspective(1200px) rotateX(0) rotateY(0)";
            });
        })();

        /* ---------- Barra de progresso ---------- */
        (function progress() {
            var bar = document.getElementById("progress-bar");
            var form = document.querySelector("form");
            if (!bar || !form) return;
            var groups = ["solucao_chamado", "satisfacao", "avaliacao"];
            function update() {
                var done = 0;
                groups.forEach(function (name) {
                    if (form.querySelector('input[name="' + name + '"]:checked')) done++;
                });
                bar.style.width = (done / groups.length * 100) + "%";
            }
            form.addEventListener("change", update);
            update();
        })();

        /* ---------- Feedback de humor na avaliação ---------- */
        (function mood() {
            var box = document.getElementById("rating-buttons");
            var mood = document.getElementById("mood");
            if (!box || !mood) return;
            var emojiEl = mood.querySelector(".mood-emoji");
            var textEl = mood.querySelector(".mood-text");
            var map = {
                "1": { e: "😠", t: "Sentimos muito. Vamos melhorar." },
                "2": { e: "😟", t: "Obrigado — vamos rever isso." },
                "3": { e: "😐", t: "Anotado! Buscamos evoluir." },
                "4": { e: "😀", t: "Que bom! Ficamos felizes." },
                "5": { e: "😁", t: "Sensacional! Muito obrigado!" }
            };
            box.addEventListener("change", function (e) {
                var val = e.target && e.target.value;
                if (!map[val]) return;
                box.classList.add("has-choice");
                emojiEl.textContent = map[val].e;
                textEl.textContent = map[val].t;
                mood.classList.remove("show");
                void mood.offsetWidth;
                mood.classList.add("show");
                if (val === "4" || val === "5") burst();
            });
        })();

        /* ---------- Confete ---------- */
        var burst = (function confetti() {
            var canvas = document.getElementById("confetti");
            if (!canvas || reduceMotion) return function () {};
            var ctx = canvas.getContext("2d");
            var dpr = Math.min(window.devicePixelRatio || 1, 2);
            var parts = [];
            var colors = ["#ad1a05", "#f98224", "#ffd27a", "#ff6b4a", "#2ecc71"];
            function fit() {
                canvas.width = window.innerWidth * dpr;
                canvas.height = window.innerHeight * dpr;
                canvas.style.width = window.innerWidth + "px";
                canvas.style.height = window.innerHeight + "px";
            }
            fit();
            window.addEventListener("resize", fit);
            var running = false;
            function loop() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                for (var i = parts.length - 1; i >= 0; i--) {
                    var p = parts[i];
                    p.vy += 0.12 * dpr;
                    p.x += p.vx; p.y += p.vy; p.rot += p.vr;
                    p.life--;
                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate(p.rot);
                    ctx.globalAlpha = Math.max(0, p.life / 60);
                    ctx.fillStyle = p.color;
                    ctx.fillRect(-p.s / 2, -p.s / 2, p.s, p.s * 0.6);
                    ctx.restore();
                    if (p.life <= 0 || p.y > canvas.height + 40) parts.splice(i, 1);
                }
                if (parts.length) requestAnimationFrame(loop);
                else { running = false; ctx.clearRect(0, 0, canvas.width, canvas.height); }
            }
            return function (originX, originY) {
                var cx = (originX != null ? originX : window.innerWidth / 2) * dpr;
                var cy = (originY != null ? originY : window.innerHeight / 2.4) * dpr;
                for (var i = 0; i < 90; i++) {
                    var a = Math.random() * Math.PI * 2;
                    var sp = (Math.random() * 6 + 3) * dpr;
                    parts.push({
                        x: cx, y: cy,
                        vx: Math.cos(a) * sp,
                        vy: Math.sin(a) * sp - 4 * dpr,
                        s: (Math.random() * 8 + 5) * dpr,
                        color: colors[(Math.random() * colors.length) | 0],
                        rot: Math.random() * Math.PI,
                        vr: (Math.random() - 0.5) * 0.3,
                        life: Math.random() * 30 + 55
                    });
                }
                if (!running) { running = true; requestAnimationFrame(loop); }
            };
        })();

        /* ---------- Botão: ripple + estado de envio ---------- */
        (function submitBtn() {
            var btn = document.getElementById("submit-btn");
            var form = document.querySelector("form");
            if (!btn || !form) return;
            btn.addEventListener("click", function (e) {
                if (reduceMotion) return;
                var rect = btn.getBoundingClientRect();
                var r = document.createElement("span");
                r.className = "ripple";
                r.style.left = (e.clientX - rect.left) + "px";
                r.style.top = (e.clientY - rect.top) + "px";
                r.style.width = r.style.height = Math.max(rect.width, rect.height) / 12 + "px";
                btn.appendChild(r);
                setTimeout(function () { r.remove(); }, 650);
            });
            form.addEventListener("submit", function () {
                // Deixa o formulário enviar normalmente; só mostra o feedback visual.
                btn.classList.add("loading");
            });
        })();
    })();
    </script>
</body>
</html>
