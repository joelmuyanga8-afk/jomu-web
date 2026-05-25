<?php

session_start();
include "connection/dbconn.php";

$user_id = $_SESSION["id"];

$sql = "SELECT profilepic FROM createaccount WHERE id = ?";
$stmt=$conn->prepare($sql);
$stmt->bind_param("i",$user_id);
$stmt->execute();
$stmt->bind_result($pic);
$stmt->fetch();
$stmt->close();

echo $pic ? $pic : "";

$conn->close();


























// $user_id = $_GET['user_id'];
// $business_name = "Apha Business";

// $dir = "/assets/uploads/";
// $file = $dir. "user_".$user_id.".jpg";

// $parts = explode("",trim($business_name));
// $initialsText = "";
// foreach($parts as $p) {
//     if ($p !== "") {
//         $initials.=strtoupper($p[0]);
//     }
// }
// $initialsText = substr($initialsText,0,2);

// echo  json_encode([
//     "initialsText" => $initialsText,
//     "image" => file_exists($file)? $file:null
// ]);
