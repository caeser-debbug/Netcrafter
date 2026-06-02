<?php
// Initialisation de la session
session_start();

// Destruction de toutes les variables de session
$_SESSION = array();

// Si un cookie de session existe, le détruire
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destruction de la session
session_destroy();

// Redirection vers la page de connexion
header("Location: login.php?success=" . urlencode("Vous avez été déconnecté avec succès."));
exit;
?>