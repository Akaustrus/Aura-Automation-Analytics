<?php
/**
 * Vista principal: lista de tareas con alta / edición / eliminación por AJAX.
 * Todo el listado lo renderiza js/tareas.js sobre #listaTareas.
 */
$csrf = tokenCsrf();
?>
<input type="hidden" id="csrfToken" value="<?= limpiar($csrf) ?>">

<header class="toolbar">
    <div>
        <h2 class="page-title mb-0"><i class="fa-solid fa-list-check me-2"></i>Gestión de tareas</h2>
        <p class="toolbar-sub mb-0">Agrega, consulta y elimina tareas sin recargar la página.</p>
    </div>
    <button type="button" class="btn btn-primary" id="btnNuevaTarea">
        <i class="fa-solid fa-plus me-1"></i>Nueva tarea
    </button>
</header>

<!-- Tarjetas de resumen -->
<div class="row g-3 mb-4" id="resumenTareas">
    <div class="col-6 col-md-3">
        <div class="stat stat-1">
            <div class="stat-icon"><i class="fa-solid fa-layer-group"></i></div>
            <div class="stat-num" data-stat="total">0</div>
            <div class="stat-label">Total tareas</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat stat-2">
            <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="stat-num" data-stat="pendiente">0</div>
            <div class="stat-label">Pendientes</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat stat-3">
            <div class="stat-icon"><i class="fa-solid fa-spinner"></i></div>
            <div class="stat-num" data-stat="en_proceso">0</div>
            <div class="stat-label">En proceso</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat stat-4">
            <div class="stat-icon"><i class="fa-solid fa-check-double"></i></div>
            <div class="stat-num" data-stat="completada">0</div>
            <div class="stat-label">Completadas</div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="filtros align-items-center">
            <button class="filtro-btn active" data-estado="">Todas</button>
            <button class="filtro-btn" data-estado="pendiente">Pendientes</button>
            <button class="filtro-btn" data-estado="en_proceso">En proceso</button>
            <button class="filtro-btn" data-estado="completada">Completadas</button>
            <div class="ms-auto d-flex gap-2 flex-wrap">
                <select id="filtroResponsable" class="form-select form-select-sm filtro-select">
                    <option value="">Todo el personal</option>
                </select>
                <select id="filtroPrioridad" class="form-select form-select-sm filtro-select">
                    <option value="">Toda prioridad</option>
                    <option value="critica">Crítica</option>
                    <option value="alta">Alta</option>
                    <option value="media">Media</option>
                    <option value="baja">Baja</option>
                </select>
                <input type="search" id="filtroBusqueda" class="form-control form-control-sm filtro-select" placeholder="Buscar tarea…">
            </div>
        </div>
    </div>
</div>

<!-- Lista de tareas -->
<div class="card">
    <div class="card-body p-0">
        <div id="listaTareas" class="tarea-lista">
            <div class="tarea-vacio"><i class="fa-solid fa-circle-notch fa-spin fa-2x"></i><p>Cargando tareas…</p></div>
        </div>
    </div>
</div>

<!-- Modal crear / editar tarea -->
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
                    <input type="text" name="titulo" id="tareaTitulo" class="form-control" required maxlength="150" placeholder="Ej. Preparar reporte mensual">
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" id="tareaDescripcion" class="form-control" rows="3" maxlength="1000" placeholder="Detalles adicionales de la tarea…"></textarea>
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
