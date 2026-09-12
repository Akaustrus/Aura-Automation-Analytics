<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function estaLogueado(): bool
{
    return isset($_SESSION['usuario_id']);
}

function exigirLogin(): void
{
    if (!estaLogueado()) {
        header('Location: index.php?ruta=login');
        exit;
    }
}

function exigirRol(array $roles): void
{
    exigirLogin();
    if (!in_array($_SESSION['usuario_rol'], $roles, true)) {
        http_response_code(403);
        echo 'No tienes permisos para esta seccion.';
        exit;
    }
}

function iniciarSesion(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_rol'] = $usuario['rol'];
}

function cerrarSesion(): void
{
    session_unset();
    session_destroy();
}
