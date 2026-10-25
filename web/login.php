<?php
session_start(); # start a session
require_once('bdd.php');

$message = "";

// Log constants
const LOG_TYPE_INFO = 'Information';
const LOG_TYPE_WARN = 'Warning';
const LOG_TYPE_ALARM = 'Alarme';

// Centralized log function
function inLog($pdo, $userID, $type, $plus) {
    $stmt = $pdo->prepare("INSERT INTO LOGS (userID, dateL, heureL, evenement, plus) VALUES (?, date('now'), time('now'), ?, ?)");
    $stmt->execute([$userID, $type, $plus]);
    $stmt->closeCursor();
}

// Guest connection
if (isset($_POST['invite'])) {
    $stmt = $pdo->prepare("SELECT * FROM USER WHERE acID = ?");
    $stmt->execute(['Invite']);
    $guest = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if ($guest) {
        $_SESSION['user'] = 'Invite';
        $_SESSION['role'] = 'invite';
        $_SESSION['user_id'] = $guest['userID'];
        $_SESSION['last_activity'] = time();

        inLog($pdo, $guest['userID'], LOG_TYPE_INFO, "Connexion en tant qu'invité");

        header('Location: mode-photo.php');
        exit;
    } else {
        $message = "Utilisateur invité introuvable.";
    }
}

// Standard Connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && isset($_POST['mdp'])) {
    $login = $_POST['login'];
    $mdp = $_POST['mdp'];

    $stmt = $pdo->prepare("SELECT * FROM USER WHERE acID = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$user) {
        $message = "Identifiant incorrect.";
    } else {
        $bloque = $user['bloque'] ?? 'non';

        if ($bloque === 'oui' && $user['role'] !== 'Super Administrateur') {
            $message = "Compte bloqué. Contactez un administrateur.";
        } elseif (password_verify($mdp, $user['mdp'])) {
            try {
                $pdo->beginTransaction();

                $reset = $pdo->prepare("UPDATE USER SET tentatives = 0 WHERE userID = ?");
                $reset->execute([$user['userID']]);
                $reset->closeCursor();

                $_SESSION['user'] = $user['acID'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_id'] = $user['userID'];
                $_SESSION['last_activity'] = time();

                inLog($pdo, $user['userID'], LOG_TYPE_INFO, 'Connexion réussie via login');

                $pdo->commit();
                header('Location: home.php');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = "Erreur : " . $e->getMessage();
            }
        } else {
            try {
                $pdo->beginTransaction();

                $inc = $pdo->prepare("UPDATE USER SET tentatives = tentatives + 1 WHERE userID = ?");
                $inc->execute([$user['userID']]);
                $inc->closeCursor();

                $tent = $pdo->prepare("SELECT tentatives FROM USER WHERE userID = ?");
                $tent->execute([$user['userID']]);
                $tentatives = $tent->fetchColumn();
                $tent->closeCursor();

                if ($tentatives >= 3 && $user['role'] !== 'Super Administrateur') {
                    $lock = $pdo->prepare("UPDATE USER SET bloque = 'oui' WHERE userID = ?");
                    $lock->execute([$user['userID']]);
                    $lock->closeCursor();

                    inLog($pdo, $user['userID'], LOG_TYPE_ALARM, 'Compte bloqué après 3 tentatives échouées');

                    $message = "Compte bloqué après 3 tentatives.";
                } else {
                    $message = "Mot de passe incorrect. Tentative $tentatives/3.";
                    $plus = "Tentative échouée de connexion pour rôle : " . $user['role'];
                    inLog($pdo, $user['userID'], LOG_TYPE_WARN, $plus);
                }

                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = "Erreur : " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - PHOTO_ATB</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-container">
    <h2>Connexion à PHOTO_ATB</h2>

    <?php if (!empty($message)) : ?>
        <p class="error"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="login" placeholder="Identifiant" required>
        <input type="password" name="mdp" placeholder="Mot de passe" required>
        <button type="submit">Se connecter</button>
    </form>

    <form method="post" style="margin-top: 20px;">
        <button type="submit" name="invite">Connexion Invité</button>
    </form>
</div>
</body>
</html>
