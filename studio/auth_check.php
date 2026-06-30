<?php
session_start();
if (isset($_SESSION['user'])) {
    http_response_code(200);
} else {
    http_response_code(401);
}
