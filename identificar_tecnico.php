<?php
require_once 'config.php';

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
} catch (mysqli_sql_exception $e) {
    die("Erro conexão: " . $e->getMessage());
}

$sql = "SELECT ticket_id FROM pesquisa_satisfacao WHERE tecnico IS NULL";
$result = $mysqli->query($sql);

while ($row = $result->fetch_assoc()) {

    $ticket_id = $row['ticket_id'];

    $sql_users = "SELECT users_id FROM glpi_tickets_users WHERE tickets_id = ? AND type = 2";
    $stmt = $mysqli->prepare($sql_users);
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $stmt->bind_result($users_id);
    $stmt->fetch();
    $stmt->close();

    if ($users_id) {

        // 🔥 ALTERADO AQUI (nome completo)
        $sql_tech = "
        SELECT 
            CASE
                WHEN TRIM(CONCAT(COALESCE(firstname, ''), ' ', COALESCE(realname, ''))) <> ''
                THEN TRIM(CONCAT(COALESCE(firstname, ''), ' ', COALESCE(realname, '')))
                ELSE name
            END AS nome_completo
        FROM glpi_users 
        WHERE id = ?
        ";

        $stmt_tech = $mysqli->prepare($sql_tech);
        $stmt_tech->bind_param("i", $users_id);
        $stmt_tech->execute();
        $stmt_tech->bind_result($tecnico);
        $stmt_tech->fetch();
        $stmt_tech->close();

        if ($tecnico) {

            $sql_update = "UPDATE pesquisa_satisfacao SET tecnico = ? WHERE ticket_id = ?";
            $stmt_update = $mysqli->prepare($sql_update);
            $stmt_update->bind_param("si", $tecnico, $ticket_id);
            $stmt_update->execute();
            $stmt_update->close();

            echo "Ticket $ticket_id atualizado<br>";
        }
    }
}

$mysqli->close();
?>
