<?php
/** Vista "Por vencer": mismos componentes AJAX, con filtro de vencimiento activo. */
$csrf = tokenCsrf();
?>
<input type="hidden" id="csrfToken" value="<?= limpiar($csrf) ?>">

<header class="toolbar">
    <div>
        <h2 class="page-title mb-0"><i class="fa-solid fa-calendar-days me-2"></i>Tareas por vencer</h2>
        <p class="toolbar-sub mb-0">Vencidas y próximas a vencer (no completadas).</p>
    </div>
    <button type="button" class="btn btn-primary" id="btnNuevaTarea">
        <i class="fa-solid fa-plus me-1"></i>Nueva tarea
    </button>
</header>

<div class="card">
    <div class="card-body p-0">
        <div id="listaTareas" class="tarea-lista" data-vista="por_vencer">
            <div class="tarea-vacio"><i class="fa-solid fa-circle-notch fa-spin fa-2x"></i><p>Cargando tareas…</p></div>
        </div>
    </div>
</div>

<!-- Modal crear / editar tarea (mismo modal que la vista principal) -->
<div class="modal fade" id="modalTarea" tabindex="-1" aria-labelledby="modalTareaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="formTarea" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTareaLabel">Nueva tarea</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="tareaId" value="">
                <div class="mb-3">
                    <label class="form-label">Título <span class="text-danger">*</span></label>
                    <input type="text" name="titulo" id="tareaTitulo" class="form-control" required maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" id="tareaDescripcion" class="form-control" rows="3" maxlength="1000"></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Prioridad</label>
                        <select name="prioridad" id="tareaPrioridad" class="form-select">
                            <option value="baja">Baja</option>
                            <option value="media" selected>Media</option>
                            <option value="alta">Alta</option>
                            <option value="critica">Crítica</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" id="tareaEstado" class="form-select">
                            <option value="pendiente">Pendiente</option>
                            <option value="en_proceso">En proceso</option>
                            <option value="completada">Completada</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Asignar a</label>
                    <select name="responsable_id" id="tareaResponsable" class="form-select">
                        <option value="">— Sin asignar —</option>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label">Fecha de vencimiento</label>
                    <input type="date" name="fecha_vencimiento" id="tareaVence" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnGuardarTarea">
                    <i class="fa-solid fa-check me-1"></i>Guardar
                </button>
            </div>
        </form>
    </div>
</div>
