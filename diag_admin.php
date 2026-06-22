<?php
require __DIR__ . '/db.php';
foreach ($pdo->query("SELECT id,email,name,is_admin,is_active FROM users WHERE is_admin=TRUE") as $r) {
    echo json_encode($r) . PHP_EOL;
}
echo "--- non-admin sample ---\n";
foreach ($pdo->query("SELECT id,email,referred_by FROM users WHERE is_admin=FALSE AND deleted_at IS NULL LIMIT 3") as $r) {
    echo json_encode($r) . PHP_EOL;
}
echo "--- pending deposits ---\n";
foreach ($pdo->query("SELECT id,user_id,amount,status FROM deposit_requests WHERE status='pending' LIMIT 3") as $r) {
    echo json_encode($r) . PHP_EOL;
}
