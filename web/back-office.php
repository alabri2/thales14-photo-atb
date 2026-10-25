<?php
require_once('session.php');
require_once('bdd.php');
require_once('password_verification.php');

// Security
if ($_SESSION['role'] === 'invite') { // Verifying if the user has the right role
    header('Location: mode-photo.php');
    exit;
}
if ($_SESSION['role'] !== 'Administrateur' && $_SESSION['role'] !== 'Super Administrateur') {
    header('Location: home.php');
    exit;
}


// Log constants
const LOG_TYPE_INFO = 'Information';
const LOG_TYPE_WARN = 'Warning';
const LOG_TYPE_ALARM = 'Alarme';

function inLog($pdo, $userID, $type, $plus) {
    $stmt = $pdo->prepare("INSERT INTO LOGS (userID, dateL, heureL, evenement, plus) VALUES (?, date('now'), time('now'), ?, ?)");
    $stmt->execute([$userID, $type, $plus]);
    $stmt->closeCursor();
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_user'])) {
    $new_login = trim($_POST['new_login']);
    $new_password = trim($_POST['new_password']);
    $new_nom = trim($_POST['new_nom']);
    $new_prenom = trim($_POST['new_prenom']);
    $new_role = $_POST['new_role'];

    if ($new_role === 'Super Administrateur') {
        $message = "Ajout de Super Administrateur interdit.";
    } elseif ($new_role !== 'Opérateur' && $new_role !== 'Administrateur') {
        $message = "Rôle invalide.";
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM USER WHERE acID = ?");
        $check->execute([$new_login]);
        if ($check->fetchColumn() > 0) {
            $message = "Cet identifiant existe déjà.";
        } else {
            $stmt = $pdo->query("SELECT n, p, q, r FROM PARAM LIMIT 1");
            $params = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $validation = password_verification($new_password, $new_login, $params['n'], $params['p'], $params['q'], $params['r']);

            if ($validation !== true) {
                $message = $validation;
            } else {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);

                $insert = $pdo->prepare("INSERT INTO USER (acID, mdp, role, tentatives, bloque, nom, prenom) VALUES (?, ?, ?, 0, 'non', ?, ?)");
                $insert->execute([$new_login, $hash, $new_role, $new_nom, $new_prenom]);

                inLog($pdo, $_SESSION['user_id'], LOG_TYPE_INFO, 'Ajout utilisateur : ' . $new_login . ' (' . $new_role . ')');

                $message = "Utilisateur ajouté avec succès.";
            }
        }
    }
}
// Restore a photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restaurer_photo'])) {
    $photoID = intval($_POST['photoID']);
    $stmt = $pdo->prepare("UPDATE PHOTO SET supr = 'non' WHERE photoID = ?");
    $stmt->execute([$photoID]);
    inLog($pdo, $_SESSION['user_id'], LOG_TYPE_INFO, "Restauration photo ID $photoID");
}

// Definitive deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_definitif_photo'])) {
    $photoID = intval($_POST['photoID']);
    $stmt = $pdo->prepare("SELECT chemin FROM PHOTO WHERE photoID = ?");
    $stmt->execute([$photoID]);
    $chemin = basename((string) $stmt->fetchColumn());
    $stmt->closeCursor();
    $chemin_complet = __DIR__ . "/assets/images/" . $chemin;

    if ($chemin !== '' && is_file($chemin_complet)) {
        unlink($chemin_complet);
    }

    $stmt = $pdo->prepare("DELETE FROM PHOTO WHERE photoID = ?");
    $stmt->execute([$photoID]);

    inLog($pdo, $_SESSION['user_id'], LOG_TYPE_INFO, "Suppression définitive photo ID $photoID ($chemin)");
}



