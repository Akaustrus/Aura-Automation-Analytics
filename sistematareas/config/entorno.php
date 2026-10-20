<?php
/**
 * Conexión a la base de datos del Sistema de Tareas.
 *
 * Edita este archivo si la detección automática no encuentra tu MySQL / MariaDB
 * (por ejemplo en una Raspberry Pi, servidor privado o hosting).
 *
 * Usa `null` para dejar un valor en modo automático.
 * También se pueden usar variables de entorno: TAREAS_DB_HOST, TAREAS_DB_PORT,
 * TAREAS_DB_USER, TAREAS_DB_PASS, TAREAS_DB_NAME (tienen prioridad sobre lo automático,
 * pero no sobre este archivo).
 */
return [
    'host'    => null,   // p. ej. '127.0.0.1', 'localhost' (por socket) o '192.168.1.50'
    'puerto'  => null,   // p. ej. 3306 (típico) o 3307; con 'localhost' se usa el socket
    'usuario' => null,   // p. ej. 'root' o un usuario dedicado como 'aura'
    'clave'   => null,   // null = se prueban '' y '1234'; una cadena fija = solo esa
    'base'    => 'tareas',
];
