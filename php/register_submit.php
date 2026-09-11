<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';

$pdo = db_connect();

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /register.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    $_SESSION['register_error'] = 'Invalid or expired form token. Please try again.';
    header('Location: /register.php');
    exit;
}

$old = $_POST;
$_SESSION['old_input'] = $old;

$required = ['email', 'last_name', 'first_name', 'level_type', 'pre_admission_code'];
foreach ($required as $field) {
    if (empty($old[$field])) {
        $_SESSION['register_error'] = 'All required fields must be filled.';
        header('Location: /register.php');
        exit;
    }
}

$email = trim($old['email']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['register_error'] = 'Invalid email format.';
    header('Location: /register.php');
    exit;
}

$validTypes = ['BED', 'College', 'TESDA'];
$levelType = $old['level_type'];
if (!in_array($levelType, $validTypes, true)) {
    $_SESSION['register_error'] = 'Invalid level type selected.';
    header('Location: /register.php');
    exit;
}

$code = trim($old['pre_admission_code']);
$stmt = $pdo->prepare('SELECT id FROM pre_admission_codes WHERE code = :code AND used = FALSE LIMIT 1');
$stmt->execute(['code' => $code]);
$codeRow = $stmt->fetch();
if (!$codeRow) {
    $_SESSION['register_error'] = 'Invalid or already used pre-admission code.';
    header('Location: /register.php');
    exit;
}

$defaults = [
    'enrollee_type' => 'new',
    'level' => '-',
    'semester' => '-',
    'modality' => 'FACE-TO-FACE',
    'first_choice_course' => '-',
    'first_major_track' => '-',
    'second_choice_course' => '-',
    'second_major_track' => '-',
    'nc_level' => '-',
    'contact_number' => '-',
    'lrn' => '-',
    'middle_name' => '-',
    'date_of_birth' => null,
    'place_of_birth' => '-',
    'gender' => '-',
    'nationality' => 'FILIPINO',
    'religion' => '-',
    'street_no' => '-',
    'street_name' => '-',
    'subdivision' => '-',
    'province' => '-',
    'town_city' => '-',
    'barangay' => '-',
    'father_name' => '-',
    'father_occupation' => '-',
    'father_mobile' => '-',
    'mother_name' => '-',
    'mother_occupation' => '-',
    'mother_mobile' => '-',
    'guardian_name' => '-',
    'guardian_occupation' => '-',
    'guardian_mobile' => '-',
    'guardian_email' => '-',
    'school_last_attended' => '-',
    'school_address' => '-',
    'school_year' => '-',
    'comorbidity' => '-',
    'parental_consent' => '-',
    'mode_of_transportation' => '-',
];

$cols = [];
$vals = [];
foreach ($defaults as $col => $def) {
    $cols[] = $col;
    $vals[] = $old[$col] ?? $def;
}
$cols[] = 'email';
$vals[] = $email;
$cols[] = 'last_name';
$vals[] = $old['last_name'];
$cols[] = 'first_name';
$vals[] = $old['first_name'];
$cols[] = 'level_type';
$vals[] = $levelType;
$cols[] = 'pre_admission_code';
$vals[] = $code;

$placeholders = implode(',', array_fill(0, count($cols), '?'));
$colList = implode(',', $cols);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("INSERT INTO pre_registrations ($colList) VALUES ($placeholders)");
    $stmt->execute($vals);
    $id = $pdo->lastInsertId();

    $appNumber = 'SMA-' . date('Y') . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE pre_registrations SET application_number = :an WHERE id = :id')
        ->execute(['an' => $appNumber, 'id' => $id]);

    $pdo->prepare('UPDATE pre_admission_codes SET used = TRUE WHERE id = :id')
        ->execute(['id' => $codeRow['id']]);

    $pdo->commit();

    $_SESSION['application_number'] = $appNumber;
    unset($_SESSION['old_input']);
    header('Location: /register.php?submitted=1');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['register_error'] = 'Database error: ' . $e->getMessage();
    header('Location: /register.php');
    exit;
}