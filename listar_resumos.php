<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Resumos Guardados</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        td { vertical-align: top; }
    </style>
</head>
<body>

    <h1>Histórico de Resumos</h1>

    <?php
    $dbHost = 'localhost';
    $dbUser = 'root';
    $dbPass = '';
    $dbName = 'resumidor_db';

    try {
        $dsn = "mysql:host=$dbHost;dbname=$dbName";
        $pdo = new PDO($dsn, $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Prepara e executa a consulta de forma segura
        $sql = "SELECT id, resumo_gerado, data_criacao FROM resumos ORDER BY id DESC";
        $stmt = $pdo->query($sql);

        // Verifica se encontrou algum registo
        if ($stmt->rowCount() > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Resumo Gerado</th><th>Data</th></tr>";
            
            // Percorre os resultados e exibe-os
            while($linha = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . $linha['id'] . "</td>";
                // Usamos htmlspecialchars para exibir os dados de forma segura
                echo "<td>" . nl2br(htmlspecialchars($linha['resumo_gerado'])) . "</td>";
                echo "<td>" . $linha['data_criacao'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "Nenhum resumo encontrado.";
        }

    } catch (PDOException $e) {
        die("ERRO: Não foi possível ler os dados. " . $e->getMessage());
    }

    // Fecha a conexão
    $pdo = null;

    ?>

</body>
</html>