if (isset($_GET['debloquer'])) {
    $id = intval($_GET['debloquer']);
    $stmt = $pdo->prepare("SELECT acID FROM USER WHERE userID = ?");
    $stmt->execute([$id]);
    $identifiantDebloque = $stmt->fetchColumn();
    $stmt->closeCursor();

    $stmt = $pdo->prepare("UPDATE USER SET tentatives = 0, bloque = 'non' WHERE userID = ?");
    $stmt->execute([$id]);
    $stmt->closeCursor();

    inLog($pdo, $_SESSION['user_id'], LOG_TYPE_INFO, 'Déblocage du compte : ' . $identifiantDebloque);
}




// The rest of the code (filtres, pagination, user display/logs/photos) is unchanged from here




// Export CSV of the logs
if (isset($_GET['export']) && $_GET['export'] === 'logs') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=logs_export.csv');
    $output = fopen("php://output", "w");
    fputcsv($output, ['Date', 'Heure', 'Utilisateur', 'Rôle', 'Type', 'Action']);

    $stmt = $pdo->query("
        SELECT LOGS.dateL, LOGS.heureL, USER.acID, USER.role, LOGS.evenement, LOGS.plus
        FROM LOGS
        JOIN USER ON LOGS.userID = USER.userID
        ORDER BY dateL DESC, heureL DESC
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['dateL'], $row['heureL'], $row['acID'], $row['role'], $row['evenement'], $row['plus']]);
    }
    fclose($output);
    exit;
}

// Filtres
$type_recherche = $_GET['type_recherche'] ?? '';
if (!in_array($type_recherche, ['utilisateur', 'log', 'photo'])) {
    $type_recherche = '';
}

$afficher_utilisateurs = ($type_recherche === 'utilisateur' || $type_recherche === '');
$afficher_photos = ($type_recherche === 'photo' || $type_recherche === '');
$filtre_user = $_GET['user'] ?? '';
$filtre_role = $_GET['role'] ?? '';
$filtre_type = $_GET['type'] ?? '';
$filtre_date = $_GET['date'] ?? '';
$filtre_action = $_GET['action'] ?? '';
$filtre_nom = $_GET['nom'] ?? '';
$filtre_id = $_GET['id'] ?? '';
$filtre_heure = $_GET['heure'] ?? '';
$filtre_utilisateur = $_GET['utilisateur'] ?? '';


// Pagination of the logs
$logs_par_page = 10;
$page_actuelle = max(1, intval($_GET['page'] ?? 1));
$debut = ($page_actuelle - 1) * $logs_par_page;

$conditions_logs = ["acID LIKE ?", "role LIKE ?", "evenement LIKE ?"];
$params_logs = ["%$filtre_user%", "%$filtre_role%", "%$filtre_type%"];

if ($filtre_date) {
    $conditions_logs[] = "dateL LIKE ?";
    $params_logs[] = "%$filtre_date%";
}
if ($filtre_action) {
    $conditions_logs[] = "plus LIKE ?";
    $params_logs[] = "%$filtre_action%";
}



$total_logs_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM LOGS
    JOIN USER ON LOGS.userID = USER.userID
    WHERE " . implode(" AND ", $conditions_logs)
);
$total_logs_stmt->execute($params_logs);

$total_logs = $total_logs_stmt->fetchColumn();
$pages_totales = ceil($total_logs / $logs_par_page);

$conditions_logs_sql = implode(" AND ", $conditions_logs);
$params_logs_limit = $params_logs;
$params_logs_limit[] = $logs_par_page;
$params_logs_limit[] = $debut;

$stmt = $pdo->prepare("
    SELECT LOGS.*, USER.acID, USER.role, USER.nom, USER.prenom 
    FROM LOGS 
    JOIN USER ON LOGS.userID = USER.userID
    WHERE $conditions_logs_sql
    ORDER BY dateL DESC, heureL DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params_logs_limit);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// users
