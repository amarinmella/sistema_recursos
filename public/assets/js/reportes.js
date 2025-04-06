/**
 * JavaScript para el módulo de Reportes
 */

document.addEventListener("DOMContentLoaded", function () {
  // Inicializar gráficos si están presentes en la página
  initCharts();

  // Configurar interacciones de filtros
  setupFilters();

  // Configurar eventos de exportación
  setupExportEvents();
});

/**
 * Inicializa los gráficos en la página de reportes
 */
function initCharts() {
  // Gráfico de recursos más utilizados
  const recursosChart = document.getElementById("recursos-chart");
  if (recursosChart) {
    renderRecursosChart(recursosChart);
  }

  // Gráfico de estado de reservas
  const estadoChart = document.getElementById("estado-chart");
  if (estadoChart) {
    renderEstadoChart(estadoChart);
  }

  // Gráfico de tendencias de reservas (si existe)
  const tendenciasChart = document.getElementById("tendencias-chart");
  if (tendenciasChart) {
    renderTendenciasChart(tendenciasChart);
  }

  // Gráfico de disponibilidad (si existe)
  const disponibilidadChart = document.getElementById("disponibilidad-chart");
  if (disponibilidadChart) {
    renderDisponibilidadChart(disponibilidadChart);
  }

  // Gráfico de actividad de usuarios (si existe)
  const actividadUsuariosChart = document.getElementById(
    "actividad-usuarios-chart"
  );
  if (actividadUsuariosChart) {
    renderActividadUsuariosChart(actividadUsuariosChart);
  }

  // Gráfico de mantenimientos (si existe)
  const mantenimientosChart = document.getElementById("mantenimientos-chart");
  if (mantenimientosChart) {
    renderMantenimientosChart(mantenimientosChart);
  }
}

/**
 * Renderiza el gráfico de recursos más utilizados
 * @param {HTMLElement} container - Elemento contenedor del gráfico
 */
function renderRecursosChart(container) {
  // Aquí se implementaría la lógica para renderizar el gráfico
  // usando una biblioteca como Chart.js o similar

  // Ejemplo de implementación (pseudocódigo):
  /*
    const data = [...]; // Obtener datos de la tabla o de un dataset en el HTML
    
    new Chart(container, {
        type: 'bar',
        data: {
            labels: data.map(item => item.nombre),
            datasets: [{
                label: 'Número de Reservas',
                data: data.map(item => item.total),
                backgroundColor: '#4a90e2'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    */

  // Mensaje temporal en lugar del gráfico
  container.innerHTML =
    '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6c757d;">Gráfico de recursos más utilizados</div>';
}

/**
 * Renderiza el gráfico de estado de reservas
 * @param {HTMLElement} container - Elemento contenedor del gráfico
 */
function renderEstadoChart(container) {
  // Mensaje temporal en lugar del gráfico
  container.innerHTML =
    '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6c757d;">Gráfico de estado de reservas</div>';
}

/**
 * Renderiza el gráfico de tendencias de reservas
 * @param {HTMLElement} container - Elemento contenedor del gráfico
 */
function renderTendenciasChart(container) {
  // Mensaje temporal en lugar del gráfico
  container.innerHTML =
    '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6c757d;">Gráfico de tendencias de reservas</div>';
}

/**
 * Renderiza el gráfico de disponibilidad de recursos
 * @param {HTMLElement} container - Elemento contenedor del gráfico
 */
function renderDisponibilidadChart(container) {
  // Mensaje temporal en lugar del gráfico
  container.innerHTML =
    '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6c757d;">Gráfico de disponibilidad de recursos</div>';
}

/**
 * Renderiza el gráfico de actividad de usuarios
 * @param {HTMLElement} container - Elemento contenedor del gráfico
 */
function renderActividadUsuariosChart(container) {
  // Mensaje temporal en lugar del gráfico
  container.innerHTML =
    '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6c757d;">Gráfico de actividad de usuarios</div>';
}

/**
 * Renderiza el gráfico de mantenimientos
 * @param {HTMLElement} container - Elemento contenedor del gráfico
 */
function renderMantenimientosChart(container) {
  // Mensaje temporal en lugar del gráfico
  container.innerHTML =
    '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6c757d;">Gráfico de mantenimientos</div>';
}

/**
 * Configura interacciones para filtros de reportes
 */
function setupFilters() {
  // Buscar formularios de filtros
  const filterForms = document.querySelectorAll(".report-filters form");

  filterForms.forEach((form) => {
    // Evento para resetear filtros
    const resetBtn = form.querySelector(".btn-reset");
    if (resetBtn) {
      resetBtn.addEventListener("click", function (e) {
        e.preventDefault();

        // Limpiar todos los campos del formulario
        const inputs = form.querySelectorAll(
          'input:not([type="submit"]), select'
        );
        inputs.forEach((input) => {
          if (input.type === "checkbox" || input.type === "radio") {
            input.checked = false;
          } else {
            input.value = "";
          }
        });

        // Si hay un select con opción por defecto, seleccionarla
        const selects = form.querySelectorAll("select");
        selects.forEach((select) => {
          if (select.options.length > 0) {
            select.selectedIndex = 0;
          }
        });
      });
    }

    // Evento para aplicar filtros automáticamente en cambios de select
    const selects = form.querySelectorAll('select[data-auto-submit="true"]');
    selects.forEach((select) => {
      select.addEventListener("change", function () {
        form.submit();
      });
    });
  });
}

/**
 * Configura eventos para exportación de datos
 */
function setupExportEvents() {
  // Evento para exportación a CSV
  const csvButtons = document.querySelectorAll(".btn-export-csv");
  csvButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      const targetUrl = this.getAttribute("data-url");

      if (targetUrl) {
        window.location.href = targetUrl;
      } else {
        alert("URL de exportación no configurada");
      }
    });
  });

  // Evento para exportación a PDF
  const pdfButtons = document.querySelectorAll(".btn-export-pdf");
  pdfButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      const targetUrl = this.getAttribute("data-url");

      if (targetUrl) {
        window.location.href = targetUrl;
      } else {
        alert("URL de exportación no configurada");
      }
    });
  });

  // Evento para exportación a Excel
  const excelButtons = document.querySelectorAll(".btn-export-excel");
  excelButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      const targetUrl = this.getAttribute("data-url");

      if (targetUrl) {
        window.location.href = targetUrl;
      } else {
        alert("URL de exportación no configurada");
      }
    });
  });
}

/**
 * Función auxiliar para formatear fechas
 * @param {Date} date - Objeto de fecha a formatear
 * @returns {string} - Fecha formateada como DD/MM/YYYY
 */
function formatDate(date) {
  const day = String(date.getDate()).padStart(2, "0");
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const year = date.getFullYear();

  return `${day}/${month}/${year}`;
}

/**
 * Función auxiliar para formatear números
 * @param {number} number - Número a formatear
 * @returns {string} - Número formateado con separadores de miles
 */
function formatNumber(number) {
  return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
