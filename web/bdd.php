<?php
try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/data/BdD_Thales14_V3.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Delete the logs older than the configured retention period
    require_once(__DIR__ . '/config.php');
    $purge = $pdo->prepare("DELETE FROM LOGS WHERE dateL < date('now', ?)");
    $purge->execute(['-' . intval(LOG_RETENTION_MONTHS) . ' months']);
} catch (PDOException $e) {
    die("Erreur connexion BDD : " . $e->getMessage());
}
?>
