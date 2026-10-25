<?php
$file = 'logs/mdp_test.txt';

if (file_exists($file)) {
    echo "<pre>" . htmlspecialchars(file_get_contents($file)) . "</pre>";
} else {
    echo "Fichier introuvable.";
}
?>
