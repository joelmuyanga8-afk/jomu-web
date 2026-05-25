<?php

session_start();
unset($_SESSION['jomu_suspended_browse'], $_SESSION['jomu_suspended_until']);
session_unset();

header('location: /');