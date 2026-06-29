<?php
header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/connection/dbconn.php';

$links = [
    'app' => '',
    'facebook' => '',
    'instagram' => '',
    'tiktok' => '',
    'x' => '',
    'support_email' => '',
    'privacy_email' => '',
    'support_phone' => '',
    'support_whatsapp' => '',
];

$conn->query(
    "CREATE TABLE IF NOT EXISTS site_links (
        link_key VARCHAR(40) PRIMARY KEY,
        label VARCHAR(80) NOT NULL,
        url VARCHAR(500) NOT NULL DEFAULT '',
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$defaultLinks = [
    'app' => 'JoMu Application (components/nav.php)',
    'facebook' => 'Facebook (components/nav.php, /, /support)',
    'instagram' => 'Instagram (components/nav.php, /, /support)',
    'tiktok' => 'Tiktok (components/nav.php, /, /support)',
    'x' => 'X (components/nav.php, /, /support)',
    'support_email' => 'Support email (/support)',
    'privacy_email' => 'Privacy policy email (/privacy-policy)',
    'support_phone' => 'Support phone call (/support)',
    'support_whatsapp' => 'Support WhatsApp (/support)',
];
$defaultLinkUrls = [
    'support_email' => 'jomumarket@email.com',
    'privacy_email' => 'ContactJoMu@gmail.com',
    'support_phone' => '+256 708973632',
    'support_whatsapp' => '+256 708973632',
];

foreach ($defaultLinks as $key => $label) {
    $defaultUrl = (string) ($defaultLinkUrls[$key] ?? '');
    $stmt = $conn->prepare("INSERT IGNORE INTO site_links (link_key, label, url) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('sss', $key, $label, $defaultUrl);
        $stmt->execute();
        $stmt->close();
    }

    $labelStmt = $conn->prepare("UPDATE site_links SET label = ? WHERE link_key = ?");
    if ($labelStmt) {
        $labelStmt->bind_param('ss', $label, $key);
        $labelStmt->execute();
        $labelStmt->close();
    }
}

$result = $conn->query("SELECT link_key, url FROM site_links");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $key = (string) ($row['link_key'] ?? '');
        if (array_key_exists($key, $links)) {
            $links[$key] = (string) ($row['url'] ?? '');
        }
    }
}

echo json_encode(['links' => $links]);
