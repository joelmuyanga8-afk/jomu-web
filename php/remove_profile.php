<?php
http_response_code(410);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['success' => false, 'message' => 'This endpoint is no longer available.']);
