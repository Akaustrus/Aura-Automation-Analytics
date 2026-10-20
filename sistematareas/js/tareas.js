/**
 * Sistema de Gestión de Tareas — Aura Automation & Analytics
 * Motor AJAX: agrega, visualiza, edita y elimina tareas sin recargar la página.
 * Comunica con api.php (JSON) usando fetch().
 */
(function () {
    'use strict';

    var API = 'api.php';
    var csrf = (document.getElementById('csrfToken') || {}).value || '';

    var $lista   = document.getElementById('listaTareas');
    var $modalEl = document.getElementById('modalTarea');

    /* Modal: usa Bootstrap si el CDN está disponible; si no, respaldo manual
       para que el CRUD nunca dependa de la carga de un script externo. */
    var modal = null;
    var hayBootstrap = (typeof bootstrap !== 'undefined' && bootstrap.Modal);
    if (hayBootstrap && $modalEl) {
        modal = bootstrap.Modal.getOrCreateInstance($modalEl);
    }

    function abrirModal() {
        if (!$modalEl) return;
        if (hayBootstrap) { modal.show(); return; }
        $modalEl.style.display = 'block';
        $modalEl.classList.add('show');
        $modalEl.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
    }

    function cerrarModal() {
        if (!$modalEl) return;
        if (hayBootstrap) { modal.hide(); return; }
        $modalEl.style.display = 'none';
        $modalEl.classList.remove('show');
        $modalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    var filtroEstado     = '';
    var filtroPrioridad  = '';
    var filtroBusqueda   = '';
    var filtroResponsable = '';

    var ETIQUETAS_ESTADO = { pendiente: 'Pendiente', en_proceso: 'En proceso', completada: 'Completada' };
    var ETIQUETAS_PRIORIDAD = { baja: 'Baja', media: 'Media', alta: 'Alta', critica: 'Crítica' };
    var ICONO_PRIORIDAD = { baja: 'fa-arrow-down', media: 'fa-minus', alta: 'fa-arrow-up', critica: 'fa-fire' };

    /* Iniciales + color determinista para el avatar del responsable. */
    function iniciales(nombre) {
        var partes = String(nombre || '').trim().split(/\s+/);
        var ini = (partes[0] ? partes[0][0] : '') + (partes[1] ? partes[1][0] : '');
        return ini.toUpperCase() || '?';
    }

    function colorAvatar(id) {
        var paleta = ['#0d9488', '#0f766e', '#0284c7', '#ea580c', '#64748b', '#16a34a'];
        return paleta[(parseInt(id, 10) || 0) % paleta.length];
    }

    function chipResponsable(t) {
        if (!t.responsable) {
            return '<span class="respon-chip respon-chip--nadie"><span class="respon-avatar respon-avatar--nadie"><i class="fa-solid fa-user-slash"></i></span>Sin asignar</span>';
        }
        return '<span class="respon-chip" title="' + escapar(t.responsable_cargo || '') + '">' +
               '<span class="respon-avatar" style="background:' + colorAvatar(t.responsable_id) + '">' +
               iniciales(t.responsable) + '</span>' + escapar(t.responsable) + '</span>';
    }

    /* ================================================== */
    /*  Utilidades                                         */
    /* ================================================== */

    function toast(mensaje, tipo) {
        var $t = document.createElement('div');
        $t.className = 'toast-aura toast-' + (tipo || 'exito');
        $t.innerHTML = '<i class="fa-solid ' + (tipo === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check') + '"></i><span></span>';
        $t.querySelector('span').textContent = mensaje;
        document.body.appendChild($t);
        requestAnimationFrame(function () { $t.classList.add('visible'); });
        setTimeout(function () {
            $t.classList.remove('visible');
            setTimeout(function () { $t.remove(); }, 300);
        }, 2600);
    }

    function escapar(texto) {
        var d = document.createElement('div');
        d.textContent = texto == null ? '' : String(texto);
        return d.innerHTML;
    }

    function peticion(params, datos) {
        var opciones = { method: datos ? 'POST' : 'GET', headers: { 'X-CSRF-Token': csrf } };
        if (datos) {
            var cuerpo = new FormData();
            Object.keys(datos).forEach(function (k) { cuerpo.append(k, datos[k]); });
            opciones.body = cuerpo;
        }
        var url = API + '?' + params;
        return fetch(url, opciones).then(function (r) {
            return r.json().catch(function () { throw new Error('Respuesta inválida del servidor.'); });
        });
    }

    /* ================================================== */
    /*  Render                                             */
    /* ================================================== */

    function pintarStats(stats) {
        if (!stats) return;
        document.querySelectorAll('[data-stat]').forEach(function ($el) {
            $el.textContent = stats[$el.dataset.stat] != null ? stats[$el.dataset.stat] : 0;
        });
    }

    /* Rellena los selects de personal (modal de tarea + filtro) sin perder la selección. */
    function rellenarSelectsPersonal(personal) {
        var $selModal = document.getElementById('tareaResponsable');
        var $selFiltro = document.getElementById('filtroResponsable');

        if ($selModal) {
            var valorActual = $selModal.value;
            $selModal.innerHTML = '<option value="">— Sin asignar —</option>';
            personal.forEach(function (p) {
                var op = document.createElement('option');
                op.value = p.id;
                op.textContent = p.nombre + (p.cargo ? ' — ' + p.cargo : '');
                $selModal.appendChild(op);
            });
            $selModal.value = valorActual || '';
        }

        if ($selFiltro) {
            var valorFiltro = $selFiltro.value;
            $selFiltro.innerHTML = '<option value="">Todo el personal</option>';
            personal.forEach(function (p) {
                var op = document.createElement('option');
                op.value = p.id;
                op.textContent = p.nombre;
                $selFiltro.appendChild(op);
            });
            $selFiltro.value = valorFiltro || '';
        }
    }

    function plantillaVacia(texto) {
        return '<div class="tarea-vacio"><i class="fa-solid fa-inbox fa-2x"></i><p>' + escapar(texto) + '</p></div>';
    }

    function pintarTarea(t) {
        var item = document.createElement('div');
        item.className = 'tarea-item' + (t.estado === 'completada' ? ' completada' : '');
        item.dataset.id = t.id;

        var vencida = t.vencida ? ' vencida' : '';
        var iconoPrioridad = ICONO_PRIORIDAD[t.prioridad] || 'fa-minus';

        item.innerHTML =
            '<button type="button" class="tarea-check' + (t.estado === 'completada' ? ' checked' : '') + '" ' +
                'title="' + (t.estado === 'completada' ? 'Marcar como pendiente' : 'Marcar como completada') + '" data-accion="toggle">' +
                '<i class="fa-solid fa-check"></i>' +
            '</button>' +
            '<div class="tarea-cuerpo">' +
                '<p class="tarea-titulo">' + escapar(t.titulo) + '</p>' +
                (t.descripcion ? '<p class="tarea-desc">' + escapar(t.descripcion) + '</p>' : '') +
                '<div class="tarea-meta">' +
                    chipResponsable(t) +
                    '<span class="prioridad prioridad-' + t.prioridad + '"><i class="fa-solid ' + iconoPrioridad + ' me-1"></i>' + ETIQUETAS_PRIORIDAD[t.prioridad] + '</span>' +
                    '<span class="estado estado-' + t.estado + '">' + ETIQUETAS_ESTADO[t.estado] + '</span>' +
                    (t.fecha_vencimiento_fmt
                        ? '<span class="tarea-vencimiento' + vencida + '"><i class="fa-regular fa-calendar me-1"></i>' +
                          (t.vencida ? 'Vencida · ' : 'Vence · ') + t.fecha_vencimiento_fmt + '</span>'
                        : '') +
                    '<span><i class="fa-regular fa-clock me-1"></i>Creada: ' + t.fecha_creacion_fmt + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="tarea-acciones">' +
                '<button type="button" class="btn btn-sm btn-outline-primary" data-accion="editar" title="Editar">' +
                    '<i class="fa-solid fa-pen"></i></button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger" data-accion="eliminar" title="Eliminar">' +
                    '<i class="fa-solid fa-trash"></i></button>' +
            '</div>';

        return item;
    }

    function cargar() {
        if (!$lista) return;

        var vista = $lista.dataset.vista || '';
        var params = 'accion=listar';
        if (filtroEstado) params += '&estado=' + encodeURIComponent(filtroEstado);
        if (filtroPrioridad) params += '&prioridad=' + encodeURIComponent(filtroPrioridad);
        if (filtroResponsable) params += '&responsable=' + encodeURIComponent(filtroResponsable);
        if (filtroBusqueda) params += '&busqueda=' + encodeURIComponent(filtroBusqueda);

        peticion(params).then(function (json) {
            if (!json.ok) throw new Error(json.mensaje);

            if (json.datos.personal) {
                rellenarSelectsPersonal(json.datos.personal);
            }

            var tareas = json.datos.tareas;
            if (vista === 'por_vencer') {
                tareas = tareas.filter(function (t) {
                    return t.fecha_vencimiento && t.estado !== 'completada';
                });
                tareas.sort(function (a, b) { return a.fecha_vencimiento.localeCompare(b.fecha_vencimiento); });
            }

            pintarStats(json.datos.estadisticas);

            if (!tareas.length) {
                $lista.innerHTML = plantillaVacia(
                    vista === 'por_vencer' ? 'No hay tareas próximas a vencer. ¡Todo al día!' : 'No hay tareas. ¡Crea la primera!'
                );
                return;
            }

            var fragmento = document.createDocumentFragment();
            tareas.forEach(function (t) { fragmento.appendChild(pintarTarea(t)); });
            $lista.innerHTML = '';
            $lista.appendChild(fragmento);
        }).catch(function (e) {
            $lista.innerHTML = plantillaVacia('No se pudo cargar las tareas: ' + e.message);
        });
    }

    /* ================================================== */
    /*  Acciones                                           */
    /* ================================================== */

    function guardarTarea(form) {
        var datos = {
            action: 'guardar',
            id: form.querySelector('#tareaId').value,
            titulo: form.querySelector('#tareaTitulo').value.trim(),
            descripcion: form.querySelector('#tareaDescripcion').value.trim(),
            prioridad: form.querySelector('#tareaPrioridad').value,
            estado: form.querySelector('#tareaEstado').value,
            responsable_id: form.querySelector('#tareaResponsable') ? form.querySelector('#tareaResponsable').value : '',
            fecha_vencimiento: form.querySelector('#tareaVence').value
        };

        if (!datos.titulo) {
            toast('El título es obligatorio.', 'error');
            return;
        }

        peticion('', datos).then(function (json) {
            toast(json.mensaje, json.ok ? 'exito' : 'error');
            if (json.ok) {
                cerrarModal();
                form.reset();
                form.querySelector('#tareaId').value = '';
                document.getElementById('modalTareaLabel').textContent = 'Nueva tarea';
                cargar();
            }
        }).catch(function (e) { toast(e.message, 'error'); });
    }

    function eliminarTarea(id, item) {
        if (!confirm('¿Eliminar esta tarea? Esta acción no se puede deshacer.')) return;

        peticion('', { action: 'eliminar', id: id }).then(function (json) {
            toast(json.mensaje, json.ok ? 'exito' : 'error');
            if (json.ok) {
                item.classList.add('eliminando');
                setTimeout(cargar, 250);
            }
        }).catch(function (e) { toast(e.message, 'error'); });
    }

    function alternarEstado(id) {
        var $item = document.querySelector('.tarea-item[data-id="' + id + '"]');
        var estabaCompletada = $item && $item.classList.contains('completada');
        var nuevoEstado = estabaCompletada ? 'pendiente' : 'completada';

        peticion('', { action: 'cambiar_estado', id: id, estado: nuevoEstado }).then(function (json) {
            toast(json.mensaje, json.ok ? 'exito' : 'error');
            if (json.ok) cargar();
        }).catch(function (e) { toast(e.message, 'error'); });
    }

    function editarTarea(id) {
        peticion('accion=obtener&id=' + id).then(function (json) {
            if (!json.ok) { toast(json.mensaje, 'error'); return; }
            var t = json.datos;
            var form = document.getElementById('formTarea');
            form.querySelector('#tareaId').value = t.id;
            form.querySelector('#tareaTitulo').value = t.titulo;
            form.querySelector('#tareaDescripcion').value = t.descripcion;
            form.querySelector('#tareaPrioridad').value = t.prioridad;
            form.querySelector('#tareaEstado').value = t.estado;
            if (form.querySelector('#tareaResponsable')) {
                form.querySelector('#tareaResponsable').value = t.responsable_id || '';
            }
            form.querySelector('#tareaVence').value = t.fecha_vencimiento || '';
            document.getElementById('modalTareaLabel').textContent = 'Editar tarea';
            abrirModal();
        }).catch(function (e) { toast(e.message, 'error'); });
    }

    /* ================================================== */
    /*  Personal (vista independiente)                     */
    /* ================================================== */

    var $listaPersonal = document.getElementById('listaPersonal');
    var $modalPersonalEl = document.getElementById('modalPersonal');
    var modalPersonal = null;
    if ($modalPersonalEl && hayBootstrap) {
        modalPersonal = bootstrap.Modal.getOrCreateInstance($modalPersonalEl);
    }

    function abrirModalPersonal() {
        if (!$modalPersonalEl) return;
        if (modalPersonal) { modalPersonal.show(); return; }
        $modalPersonalEl.style.display = 'block';
        $modalPersonalEl.classList.add('show');
        $modalPersonalEl.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
    }

    function cerrarModalPersonal() {
        if (!$modalPersonalEl) return;
        if (modalPersonal) { modalPersonal.hide(); return; }
        $modalPersonalEl.style.display = 'none';
        $modalPersonalEl.classList.remove('show');
        $modalPersonalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    function pintarPersonal(miembros) {
        if (!miembros.length) {
            $listaPersonal.innerHTML = plantillaVacia('No hay miembros registrados. ¡Agrega el primero!');
            return;
        }

        var fragmento = document.createDocumentFragment();
        miembros.forEach(function (p) {
            var item = document.createElement('div');
            item.className = 'tarea-item';
            item.dataset.personalId = p.id;

            var abiertas = parseInt(p.tareas_abiertas, 10) || 0;
            var total = parseInt(p.total_tareas, 10) || 0;

            item.innerHTML =
                '<span class="respon-avatar respon-avatar--grande" style="background:' + colorAvatar(p.id) + '">' +
                    iniciales(p.nombre) + '</span>' +
                '<div class="tarea-cuerpo">' +
                    '<p class="tarea-titulo">' + escapar(p.nombre) + '</p>' +
                    '<p class="tarea-desc">' + escapar(p.cargo || 'Sin cargo definido') +
                        (p.email ? ' · <i class="fa-regular fa-envelope me-1"></i>' + escapar(p.email) : '') + '</p>' +
                    '<div class="tarea-meta">' +
                        '<span class="prioridad prioridad-alta"><i class="fa-solid fa-list-check me-1"></i>' + abiertas + ' abierta(s)</span>' +
                        '<span class="prioridad prioridad-baja"><i class="fa-solid fa-check-double me-1"></i>' + total + ' total</span>' +
                    '</div>' +
                '</div>' +
                '<div class="tarea-acciones">' +
                    '<button type="button" class="btn btn-sm btn-outline-primary" data-accion-personal="editar" title="Editar">' +
                        '<i class="fa-solid fa-pen"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" data-accion-personal="eliminar" title="Eliminar">' +
                        '<i class="fa-solid fa-trash"></i></button>' +
                '</div>';

            fragmento.appendChild(item);
        });

        $listaPersonal.innerHTML = '';
        $listaPersonal.appendChild(fragmento);
    }

    function cargarPersonal() {
        if (!$listaPersonal) return;
        peticion('accion=listar_personal').then(function (json) {
            if (!json.ok) throw new Error(json.mensaje);
            pintarPersonal(json.datos.personal);
        }).catch(function (e) {
            $listaPersonal.innerHTML = plantillaVacia('No se pudo cargar el personal: ' + e.message);
        });
    }

    function guardarPersonal(form) {
        var datos = {
            action: 'guardar_personal',
            id: form.querySelector('#personalId').value,
            nombre: form.querySelector('#personalNombre').value.trim(),
            cargo: form.querySelector('#personalCargo').value.trim(),
            email: form.querySelector('#personalEmail').value.trim()
        };

        if (!datos.nombre) {
            toast('El nombre es obligatorio.', 'error');
            return;
        }

        peticion('', datos).then(function (json) {
            toast(json.mensaje, json.ok ? 'exito' : 'error');
            if (json.ok) {
                cerrarModalPersonal();
                form.reset();
                form.querySelector('#personalId').value = '';
                document.getElementById('modalPersonalLabel').textContent = 'Nuevo miembro';
                cargarPersonal();
            }
        }).catch(function (e) { toast(e.message, 'error'); });
    }

    function eliminarPersonal(id, item) {
        if (!confirm('¿Eliminar a este miembro? Sus tareas quedarán sin asignar.')) return;

        peticion('', { action: 'eliminar_personal', id: id }).then(function (json) {
            toast(json.mensaje, json.ok ? 'exito' : 'error');
            if (json.ok) {
                item.classList.add('eliminando');
                setTimeout(cargarPersonal, 250);
            }
        }).catch(function (e) { toast(e.message, 'error'); });
    }

    function editarPersonal(id) {
        peticion('accion=listar_personal').then(function (json) {
            if (!json.ok) { toast(json.mensaje, 'error'); return; }
            var p = (json.datos.personal || []).filter(function (m) { return parseInt(m.id, 10) === parseInt(id, 10); })[0];
            if (!p) { toast('Miembro no encontrado.', 'error'); return; }

            var form = document.getElementById('formPersonal');
            form.querySelector('#personalId').value = p.id;
            form.querySelector('#personalNombre').value = p.nombre;
            form.querySelector('#personalCargo').value = p.cargo || '';
            form.querySelector('#personalEmail').value = p.email || '';
            document.getElementById('modalPersonalLabel').textContent = 'Editar miembro';
            abrirModalPersonal();
        }).catch(function (e) { toast(e.message, 'error'); });
    }

    /* ================================================== */
    /*  Eventos                                           */
    /* ================================================== */

    document.addEventListener('DOMContentLoaded', function () {

        // Delegación de eventos en la lista (editar / eliminar / toggle)
        if ($lista) {
            $lista.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-accion]');
                if (!btn) return;
                var item = btn.closest('.tarea-item');
                if (!item) return;
                var id = item.dataset.id;
                var accion = btn.dataset.accion;

                if (accion === 'eliminar') eliminarTarea(id, item);
                else if (accion === 'toggle') alternarEstado(id);
                else if (accion === 'editar') editarTarea(id);
            });
        }

        // Botón "Nueva tarea"
        var $btnNueva = document.getElementById('btnNuevaTarea');
        if ($btnNueva) {
            $btnNueva.addEventListener('click', function () {
                var form = document.getElementById('formTarea');
                form.reset();
                form.querySelector('#tareaId').value = '';
                document.getElementById('modalTareaLabel').textContent = 'Nueva tarea';
                abrirModal();
            });
        }

        // Envío AJAX del formulario
        var $form = document.getElementById('formTarea');
        if ($form) {
            $form.addEventListener('submit', function (e) {
                e.preventDefault();
                guardarTarea($form);
            });
        }

        // Cierre de modales (respaldo si el CDN de Bootstrap no cargó)
        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-bs-dismiss="modal"]')) {
                cerrarModal();
                cerrarModalPersonal();
            }
        });

        /* ---- Personal: eventos (solo si la vista está activa) ---- */
        if ($listaPersonal) {
            $listaPersonal.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-accion-personal]');
                if (!btn) return;
                var item = btn.closest('[data-personal-id]');
                if (!item) return;
                var id = item.dataset.personalId;

                if (btn.dataset.accionPersonal === 'eliminar') eliminarPersonal(id, item);
                else if (btn.dataset.accionPersonal === 'editar') editarPersonal(id);
            });

            var $btnNuevoPersonal = document.getElementById('btnNuevoPersonal');
            if ($btnNuevoPersonal) {
                $btnNuevoPersonal.addEventListener('click', function () {
                    var form = document.getElementById('formPersonal');
                    form.reset();
                    form.querySelector('#personalId').value = '';
                    document.getElementById('modalPersonalLabel').textContent = 'Nuevo miembro';
                    abrirModalPersonal();
                });
            }

            var $formPersonal = document.getElementById('formPersonal');
            if ($formPersonal) {
                $formPersonal.addEventListener('submit', function (e) {
                    e.preventDefault();
                    guardarPersonal($formPersonal);
                });
            }
        }

        // Filtros de estado
        document.querySelectorAll('.filtro-btn[data-estado]').forEach(function ($btn) {
            $btn.addEventListener('click', function () {
                document.querySelectorAll('.filtro-btn[data-estado]').forEach(function (b) { b.classList.remove('active'); });
                $btn.classList.add('active');
                filtroEstado = $btn.dataset.estado;
                cargar();
            });
        });

        // Filtro de prioridad
        var $prioridad = document.getElementById('filtroPrioridad');
        if ($prioridad) {
            $prioridad.addEventListener('change', function () {
                filtroPrioridad = $prioridad.value;
                cargar();
            });
        }

        // Filtro de responsable
        var $responsable = document.getElementById('filtroResponsable');
        if ($responsable) {
            $responsable.addEventListener('change', function () {
                filtroResponsable = $responsable.value;
                cargar();
            });
        }

        // Búsqueda con debounce
        var $busqueda = document.getElementById('filtroBusqueda');
        var temporizador = null;
        if ($busqueda) {
            $busqueda.addEventListener('input', function () {
                clearTimeout(temporizador);
                temporizador = setTimeout(function () {
                    filtroBusqueda = $busqueda.value.trim();
                    cargar();
                }, 300);
            });
        }

        cargar();
        cargarPersonal();
    });
})();
