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
    fail('não foi possível registrar a pesquisa no banco de dados.');
}

header('Location: sucesso.php');
exit;
