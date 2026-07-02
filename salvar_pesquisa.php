<?php
require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

function normalizeDateTime(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $formats = [
        'd-m-Y H:i',
        'd-m-Y H:i:s',
        'd/m/Y H:i',
        'd/m/Y H:i:s',
        'Y-m-d H:i',
        'Y-m-d H:i:s',
        'Y-m-d\TH:i',
        'Y-m-d\TH:i:s',
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        return date('Y-m-d H:i:s', $timestamp);
    }

    return null;
}

function fail(string $message): void
{
    die('Erro ao salvar a pesquisa: ' . $message);
}

// Exibida quando o chamado já possui uma avaliação registrada (a coluna
// ticket_id é UNIQUE, então só é possível responder a pesquisa uma vez).
function alreadyAnswered(): void
{
    http_response_code(409);
    header('Content-Type: text/html; charset=UTF-8');
    echo <<<'HTML'
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pesquisa já respondida</title>
<style>
    :root { --primary: #ad1a05; --accent: #f98224; }
    * { box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        display: flex; justify-content: center; align-items: center;
        min-height: 100vh; margin: 0; padding: 16px; text-align: center;
        background: radial-gradient(circle at top, #ffffff 0%, #f7f8fa 40%, #f3f5f7 100%);
    }
    .container {
        background: #fff; padding: 40px 32px; border-radius: 24px;
        box-shadow: 0 20px 50px rgba(16, 24, 40, 0.12);
        max-width: 420px; width: 100%;
        opacity: 0; transform: translateY(30px) scale(0.96);
        animation: rise 0.7s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }
    @keyframes rise { to { opacity: 1; transform: translateY(0) scale(1); } }
    .badge {
        width: 96px; height: 96px; margin: 0 auto 18px; border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--accent));
        display: flex; align-items: center; justify-content: center;
        font-size: 48px; color: #fff;
        box-shadow: 0 12px 28px rgba(173, 26, 5, 0.28);
        animation: pop 0.6s 0.25s cubic-bezier(0.22, 1.4, 0.4, 1) both;
    }
    @keyframes pop { 0% { transform: scale(0); } 60% { transform: scale(1.15); } 100% { transform: scale(1); } }
    h2 { color: var(--primary); margin: 0 0 8px; font-size: 1.5rem; }
    .message { margin-top: 6px; font-size: 1.05rem; color: #555; line-height: 1.5; }
    .footer { margin-top: 24px; font-size: 0.82rem; color: #999; }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation-duration: 0.001ms !important; }
        .container { opacity: 1; transform: none; }
    }
</style>
</head>
<body>
    <div class="container">
        <div class="badge" aria-hidden="true">✓</div>
        <h2>Este chamado já foi avaliado</h2>
        <div class="message">Nossos registros mostram que a pesquisa deste chamado já foi respondida. Agradecemos a sua participação!</div>
        <div class="footer">&copy; 2026 Consórcio Monto Mendes Júnior. Todos os direitos reservados.</div>
    </div>
</body>
</html>
HTML;
    exit;
}

$ticket_id = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$ticket_name = trim($_POST['ticket_name'] ?? '');
$ticket_createdate = normalizeDateTime($_POST['ticket_createdate'] ?? '');
$ticket_solvedate = normalizeDateTime($_POST['ticket_solvedate'] ?? '');
$solucao_chamado = trim($_POST['solucao_chamado'] ?? '');
$satisfacao = trim($_POST['satisfacao'] ?? '');
$avaliacao = isset($_POST['avaliacao']) ? (int)$_POST['avaliacao'] : 0;
$comentario = trim($_POST['comentario'] ?? '');
$tecnico = trim($_POST['tecnico'] ?? '');
$cargo = trim($_POST['cargo'] ?? '');
$requerente = trim($_POST['requerente'] ?? '');

if ($ticket_id <= 0 || $solucao_chamado === '' || $satisfacao === '' || $avaliacao <= 0) {
    fail('dados insuficientes.');
}

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    $available = [];
    $res = $conn->query("SHOW COLUMNS FROM pesquisa_satisfacao");
    while ($row = $res->fetch_assoc()) {
        $available[] = $row['Field'];
    }
    $res->close();

    $dataMap = [
        'ticket_id' => $ticket_id,
        'ticket_name' => $ticket_name,
        'ticket_createdate' => $ticket_createdate,
        'ticket_solvedate' => $ticket_solvedate,
        'solucao_chamado' => $solucao_chamado,
        'satisfacao' => $satisfacao,
        'avaliacao' => $avaliacao,
        'comentario' => $comentario,
        'tecnico' => $tecnico,
        'cargo' => $cargo,
        'requerente' => $requerente,
    ];

    $columns = [];
    $placeholders = [];
    $types = '';
    $values = [];

    foreach ($dataMap as $column => $value) {
        if (!in_array($column, $available, true)) {
            continue;
        }

        $columns[] = $column;
        $placeholders[] = '?';

        if (in_array($column, ['ticket_id', 'avaliacao'], true)) {
            $types .= 'i';
            $values[] = (int)$value;
        } else {
            $types .= 's';
            $values[] = $value === null ? null : (string)$value;
        }
    }

    if (!$columns) {
        fail('nenhuma coluna compatível foi encontrada na tabela.');
    }

    $sql = "INSERT INTO pesquisa_satisfacao (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
    $conn->close();
} catch (mysqli_sql_exception $e) {
    error_log('Pesquisa/salvar_pesquisa.php - ' . $e->getMessage());
    // 1062 = Duplicate entry: a coluna ticket_id é UNIQUE, então o chamado
    // já possui uma avaliação. Mostra mensagem amigável em vez do erro genérico.
    if ((int)$e->getCode() === 1062) {
        alreadyAnswered();
    }
    fail('não foi possível registrar a pesquisa no banco de dados.');
}

header('Location: sucesso.php');
exit;
