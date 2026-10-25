<?php
require_once('session.php');
require_once('bdd.php');

$led = $_POST['led_mode'] ?? 'off';
$message = "";
$recentPhoto = null;

// recovery of the last photo
$stmtLast = $pdo->prepare("SELECT * FROM PHOTO WHERE userID = ? AND supr != 'oui' ORDER BY dateP DESC, heureP DESC LIMIT 1");
$stmtLast->execute([$_SESSION['user_id']]);
$recentPhoto = $stmtLast->fetch(PDO::FETCH_ASSOC);
$stmtLast->closeCursor();

// deletion of the last photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_photo'])) {
    $photoID = intval($_POST['photoID']);

    $stmt = $pdo->prepare("UPDATE PHOTO SET supr = 'oui' WHERE photoID = ?");
    $stmt->execute([$photoID]);
    $stmt->closeCursor();

    $stmtLog = $pdo->prepare("INSERT INTO LOGS (userID, dateL, heureL, evenement, plus) VALUES (?, date('now'), time('now'), ?, ?)");
    $stmtLog->execute([$_SESSION['user_id'], 'Information', "Photo ID $photoID mise à la corbeille"]);
    $stmtLog->closeCursor();

    header("Location: mode-photo.php?message=photo_supprimee");
    exit;
}

// photo taking (simulation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prendre_photo'])) {
    $file_name = "photo_" . date("Ymd_His") . ".jpg";

    // writing it in commande.json
    $commande = [
        "action" => "prendre_photo",
        "nom" => $file_name,
        "led" => $led
    ];
    file_put_contents("commande.json", json_encode($commande, JSON_PRETTY_PRINT));

    // Insertion in data base
    $stmt = $pdo->prepare("INSERT INTO PHOTO (titre, chemin, dateP, heureP, userID, supr) VALUES (?, ?, date('now'), time('now'), ?, 'non')");
    $stmt->execute([$file_name, $file_name, $_SESSION['user_id']]);
    $stmt->closeCursor();

    // Creation of the log of the action
    $stmtLog = $pdo->prepare("INSERT INTO LOGS (userID, dateL, heureL, evenement, plus) VALUES (?, date('now'), time('now'), ?, ?)");
    $stmtLog->execute([$_SESSION['user_id'], 'Information', "Photo prise : $file_name"]);
    $stmtLog->closeCursor();

    header("Location: mode-photo.php?message=photo_ok");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mode Photo - PHOTO_ATB</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .photo-zone {
            width: 320px;
            height: 240px;
            border: 3px dashed #00cce7;
            margin: 20px auto;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #00cce7;
            font-weight: bold;
            overflow: hidden;
        }

        .photo-zone img {
            max-width: 100%;
            max-height: 100%;
        }

        .center {
            text-align: center;
        }

        .center form {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
        }

        .center select {
            padding: 8px;
            background-color: #1e1e1e;
            color: #f0f0f0;
            border: 1px solid #444;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<div class="menu">
    <h3>Menu</h3>
    <ul>
        <?php if ($_SESSION['role'] !== 'invite') : ?>
            <li><a href="home.php">Accueil</a></li>
            <li><a href="visionnage.php">Visionnage</a></li>
            <?php if ($_SESSION['role'] === 'Administrateur' || $_SESSION['role'] === 'Super Administrateur') : ?>
                <li><a href="back-office.php">Back Office</a></li>
            <?php endif; ?>
        <?php endif; ?>
        <li><a href="mode-photo.php">Mode Photo</a></li>
        <li><a href="logout.php">Déconnexion</a></li>
    </ul>
</div>

<div class="container center">
    <h2>Mode Photo</h2>

    <div class="photo-zone">
        <?php if ($recentPhoto): ?>
            <img src="assets/images/<?= htmlspecialchars($recentPhoto['chemin']) ?>" alt="Dernière photo">
        <?php else: ?>
            Aperçu Caméra
        <?php endif; ?>
    </div>

    <form method="post">
        <input type="hidden" name="prendre_photo" value="1">
        <button type="submit">📸 Prendre une photo</button>
        <select name="led_mode">
            <option value="off" <?= $led === 'off' ? 'selected' : '' ?>>LED Off</option>
            <option value="on" <?= $led === 'on' ? 'selected' : '' ?>>LED On</option>
            <option value="auto" <?= $led === 'auto' ? 'selected' : '' ?>>LED Auto</option>
        </select>
    </form>

    <?php if ($recentPhoto): ?>
        <form method="post" onsubmit="return confirm('Envoyer cette photo à la corbeille ?');" style="margin-top: 10px;">
            <input type="hidden" name="supprimer_photo" value="1">
            <input type="hidden" name="photoID" value="<?= $recentPhoto['photoID'] ?>">
            <button type="submit">🗑️ Supprimer cette photo</button>
        </form>
    <?php endif; ?>

    <?php if (isset($_GET['message'])): ?>
        <p style="color: green; font-weight: bold; margin-top: 15px;">
            <?php
            if ($_GET['message'] === 'photo_ok') echo "✅ Photo prise avec succès.";
            if ($_GET['message'] === 'photo_supprimee') echo "🗑️ Photo supprimée.";
            ?>
        </p>
    <?php endif; ?>
</div>
</body>
</html>
