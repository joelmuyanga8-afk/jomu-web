<?php

session_start();
include "connection/dbconn.php";

$user_id = $_SESSION["id"];

$sql= "SELECT businessname FROM createaccount WHERE id = ?";
$stmt=$conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($businessName);
$stmt->fetch();
$stmt->close();
$conn->close();

echo json.encode(["businessName" => $businessName ? $businessName: ""]);