$utilisateurs = [];
if ($afficher_utilisateurs) {
    $stmt = $pdo->prepare("SELECT * FROM USER WHERE acID LIKE ? AND role LIKE ?");
    $stmt->execute(["%$filtre_user%", "%$filtre_role%"]);
    $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_user'])) {
    $idModif = intval($_POST['id_user']);
    $new_role = $_POST['modif_role'];
    $new_pass = trim($_POST['modif_pass']);

    // retrieve infos from the account we want to modify
    $stmt = $pdo->prepare("SELECT * FROM USER WHERE userID = ?");
    $stmt->execute([$idModif]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$targetUser) {
        $message = "Utilisateur introuvable.";
    } elseif ($targetUser['role'] === 'Super Administrateur') {
        $message = "Impossible de modifier un Super Administrateur.";
    } else {
        // update
        $actions = [];

        if (!empty($new_pass)) {
            // password verification
           $stmt = $pdo->query("SELECT n, p, q, r FROM PARAM LIMIT 1");
           $params = $stmt->fetch(PDO::FETCH_ASSOC);
           $stmt->closeCursor();

           $validation = password_verification($new_pass, $targetUser['acID'], $params['n'], $params['p'], $params['q'], $params['r']);

            if ($validation !== true) {
                $message = $validation;
            } else {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE USER SET mdp = ? WHERE userID = ?");
                $stmt->execute([$hash, $idModif]);
                $stmt->closeCursor();
                $actions[] = "Mot de passe modifié";
            }
        }

        if ($targetUser['role'] !== $new_role && ($new_role === 'Opérateur' || $new_role === 'Administrateur')) {
            if (in_array($_SESSION['role'], ['Super Administrateur', 'Administrateur'])) {
                $stmt = $pdo->prepare("UPDATE USER SET role = ? WHERE userID = ?");
                $stmt->execute([$new_role, $idModif]);
                $stmt->closeCursor();
                $actions[] = "Rôle modifié : " . $targetUser['role'] . " → " . $new_role;
            }
        }

        if (!empty($actions)) {
            $stmtLog = $pdo->prepare("INSERT INTO LOGS (userID, dateL, heureL, evenement, plus) VALUES (?, date('now'), time('now'), ?, ?)");
            $stmtLog->execute([
                $_SESSION['user_id'],
                LOG_TYPE_INFO,
                "Modification utilisateur : " . $targetUser['acID'] . " (" . implode(', ', $actions) . ")"
            ]);
            $stmtLog->closeCursor();
            $message = "Modifications enregistrées.";
        } elseif (!isset($message)) {
            $message = "Aucun changement détecté.";
        }
    }
}

// deletion of user (securized)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_user'])) {
    $idSup = intval($_POST['id_user']);

    // Retrieve infos of the account we want to delete
    $stmt = $pdo->prepare("SELECT * FROM USER WHERE userID = ?");
    $stmt->execute([$idSup]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$targetUser) {
        $message = "Utilisateur introuvable.";
    } elseif ($targetUser['role'] === 'Super Administrateur') {
        $message = "Impossible de supprimer un Super Administrateur.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM USER WHERE userID = ?");
        $stmt->execute([$idSup]);
        $stmt->closeCursor();

        // log about this deletion
        $stmtLog = $pdo->prepare("INSERT INTO LOGS (userID, dateL, heureL, evenement, plus) VALUES (?, date('now'), time('now'), ?, ?)");
        $stmtLog->execute([
        $_SESSION['user_id'],
        LOG_TYPE_INFO,
       'Suppression utilisateur : ' . $targetUser['acID'] . ' (' . $targetUser['role'] . ')'
     ]);

        $stmtLog->closeCursor();

        $message = "Utilisateur supprimé.";
    }
}
// deletion of a photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_photo'])) {
    $photoID = intval($_POST['photoID']);

    $stmt = $pdo->prepare("UPDATE PHOTO SET supr = 'oui' WHERE photoID = ?");
    $stmt->execute([$photoID]);
    $stmt->closeCursor();

    inLog($pdo, $_SESSION['user_id'], LOG_TYPE_INFO, "Photo mise à la corbeille ID $photoID");

    $message = "Photo supprimée (envoyée à la corbeille).";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_param'])) {
    if ($_SESSION['role'] === 'Super Administrateur' || $_SESSION['role'] === 'Administrateur') {
        $new_n = max(0, intval($_POST['n']));
        $new_p = max(0, intval($_POST['p']));
        $new_q = max(0, intval($_POST['q']));
        $new_r = max(0, intval($_POST['r']));

        $stmt = $pdo->prepare("UPDATE PARAM SET n = ?, p = ?, q = ?, r = ? WHERE idParam = 1");
        $stmt->execute([$new_n, $new_p, $new_q, $new_r]);
        $stmt->closeCursor();

        // information log on the new parameters for the creation of password
        $stmtLog = $pdo->prepare("INSERT INTO LOGS (userID, dateL, heureL, evenement, plus) VALUES (?, date('now'), time('now'), ?, ?)");
        $stmtLog->execute([
            $_SESSION['user_id'],
            'Information',
            'Modification des paramètres de sécurité n/p/q/r'
        ]);
        $stmtLog->closeCursor();

        $message = "Paramètres de sécurité mis à jour.";
    } else {
        $message = "Accès refusé.";
    }
}

