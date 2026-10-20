<?php
/**
 * Gestión del personal: miembros del equipo a quienes se asignan tareas.
 * Alta, edición y eliminación 100% por AJAX (sin recargar la página).
 */
$csrf = tokenCsrf();
?>
<input type="hidden" id="csrfToken" value="<?= limpiar($csrf) ?>">

<header class="toolbar">
    <div>
        <h2 class="page-title mb-0"><i class="fa-solid fa-users-gear me-2"></i>Personal del equipo</h2>
        <p class="toolbar-sub mb-0">Administra a los miembros que recibirán tareas.</p>
    </div>
    <button type="button" class="btn btn-primary" id="btnNuevoPersonal">
        <i class="fa-solid fa-user-plus me-1"></i>Nuevo miembro
    </button>
</header>

<div class="card">
    <div class="card-body p-0">
        <div id="listaPersonal" class="tarea-lista">
            <div class="tarea-vacio"><i class="fa-solid fa-circle-notch fa-spin fa-2x"></i><p>Cargando personal…</p></div>
        </div>
    </div>
</div>

<!-- Modal crear / editar miembro -->
<div class="modal fade" id="modalPersonal" tabindex="-1" aria-labelledby="modalPersonalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formPersonal" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPersonalLabel">Nuevo miembro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="personalId" value="">
                <div class="mb-3">
                    <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" id="personalNombre" class="form-control" required maxlength="120" placeholder="Ej. Ana Torres">
                </div>
                <div class="mb-3">
                    <label class="form-label">Cargo</label>
                    <input type="text" name="cargo" id="personalCargo" class="form-control" maxlength="80" placeholder="Ej. Coordinadora de proyectos">
                </div>
                <div class="mb-0">
                    <label class="form-label">Correo</label>
                    <input type="email" name="email" id="personalEmail" class="form-control" maxlength="120" placeholder="nombre@aura.com">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check me-1"></i>Guardar
                </button>
            </div>
        </form>
    </div>
</div>
