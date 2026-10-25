<?php
function password_verification($password, $identifiant, $n, $p, $q, $r) {
    $errors = [];

    if (strlen($password) < ($n + $p + $q + $r)) {
        $errors[] = "Mot de passe trop court.";
    }

    if ($n > 0 && preg_match_all('/[0-9]/', $password) < $n) {
        $errors[] = "Au moins $n chiffre(s) requis.";
    }

    if ($p > 0 && preg_match_all('/[a-z]/', $password) < $p) {
        $errors[] = "Au moins $p minuscule(s) requise(s).";
    }

    if ($q > 0 && preg_match_all('/[A-Z]/', $password) < $q) {
        $errors[] = "Au moins $q majuscule(s) requise(s).";
    }

    if ($r > 0 && preg_match_all('/[^a-zA-Z0-9]/', $password) < $r) {
        $errors[] = "Au moins $r caractère(s) spécial(aux) requis.";
    }

    if (preg_match('/[^\x20-\x7E]/', $password)) {
        $errors[] = "Le mot de passe ne doit pas contenir d'accent.";
    }

    if (stripos($password, $identifiant) !== false) {
        $errors[] = "Le mot de passe ne doit pas contenir l'identifiant.";
    }

    return empty($errors) ? true : implode(" ", $errors);
}