$stmt = $pdo->query("SELECT * FROM PARAM LIMIT 1");
$params = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor();

// photos
$photos = [];
if ($afficher_photos) {
$stmt = $pdo->prepare("
    SELECT P.*, U.acID
    FROM PHOTO P
    JOIN USER U ON P.userID = U.userID
    WHERE supr = 'oui'
    AND dateP LIKE ?
    AND titre LIKE ?
    AND photoID LIKE ?
");
    $stmt->execute(["%$filtre_date%", "%$filtre_nom%", "%$filtre_id%"]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
}


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Back Office - PHOTO_ATB</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="menu">
    <h3>Menu</h3>
    <ul>
        <li><a href="home.php">Accueil</a></li>
        <li><a href="visionnage.php">Visionnage</a></li>
        <li><a href="back-office.php">Back Office</a></li>
        <li><a href="mode-photo.php">Mode Photo</a></li>
        <li><a href="logout.php">Déconnexion</a></li>
    </ul>
</div>

<div class="container">
    <h2>Back Office</h2>

    <form method="get">
        <label><strong>Rechercher dans :</strong></label>
        <select name="type_recherche" onchange="this.form.submit()">
            <option value="">-- Choisir --</option>
            <option value="utilisateur" <?= $type_recherche === 'utilisateur' ? 'selected' : '' ?>>Utilisateurs</option>
            <option value="log" <?= $type_recherche === 'log' ? 'selected' : '' ?>>Logs</option>
            <option value="photo" <?= $type_recherche === 'photo' ? 'selected' : '' ?>>Photos</option>
        </select>

        <?php if ($type_recherche === 'utilisateur'): ?>
            <input type="text" name="user" placeholder="Identifiant" value="<?= htmlspecialchars($filtre_user) ?>">
            <input type="text" name="role" placeholder="Rôle" value="<?= htmlspecialchars($filtre_role) ?>">
        <?php elseif ($type_recherche === 'log'): ?>
    <input type="text" name="user" placeholder="Utilisateur" value="<?= htmlspecialchars($filtre_user) ?>">
    <input type="text" name="role" placeholder="Rôle" value="<?= htmlspecialchars($filtre_role) ?>">
    <input type="text" name="type" placeholder="Type (info, erreur...)" value="<?= htmlspecialchars($filtre_type) ?>">
    <input type="text" name="date" placeholder="Date (AAAA-MM-JJ)" value="<?= htmlspecialchars($filtre_date) ?>">
    <input type="text" name="action" placeholder="Action (mot-clé...)" value="<?= htmlspecialchars($filtre_action) ?>">
      <?php elseif ($type_recherche === 'photo'): ?>
    <input type="text" name="date" placeholder="Date" value="<?= htmlspecialchars($filtre_date) ?>">
    <input type="text" name="heure" placeholder="Heure" value="<?= htmlspecialchars($filtre_heure) ?>">
    <input type="text" name="nom" placeholder="Titre" value="<?= htmlspecialchars($filtre_nom) ?>">
    <input type="text" name="id" placeholder="ID Photo" value="<?= htmlspecialchars($filtre_id) ?>">
    <input type="text" name="utilisateur" placeholder="Utilisateur" value="<?= htmlspecialchars($filtre_utilisateur) ?>">
       <?php endif; ?>

        <button type="submit">Rechercher</button>
    </form>
    <section>
    <h3>Ajouter un utilisateur</h3>
    <?php if (!empty($message)): ?>
        <p style="color: red;"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="ajouter_user" value="1">
        <input type="text" name="new_login" placeholder="Identifiant" required>
        <input type="password" name="new_password" placeholder="Mot de passe" required>
        <input type="text" name="new_nom" placeholder="Nom" required>
        <input type="text" name="new_prenom" placeholder="Prénom" required>
        <select name="new_role" required>
            <option value="">-- Rôle --</option>
            <option value="Opérateur">Opérateur</option>
            <?php if (in_array($_SESSION['role'], ['Super Administrateur', 'Administrateur'])): ?>
                <option value="Administrateur">Administrateur</option>
            <?php endif; ?>
        </select>
        <button type="submit">Ajouter</button>
    </form>
</section>
<?php if ($_SESSION['role'] === 'Super Administrateur' || $_SESSION['role'] === 'Administrateur'): ?>
<section>
    <h3>Paramètres de sécurité du mot de passe</h3>
    <form method="post">
        <input type="hidden" name="modifier_param" value="1">
        <label>Chiffres requis (n) : <input type="number" name="n" value="<?= $params['n'] ?>" min="0"></label><br>
        <label>Minuscules requises (p) : <input type="number" name="p" value="<?= $params['p'] ?>" min="0"></label><br>
        <label>Majuscules requises (q) : <input type="number" name="q" value="<?= $params['q'] ?>" min="0"></label><br>
        <label>Caractères spéciaux requis (r) : <input type="number" name="r" value="<?= $params['r'] ?>" min="0"></label><br>
        <button type="submit">Modifier</button>
    </form>
</section>
<?php endif; ?>


    <!-- Utilisateurs -->
    <section>
        <h3>Utilisateurs</h3>
        <table>
            <thead>
                <tr><th>Identifiant</th><th>Rôle</th><th>Tentatives</th><th>Bloqué</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $user): ?>
                    <tr>
                       
    <td><?= $user['acID'] ?></td>
    <td><?= $user['role'] ?></td>
    <td><?= $user['tentatives'] ?></td>
    <td><?= $user['bloque'] === 'oui' ? '✅ Oui' : '❌ Non' ?></td>
    <td> <!-- 💡 ajoute ce <td> manquant -->
        <!-- Débloquer -->
        <a href="?type_recherche=utilisateur&debloquer=<?= $user['userID'] ?>" <?= $user['bloque'] === 'oui' ? '' : 'style="opacity:0.5;pointer-events:none;"' ?>>Débloquer</a>
         
        <!-- Modifier -->
        <?php if ($user['role'] !== 'Super Administrateur' && in_array($_SESSION['role'], ['Super Administrateur', 'Administrateur'])): ?>
            <form method="post" style="display:inline;">
                <input type="hidden" name="modifier_user" value="1">
                <input type="hidden" name="id_user" value="<?= $user['userID'] ?>">
                <input type="password" name="modif_pass" placeholder="Nouveau mot de passe" style="width:120px;">
                <select name="modif_role">
                    <option value="Opérateur" <?= $user['role'] === 'Opérateur' ? 'selected' : '' ?>>Opérateur</option>
                    <?php if (in_array($_SESSION['role'], ['Super Administrateur', 'Administrateur'])): ?>
                        <option value="Administrateur" <?= $user['role'] === 'Administrateur' ? 'selected' : '' ?>>Administrateur</option>
                    <?php endif; ?>
                </select>
                <button type="submit">Modifier</button>
            </form>
        <?php else: ?>
            <span style="opacity:0.5;">Non modifiable</span>
        <?php endif; ?>

        <!-- Erase user -->
        <?php if ($user['role'] !== 'Super Administrateur' && in_array($_SESSION['role'], ['Super Administrateur', 'Administrateur'])): ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer définitivement cet utilisateur ?');">
                <input type="hidden" name="supprimer_user" value="1">
                <input type="hidden" name="id_user" value="<?= $user['userID'] ?>">
                <button type="submit">in trash</button>
            </form>
        <?php endif; ?>
    </td> <!-- end of <td> -->
</tr>

                        
                        
    
            
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <!-- Logs -->
    <section>
        <h3>Logs utilisateurs</h3>
        <p><a href="?export=logs"> Exporter tous les logs (CSV)</a></p>
        <table>
            <thead><tr><th>Date</th><th>Utilisateur</th><th>Rôle</th><th>Type</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $log['dateL'] ?> <?= $log['heureL'] ?></td>
                        <td><?= $log['prenom'] ?> <?= $log['nom'] ?> (<?= $log['acID'] ?>)</td>
                        <td><?= $log['role'] ?></td>
                        <td><?= $log['evenement'] ?></td>
                        <td><?= $log['plus'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
       <div class="pagination">
<?php
$plage = 1; // number of pages before/after the current page
$afficheDebut = 3;
$afficheFin = 3;

for ($i = 1; $i <= $pages_totales; $i++) {
    // display of the first and last pages
    if (
        $i <= $afficheDebut ||
        $i > $pages_totales - $afficheFin ||
        ($i >= $page_actuelle - $plage && $i <= $page_actuelle + $plage)
    ) {
        $isActive = $i === $page_actuelle ? 'style="font-weight:bold;"' : '';
        echo "<a href='?type_recherche=log&page=$i&user=" . urlencode($filtre_user) . "&role=" . urlencode($filtre_role) . "&type=" . urlencode($filtre_type) . "' $isActive>Page $i</a> ";
        $lastShown = $i;
    } elseif (!isset($lastEllipsis) || $lastEllipsis !== $lastShown + 1) {
        echo "... ";
        $lastEllipsis = $i;
    }
}
?>
</div>

    </section>

    <!-- Photos -->
    <section>
        <h3>Suppression définitive de photos</h3>
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
            <td><img src="assets/images/<?= htmlspecialchars($photo['chemin']) ?>" width="60"></td>
            <td><?= htmlspecialchars($photo['dateP']) ?></td>
            <td><?= htmlspecialchars($photo['heureP']) ?></td>
            <td><?= htmlspecialchars($photo['photoID']) ?></td>
            <td><?= htmlspecialchars($photo['titre']) ?></td>
            <td><?= htmlspecialchars($photo['acID']) ?></td>
            <td>
    <form method="post" style="display:inline;">
        <input type="hidden" name="restaurer_photo" value="1">
        <input type="hidden" name="photoID" value="<?= $photo['photoID'] ?>">
        <button type="submit">♻️ Restaurer</button>
    </form>

   <form method="post" onsubmit="return confirm('Supprimer définitivement cette photo ?');" style="display:inline;">
    <input type="hidden" name="supprimer_definitif_photo" value="1">
    <input type="hidden" name="photoID" value="<?= $photo['photoID'] ?>">
    <input type="hidden" name="chemin_photo" value="<?= $photo['chemin'] ?>">
    <button type="submit">🗑️ Supprimer définitivement</button>
</form>



        
    <?php endif; ?>
<?php endforeach; ?>
</tbody>

    </section>
</div>
</body>
</html>
