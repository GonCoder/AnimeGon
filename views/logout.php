<?php
// logout.php - Cerrar sesión

require_once '../backend/config/funciones.php';

// Cerrar sesión
cerrarSesion();

// Redirigir al index con mensaje
header("Location: ../index.php?logout=success");
exit();
?>