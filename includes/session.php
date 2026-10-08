<?php
// Included by the resident pages. New residents (e.g. first Google login) must give their
// subdivision, block and lot before using the dashboard; see complete-profile.php.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user']['home_done']) && !empty($_SESSION['user']['id'])) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/user_schema.php';
    greenai_ensure_user_contact_columns($pdo);
    $homeCheck = $pdo->prepare("SELECT Residence, block_no, lot_no FROM users WHERE id = ?");
    $homeCheck->execute([(int) $_SESSION['user']['id']]);
    $homeRow = $homeCheck->fetch();
    if ($homeRow && trim((string) $homeRow['Residence']) !== '' && trim((string) $homeRow['block_no']) !== '' && trim((string) $homeRow['lot_no']) !== '') {
        $_SESSION['user']['home_done'] = true;
    } else {
        $toRoot = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'dashboard' ? '../' : '';
        header('Location: ' . $toRoot . 'complete-profile.php');
        exit();
    }
}
