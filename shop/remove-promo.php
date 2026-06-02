<?php
header('Content-Type: application/json');
session_start();
unset($_SESSION['promo']);
echo json_encode(['ok' => true]);
