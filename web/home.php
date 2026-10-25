<?php
require_once('session.php');
require_once('bdd.php');

// heading guests to photo mode
if ($_SESSION['role'] === 'invite') {
    header('Location: mode-photo.php');
    exit;
}

// Filtres
$name_filtre = $_GET['nom'] ?? '';
$date_filtre = $_GET['date'] ?? '';
$hour_filtre = $_GET['heure'] ?? '';
$photo_id_filtre = $_GET['photoid'] ?? '';
$user_filtre = $_GET['utilisateur'] ?? '';

// Suppression logique
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_photo'])) {
    if (!in_array($_SESSION['role'], ['Opérateur', 'Administrateur', 'Super Administrateur'])) {
        exit("Accès refusé.");
    }

    $photoID = intval($_POST['photoID']);

    $stmt = $pdo->prepare("UPDATE PHOTO SET supr = 'oui' WHERE photoID = ?");
    $stmt->execute([$photoID]);
    $stmt->closeCursor();

    // Log
    $stmtLog = $pdo->prepare("INSERT INTO LOGS (userID, dateL, heureL, evenement, plus) VALUES (?, date('now'), time('now'), ?, ?)");
    $stmtLog->execute([$_SESSION['user_id'], 'Information', "Photo mise à la corbeille ID $photoID"]);
    $stmtLog->closeCursor();

    header("Location: home.php?message=photo_supprimee");
    exit;
}

// Requête BDD
$stmt = $pdo->prepare("
    SELECT P.*, U.acID
    FROM PHOTO P
    JOIN USER U ON P.userID = U.userID
    WHERE supr = 'non'
    ORDER BY dateP DESC, heureP DESC
    LIMIT 5
");
$stmt->execute();
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Application des filtres sur les 5 résultats
$photos = array_filter($photos, function ($photo) use ($name_filtre, $date_filtre, $hour_filtre, $photo_id_filtre, $user_filtre) {
    return stripos($photo['titre'], $name_filtre) !== false
        && stripos($photo['dateP'], $date_filtre) !== false
        && stripos($photo['heureP'], $hour_filtre) !== false
        && stripos($photo['photoID'], $photo_id_filtre) !== false
        && stripos($photo['acID'], $user_filtre) !== false;
});
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Accueil - PHOTO_ATB</title>
    <link rel="stylesheet" href="assets/css/style.css">
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

<div class="container">
    <?php if (isset($_GET['message']) && $_GET['message'] === 'photo_supprimee'): ?>
        <p style="color: green;">Photo supprimée avec succès.</p>
    <?php endif; ?>

    <h2>Bienvenue, <?= htmlspecialchars($_SESSION['user']) ?> (<?= $_SESSION['role'] ?>)</h2>
    <h3>Dernières photos (max 5)</h3>

    <form method="get">
        <input type="text" name="nom" placeholder="Titre" value="<?= htmlspecialchars($name_filtre) ?>">
        <input type="text" name="date" placeholder="Date (YYYY-MM-DD)" value="<?= htmlspecialchars($date_filtre) ?>">
        <input type="text" name="heure" placeholder="Heure (HH:MM)" value="<?= htmlspecialchars($hour_filtre) ?>">
        <input type="text" name="photoid" placeholder="ID Photo" value="<?= htmlspecialchars($photo_id_filtre) ?>">
        <input type="text" name="utilisateur" placeholder="Identifiant utilisateur" value="<?= htmlspecialchars($user_filtre) ?>">
        <button type="submit">🔍 Rechercher</button>
    </form>

    <table>
        <thead>
        <tr>
            <th>Miniature</th>
            <th>Date</th>
            <th>Heure</th>
            <th>ID Photo</th>
            <th>Titre</th>
            <th>Utilisateur</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($photos as $photo): ?>
            <?php if (isset($photo['chemin'], $photo['dateP'], $photo['photoID'], $photo['titre'], $photo['acID'], $photo['heureP'])): ?>
                <tr>
                    <td>
                        <a href="assets/images/<?= htmlspecialchars($photo['chemin']) ?>" download>
                            <img src="assets/images/<?= htmlspecialchars($photo['chemin']) ?>" width="60" alt="Miniature">
                        </a>
                    </td>
                    <td><?= htmlspecialchars($photo['dateP']) ?></td>
                    <td><?= htmlspecialchars($photo['heureP']) ?></td>
                    <td><?= htmlspecialchars($photo['photoID']) ?></td>
                    <td><?= htmlspecialchars($photo['titre']) ?></td>
                    <td><?= htmlspecialchars($photo['acID']) ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Envoyer cette photo à la corbeille ?');">
                            <input type="hidden" name="supprimer_photo" value="1">
                            <input type="hidden" name="photoID" value="<?= $photo['photoID'] ?>">
                            <button type="submit">🗑️</button>
                        </form>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p><a href="visionnage.php">Voir toutes les photos</a></p>
</div>
</body>
</html>
