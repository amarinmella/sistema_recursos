/**
 * Archivo principal de JavaScript
 * Sistema de Gestión de Recursos
 */

document.addEventListener("DOMContentLoaded", function () {
  // Inicializar componentes
  initAlerts();
  setupMobileMenu();
});

/**
 * Inicializar alertas con autoclose
 */
function initAlerts() {
  const alerts = document.querySelectorAll(".alert:not(.alert-persistent)");

  alerts.forEach((alert) => {
    // Auto cerrar después de 5 segundos
    setTimeout(() => {
      if (alert.parentNode) {
        alert.style.opacity = "0";
        setTimeout(() => {
          if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
          }
        }, 300);
      }
    }, 5000);

    // Añadir botón de cierre
    const closeButton = document.createElement("button");
    closeButton.innerHTML = "&times;";
    closeButton.className = "alert-close";
    closeButton.style.background = "transparent";
    closeButton.style.border = "none";
    closeButton.style.float = "right";
    closeButton.style.fontSize = "20px";
    closeButton.style.fontWeight = "bold";
    closeButton.style.cursor = "pointer";
    closeButton.style.color = "inherit";

    closeButton.addEventListener("click", () => {
      alert.style.opacity = "0";
      setTimeout(() => {
        if (alert.parentNode) {
          alert.parentNode.removeChild(alert);
        }
      }, 300);
    });

    alert.appendChild(closeButton);
  });
}

/**
 * Configurar menú para dispositivos móviles
 */
function setupMobileMenu() {
  const sidebar = document.querySelector(".sidebar");

  // Crear botón para móviles
  if (sidebar && !document.querySelector(".mobile-menu-toggle")) {
    const toggle = document.createElement("button");
    toggle.className = "mobile-menu-toggle";
    toggle.innerHTML = "☰";
    toggle.style.position = "fixed";
    toggle.style.top = "10px";
    toggle.style.left = "10px";
    toggle.style.zIndex = "1000";
    toggle.style.backgroundColor = "var(--primary-color)";
    toggle.style.color = "white";
    toggle.style.border = "none";
    toggle.style.borderRadius = "4px";
    toggle.style.padding = "8px 12px";
    toggle.style.fontSize = "18px";
    toggle.style.cursor = "pointer";
    toggle.style.display = "none";

    toggle.addEventListener("click", function () {
      sidebar.classList.toggle("mobile-open");
    });

    document.body.appendChild(toggle);

    // Mostrar/ocultar según tamaño de pantalla
    const checkWidth = function () {
      if (window.innerWidth < 768) {
        toggle.style.display = "block";
        sidebar.classList.add("mobile-sidebar");
        sidebar.style.position = "fixed";
        sidebar.style.left = "-250px";
        sidebar.style.top = "0";
        sidebar.style.height = "100%";
        sidebar.style.zIndex = "999";
        sidebar.style.transition = "left 0.3s ease";

        if (sidebar.classList.contains("mobile-open")) {
          sidebar.style.left = "0";
        } else {
          sidebar.style.left = "-250px";
        }
      } else {
        toggle.style.display = "none";
        sidebar.classList.remove("mobile-sidebar", "mobile-open");
        sidebar.style.position = "";
        sidebar.style.left = "";
        sidebar.style.top = "";
        sidebar.style.height = "";
        sidebar.style.zIndex = "";
      }
    };

    // Verificar al cargar y al cambiar tamaño
    checkWidth();
    window.addEventListener("resize", checkWidth);
  }
}

/**
 * Formatear fecha para mostrar
 * @param {string} dateString - Fecha en formato YYYY-MM-DD
 * @returns {string} - Fecha formateada DD/MM/YYYY
 */
function formatDate(dateString) {
  if (!dateString) return "";

  const parts = dateString.split("-");
  if (parts.length !== 3) return dateString;

  return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

/**
 * Confirmar acción peligrosa
 * @param {string} message - Mensaje de confirmación
 * @returns {boolean} - true si confirma, false si cancela
 */
function confirmar(message) {
  return confirm(message || "¿Estás seguro de realizar esta acción?");
}
