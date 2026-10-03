/* ============================================================
   Comportamiento compartido - Votaciones SENA
     · Filtros en tiempo real (sin botón de búsqueda)
     · Validaciones con mensajes junto al campo
     · Protección contra envíos duplicados
   Todo es progresivo: sin JavaScript, los formularios siguen
   funcionando con su botón de siempre.
   ============================================================ */
(function () {
    'use strict';

    /* ---------- utilidades ---------- */

    function normalizar(texto) {
        return (texto || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');   /* quita tildes para comparar */
    }

    function mostrarError(campo, mensaje) {
        limpiarError(campo);
        campo.classList.add('campo-invalido');
        var aviso = document.createElement('p');
        aviso.className = 'error-campo';
        aviso.textContent = mensaje;
        campo.insertAdjacentElement('afterend', aviso);
    }

    function limpiarError(campo) {
        campo.classList.remove('campo-invalido');
        var siguiente = campo.nextElementSibling;
        if (siguiente && siguiente.classList.contains('error-campo')) {
            siguiente.remove();
        }
    }

    /* ============================================================
       1. FILTROS EN TIEMPO REAL
       ============================================================ */

    /* Solo los formularios marcados con data-filtro-vivo se auto-envían.
       La clase .filtros es solo maquetación y también la usan formularios
       de alta, que NO deben enviarse al cambiar un campo. */
    function activarFiltros() {
        document.querySelectorAll('form[data-filtro-vivo]').forEach(function (formulario) {

            /* Los <select> aplican el filtro apenas cambian */
            formulario.querySelectorAll('select').forEach(function (selector) {
                selector.addEventListener('change', function () {
                    formulario.classList.add('filtros-cargando');
                    formulario.submit();
                });
            });

            /* La búsqueda de texto filtra la tabla al instante */
            var buscador = formulario.querySelector('[data-filtra-tabla]');
            if (buscador) {
                activarBuscador(formulario, buscador);
            }

            /* Con JS el botón sobra: el filtro ya es inmediato */
            formulario.querySelectorAll('[data-solo-sin-js]').forEach(function (boton) {
                boton.hidden = true;
            });
        });
    }

    function activarBuscador(formulario, buscador) {
        var tarjeta = formulario.closest('.tarjeta') || document;
        var tabla = tarjeta.querySelector('table.tabla');
        if (!tabla) { return; }

        var cuerpo = tabla.querySelector('tbody');
        var filas = Array.prototype.slice.call(cuerpo.querySelectorAll('tr'));

        /* Ignora la fila de "no se encontraron datos" */
        filas = filas.filter(function (fila) {
            return !fila.querySelector('.vacio');
        });

        var contador = document.createElement('p');
        contador.className = 'contador-filtros';
        formulario.insertAdjacentElement('afterend', contador);

        var sinResultados = document.createElement('p');
        sinResultados.className = 'contador-filtros contador-vacio';
        sinResultados.textContent = 'Ningún registro coincide con la búsqueda.';
        sinResultados.hidden = true;
        contador.insertAdjacentElement('afterend', sinResultados);

        function filtrar() {
            var termino = normalizar(buscador.value).trim();
            var visibles = 0;

            filas.forEach(function (fila) {
                var coincide = termino === '' || normalizar(fila.textContent).indexOf(termino) !== -1;
                fila.hidden = !coincide;
                if (coincide) { visibles++; }
            });

            sinResultados.hidden = visibles !== 0;
            contador.textContent = visibles === filas.length
                ? filas.length + ' registros'
                : visibles + ' de ' + filas.length + ' registros';
        }

        /* Evita que Enter recargue la página: el filtro ya es inmediato */
        buscador.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter') { evento.preventDefault(); }
        });

        buscador.addEventListener('input', filtrar);
        filtrar();
    }

    /* ============================================================
       2. VALIDACIONES
       ============================================================ */

    /* Pares de campos donde el primero debe ser menor que el segundo */
    var RANGOS = [
        ['fechaini', 'fechafin', 'La fecha de cierre debe ser posterior a la de inicio.'],
        ['fecha_inicio', 'fecha_fin', 'La fecha de cierre debe ser posterior a la de inicio.'],
        ['hora_inicio', 'hora_fin', 'La hora de cierre debe ser posterior a la de apertura.']
    ];

    function validarRangos(formulario) {
        var valido = true;

        RANGOS.forEach(function (rango) {
            var desde = formulario.querySelector('[name="' + rango[0] + '"]');
            var hasta = formulario.querySelector('[name="' + rango[1] + '"]');
            if (!desde || !hasta || !desde.value || !hasta.value) { return; }

            if (desde.value >= hasta.value) {
                mostrarError(hasta, rango[2]);
                valido = false;
            } else {
                limpiarError(hasta);
            }
        });

        return valido;
    }

    function validarArchivos(formulario) {
        var valido = true;

        formulario.querySelectorAll('input[type="file"][accept]').forEach(function (campo) {
            if (!campo.files || campo.files.length === 0) { return; }

            var permitidas = campo.getAttribute('accept')
                .split(',')
                .map(function (extension) { return extension.trim().toLowerCase(); });

            var nombre = campo.files[0].name.toLowerCase();
            var aceptado = permitidas.some(function (permitida) {
                if (permitida === 'image/*') { return /\.(jpe?g|png|gif|webp|bmp)$/.test(nombre); }
                if (permitida.charAt(0) === '.') { return nombre.slice(-permitida.length) === permitida; }
                return true;
            });

            if (!aceptado) {
                mostrarError(campo, 'Formato no admitido. Se esperaba: ' + permitidas.join(', '));
                valido = false;
            } else {
                limpiarError(campo);
            }
        });

        return valido;
    }

    function validarNumeros(formulario) {
        var valido = true;

        formulario.querySelectorAll('input[type="number"]').forEach(function (campo) {
            if (campo.disabled || !campo.value) { return; }

            if (Number(campo.value) <= 0) {
                mostrarError(campo, 'Debe ser un número mayor que cero.');
                valido = false;
            } else {
                limpiarError(campo);
            }
        });

        return valido;
    }

    function activarValidaciones() {
        document.querySelectorAll('form').forEach(function (formulario) {
            if (formulario.hasAttribute('data-filtro-vivo')) { return; }

            /* Revalida en caliente para que el error desaparezca al corregir */
            formulario.querySelectorAll('input, select').forEach(function (campo) {
                campo.addEventListener('input', function () {
                    if (campo.classList.contains('campo-invalido')) { limpiarError(campo); }
                });
                campo.addEventListener('change', function () {
                    validarRangos(formulario);
                    validarArchivos(formulario);
                });
            });

            formulario.addEventListener('submit', function (evento) {
                var valido = validarRangos(formulario) && validarArchivos(formulario) && validarNumeros(formulario);

                if (!valido) {
                    evento.preventDefault();
                    var primero = formulario.querySelector('.campo-invalido');
                    if (primero) { primero.focus(); }
                    return;
                }

                bloquearReenvio(formulario);
            });
        });
    }

    /* ============================================================
       3. UN SOLO ENVÍO
       ============================================================ */

    function bloquearReenvio(formulario) {
        var boton = formulario.querySelector('button[type="submit"]');
        if (!boton) { return; }

        /* Si el navegador cancela el envío (por ej. un confirm), se reactiva */
        setTimeout(function () {
            boton.disabled = true;
            boton.dataset.textoOriginal = boton.textContent;
            boton.textContent = 'Procesando…';
        }, 0);

        setTimeout(function () {
            if (boton.disabled) {
                boton.disabled = false;
                if (boton.dataset.textoOriginal) { boton.textContent = boton.dataset.textoOriginal; }
            }
        }, 8000);
    }

    /* ---------- arranque ---------- */

    document.addEventListener('DOMContentLoaded', function () {
        activarFiltros();
        activarValidaciones();
    });
})();
