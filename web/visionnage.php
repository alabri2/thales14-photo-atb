<?php
require_once('session.php');
require_once('bdd.php');

// Rediriger les invités uniquement vers mode-photo
if ($_SESSION['role'] === 'invite') {
    header('Location: mode-photo.php');
    exit;
}
$name_filtre = $_GET['nom'] ?? '';
$date_filtre = $_GET['date'] ?? '';
$hour_filtre = $_GET['heure'] ?? '';
$photo_id_filtre = $_GET['photoid'] ?? '';
$user_filtre = $_GET['utilisateur'] ?? '';

// Securized deletion of photos
// provisional deletion (without the deletion of the file on raspberry and DB)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_photo'])) {
    // authorize operators
    if (!in_array($_SESSION['role'], ['Opérateur', 'Administrateur', 'Super Administrateur'])) {
        exit("Accès refusé.");
    }

    $photoID = intval($_POST['photoID']);

    // update of DB : deletion of photo
    $stmt = $pdo->prepare("UPDATE PHOTO SET supr = 'oui' WHERE photoID = ?");
    $stmt->execute([$photoID]);
    $stmt->closeCursor();

    // Log
    $stmtLog = $pdo->prepare("INSERT INTO LOGS (userID, dateL, heureL, evenement, plus) VALUES (?, date('now'), time('now'), ?, ?)");
    $stmtLog->execute([$_SESSION['user_id'], 'Information', "Photo mise à la corbeille ID $photoID"]);
    $stmtLog->closeCursor();

    header("Location: visionnage.php?message=photo_supprimee");
    exit;
}

// WHERE dynamique
$conditions = ["supr != 'oui'"];
$params = [];

if ($name_filtre) {
    $conditions[] = "titre LIKE ?";
    $params[] = "%$name_filtre%";
}
if ($date_filtre) {
    $conditions[] = "dateP LIKE ?";
    $params[] = "%$date_filtre%";
}
if ($hour_filtre) {
    $conditions[] = "heureP LIKE ?";
    $params[] = "%$hour_filtre%";
}
if ($photo_id_filtre) {
    $conditions[] = "photoID LIKE ?";
    $params[] = "%$photo_id_filtre%";
}
if ($user_filtre) {
    $conditions[] = "U.acID LIKE ?";
    $params[] = "%$user_filtre%";
}

$where = implode(" AND ", $conditions);

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limite = 10;
$debut = ($page - 1) * $limite;

// Total
$stmt_count = $pdo->prepare("
    SELECT COUNT(*)
    FROM PHOTO P
    JOIN USER U ON P.userID = U.userID
    WHERE $where
");
$stmt_count->execute($params);
$total = $stmt_count->fetchColumn();
$pages_total = ceil($total / $limite);

// recovery of photos
$params_limit = [...$params, $limite, $debut];
$sql = "
    SELECT P.*, U.acID
    FROM PHOTO P
    JOIN USER U ON P.userID = U.userID
    WHERE $where
    ORDER BY dateP DESC, heureP DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params_limit);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Visionnage - PHOTO_ATB</title>
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
    <h2>Visionnage complet</h2>

    <form method="get">
        <input type="text" name="nom" placeholder="Titre" value="<?= htmlspecialchars($name_filtre) ?>">
        <input type="text" name="date" placeholder="Date (YYYY-MM-DD)" value="<?= htmlspecialchars($date_filtre) ?>">
        <input type="text" name="heure" placeholder="Heure (HH:MM)" value="<?= htmlspecialchars($_GET['heure'] ?? '') ?>">
        <input type="text" name="photoid" placeholder="ID Photo" value="<?= htmlspecialchars($_GET['photoid'] ?? '') ?>">
        <input type="text" name="utilisateur" placeholder="Identifiant utilisateur" value="<?= htmlspecialchars($_GET['utilisateur'] ?? '') ?>">
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
            <th>Actions</th>
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
                            <a href="voir_photo.php?id=<?= $photo['photoID'] ?>" target="_blank">👁️ Voir</a>
                            <button type="submit">🗑️</button>
                        </form>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php for ($i = 1; $i <= $pages_total; $i++): ?>
            <a href="?page=<?= $i ?>
                &nom=<?= urlencode($name_filtre) ?>
                &date=<?= urlencode($date_filtre) ?>
                &heure=<?= urlencode($hour_filtre) ?>
                &photoid=<?= urlencode($photo_id_filtre) ?>
                &utilisateur=<?= urlencode($user_filtre) ?>">
                Page <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
</div>
</body>
</html>
