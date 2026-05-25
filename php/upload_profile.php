<?php


 session_start();
 header('Content-Type: application/json; charset=UTF-8');
 include "connection/dbconn.php";
 require_once "partials/helpers.php";


 if (!isset($_SESSION['emailormobilenumber'])) {
    echo json_encode([ 'success' => false, 'error' => 'Business not authenticated']);
    exit;
 }

 $emailormobilenumber = $_SESSION['emailormobilenumber'];

 if (!isset($_FILES['profile-pic'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit();
 }

 jomu_require_csrf();
 jomu_require_rate_limit('upload_profile', 12, 60 * 60, 'Too many profile upload attempts. Please wait and try again.', (string) $_SESSION['emailormobilenumber']);

 $file = $_FILES['profile-pic'];
 $allowedTypes = ['image/jpeg','image/jpg','image/png','image/webp'];
$uploadDir = "uploads/profile/";
include "partials/_media_upload.php";

 $stmt = $conn->prepare("SELECT profilepic from users WHERE emailormobilenumber = ? limit 1");
   $stmt->bind_param('s', $_SESSION['emailormobilenumber']);
   $stmt->execute();
   $user = $stmt->get_result()->fetch_assoc();
   if (!empty($user['profilepic'])) {
      jomu_delete_listing_media_if_safe((string) $user['profilepic']);
   }

    $sql = "UPDATE users SET profilepic = ? WHERE emailormobilenumber = ?";
    $stmt = $conn->prepare($sql);

  

    $stmt->bind_param("ss", $targetPath, $emailormobilenumber);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'imageUrl' => $targetPath]);
    } else { 
        echo json_encode(['success' => false, 'error' => 'Database update failed.']);
    }
    $stmt->close();
    $conn->close();
 





















