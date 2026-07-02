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
            background:
                radial-gradient(circle at top, #ffffff 0%, #f7f8fa 30%, var(--bg) 100%);
            color: var(--text);
            min-height: 100vh;
            padding: 16px;
        }

        .page {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
        }

        .card {
            background: var(--card);
            border: 1px solid rgba(16, 24, 40, 0.06);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
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
            transition: 0.2s ease;
        }

        .choice input:checked + label {
            background: var(--primary-soft);
            border-color: rgba(173, 26, 5, 0.35);
            color: var(--primary);
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
            transition: 0.2s ease;
            user-select: none;
        }

        .rating .emoji {
            font-size: clamp(1.5rem, 6vw, 2rem);
            line-height: 1;
        }

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
            width: 100%;
            min-height: 54px;
            border: 0;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(173, 26, 5, 0.18);
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
    <div class="page">
        <div class="card">
            <div class="hero">
                <div class="logo-wrap">
                    <img src="<?= h(COMPANY_LOGO) ?>" alt="Logo da empresa">
                </div>
                <h1 class="title">Pesquisa de satisfação</h1>
                <p class="subtitle">Sua avaliação é rápida e ajuda a melhorar continuamente o atendimento prestado.</p>
            </div>

            <div class="content">
                <div class="tech-box">
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

                <div class="ticket-box">
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

                    <div class="question">
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

                    <div class="question">
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

                    <div class="question">
                        <h3>Como você avalia este atendimento?</h3>
                        <div class="rating-buttons">
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
                    </div>

                    <div class="comment-box">
                        <h3>Crítica, elogio ou sugestão</h3>
                        <textarea name="comentario" placeholder="Escreva aqui sua opinião sobre o atendimento."></textarea>
                        <div class="hint">Campo opcional.</div>
                    </div>

                    <button class="submit" type="submit">Enviar avaliação</button>
                </form>

                <div class="footer">
                    &copy; 2026 Consórcio Monto Mendes Júnior. Todos os direitos reservados.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
