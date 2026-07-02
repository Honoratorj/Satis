<?php
require_once __DIR__ . '/config.php';
$conn = db();
$ticket_id = $_GET['ticket_id'] ?? '';
$tecnico = $_GET['tecnico'] ?? '';
$requerente = $_GET['requerente'] ?? '';
$solucao_chamado = $_GET['solucao_chamado'] ?? '';
$satisfacao = $_GET['satisfacao'] ?? '';
$dentro_prazo = $_GET['dentro_prazo'] ?? '';
$avaliacao = $_GET['avaliacao'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$sql = "SELECT ticket_id, ticket_name, requerente, tecnico, cargo, solucao_chamado, satisfacao, dentro_prazo, avaliacao, comentario, created_at FROM pesquisa_satisfacao WHERE 1=1";
$params = [];
$types = '';
if ($ticket_id !== '') { $sql .= " AND ticket_id = ?"; $params[] = $ticket_id; $types .= 'i'; }
if ($tecnico !== '') { $sql .= " AND tecnico LIKE ?"; $params[] = '%' . $tecnico . '%'; $types .= 's'; }
if ($requerente !== '') { $sql .= " AND requerente LIKE ?"; $params[] = '%' . $requerente . '%'; $types .= 's'; }
if ($solucao_chamado !== '') { $sql .= " AND solucao_chamado = ?"; $params[] = $solucao_chamado; $types .= 's'; }
if ($satisfacao !== '') { $sql .= " AND satisfacao = ?"; $params[] = $satisfacao; $types .= 's'; }
if ($dentro_prazo !== '') { $sql .= " AND dentro_prazo = ?"; $params[] = $dentro_prazo; $types .= 's'; }
if ($avaliacao !== '') { $sql .= " AND avaliacao <= ?"; $params[] = $avaliacao; $types .= 'i'; }
if ($data_inicio !== '' && $data_fim !== '') { $sql .= " AND created_at BETWEEN ? AND ?"; $params[] = $data_inicio . ' 00:00:00'; $params[] = $data_fim . ' 23:59:59'; $types .= 'ss'; }
$sql .= " ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Relatório de Pesquisa de Satisfação</title><style>body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px}.container{max-width:1400px;margin:0 auto}h1{text-align:center;color:#333;margin-bottom:24px}.filter-container,.table-wrap{background:#fff;padding:20px;border-radius:10px;box-shadow:0 0 12px rgba(0,0,0,.08);margin-bottom:24px}.filter-container form{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}label{display:block;font-size:14px;color:#333;margin-bottom:6px;font-weight:700}input,select,button{width:100%;padding:10px;font-size:14px;border:1px solid #ddd;border-radius:6px}button{background:#F38828;color:#fff;border:0;cursor:pointer;font-weight:700}button:hover{background:#AD1A05}table{width:100%;border-collapse:collapse}th,td{border:1px solid #e5e5e5;padding:10px;text-align:left;vertical-align:top}th{background:#AD1A05;color:#fff}tr:nth-child(even){background:#fafafa}.empty-message{text-align:center;padding:20px;color:#777}.comentario{min-width:260px;white-space:pre-wrap}</style></head><body><div class="container"><h1>Relatório de Pesquisa de Satisfação</h1><div class="filter-container"><form method="GET"><div><label for="ticket_id">ID do Chamado</label><input type="number" name="ticket_id" id="ticket_id" value="<?= e($ticket_id) ?>"></div><div><label for="tecnico">Técnico</label><input type="text" name="tecnico" id="tecnico" value="<?= e($tecnico) ?>"></div><div><label for="requerente">Requerente</label><input type="text" name="requerente" id="requerente" value="<?= e($requerente) ?>"></div><div><label for="solucao_chamado">Chamado solucionado</label><select name="solucao_chamado" id="solucao_chamado"><option value="">--Selecione--</option><option value="Sim" <?= $solucao_chamado === 'Sim' ? 'selected' : '' ?>>Sim</option><option value="Não" <?= $solucao_chamado === 'Não' ? 'selected' : '' ?>>Não</option></select></div><div><label for="satisfacao">Satisfação</label><select name="satisfacao" id="satisfacao"><option value="">--Selecione--</option><option value="Sim" <?= $satisfacao === 'Sim' ? 'selected' : '' ?>>Sim</option><option value="Não" <?= $satisfacao === 'Não' ? 'selected' : '' ?>>Não</option></select></div><div><label for="dentro_prazo">Dentro do prazo</label><select name="dentro_prazo" id="dentro_prazo"><option value="">--Selecione--</option><option value="Sim" <?= $dentro_prazo === 'Sim' ? 'selected' : '' ?>>Sim</option><option value="Não" <?= $dentro_prazo === 'Não' ? 'selected' : '' ?>>Não</option></select></div><div><label for="avaliacao">Avaliação até</label><input type="number" name="avaliacao" id="avaliacao" min="1" max="5" value="<?= e($avaliacao) ?>"></div><div><label for="data_inicio">Data de início</label><input type="date" name="data_inicio" id="data_inicio" value="<?= e($data_inicio) ?>"></div><div><label for="data_fim">Data de fim</label><input type="date" name="data_fim" id="data_fim" value="<?= e($data_fim) ?>"></div><div style="display:flex;align-items:end;"><button type="submit">Filtrar</button></div></form></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Título</th><th>Requerente</th><th>Técnico</th><th>Cargo</th><th>Solucionado</th><th>Satisfação</th><th>Prazo</th><th>Nota</th><th>Comentário</th><th>Data</th></tr></thead><tbody><?php if ($result->num_rows > 0): ?><?php while ($row = $result->fetch_assoc()): ?><tr><td><?= e($row['ticket_id']) ?></td><td><?= e($row['ticket_name']) ?></td><td><?= e($row['requerente']) ?></td><td><?= e($row['tecnico']) ?></td><td><?= e($row['cargo']) ?></td><td><?= e($row['solucao_chamado']) ?></td><td><?= e($row['satisfacao']) ?></td><td><?= e($row['dentro_prazo']) ?></td><td><?= e($row['avaliacao']) ?></td><td class="comentario"><?= e($row['comentario']) ?></td><td><?= e($row['created_at']) ?></td></tr><?php endwhile; ?><?php else: ?><tr><td colspan="11" class="empty-message">Nenhum registro encontrado.</td></tr><?php endif; ?></tbody></table></div></div></body></html><?php $stmt->close(); $conn->close(); ?>
