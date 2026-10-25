<?php
require_once('session.php');
require_once('bdd.php');

if (!isset($_GET['id'])) {
    die("Aucune photo spécifiée.");
}

$photoID = intval($_GET['id']);

$stmt = $pdo->prepare("
    SELECT P.*, U.acID
    FROM PHOTO P
    JOIN USER U ON P.userID = U.userID
    WHERE P.photoID = ? AND P.supr != 'oui'
");
$stmt->execute([$photoID]);
$photo = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor();

if (!$photo) {
    die("Photo introuvable.");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($photo['titre']) ?> - PHOTO_ATB</title>
    <style>
        body {
            background-color: #000;
            color: #f0f0f0;
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 20px;
        }
        img {
            max-width: 90%;
            max-height: 80vh;
            border: 4px solid #00cce7;
            border-radius: 10px;
        }
        .info {
            margin-top: 20px;
            font-size: 16px;
        }
        a {
            display: inline-block;
            margin-top: 25px;
            color: #87cefa;
            text-decoration: none;
            font-weight: bold;
        }
        a:hover {
            text-decoration: underline;
        }
        .download {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h2><?= htmlspecialchars($photo['titre']) ?></h2>
    <img src="assets/images/<?= htmlspecialchars($photo['chemin']) ?>" alt="Photo">
    
    <div class="info">
        <p><strong>Date :</strong> <?= htmlspecialchars($photo['dateP']) ?></p>
        <p><strong>Heure :</strong> <?= htmlspecialchars($photo['heureP']) ?></p>
        <p><strong>Utilisateur :</strong> <?= htmlspecialchars($photo['acID']) ?></p>
    </div>

    <div class="download">
        <a href="assets/images/<?= htmlspecialchars($photo['chemin']) ?>" download>Télécharger la photo 📥</a>
    </div>

    <a href="visionnage.php">← Retour au visionnage</a>
</body>
</html>
