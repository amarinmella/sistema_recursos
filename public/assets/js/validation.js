/**
 * Scripts de validación para los formularios
 * Sistema de Gestión de Recursos
 */

// Función principal de inicialización
document.addEventListener("DOMContentLoaded", function () {
  // Inicializar validación de formulario de login
  initLoginValidation();

  // Inicializar validación de formulario de recursos
  initResourceFormValidation();

  // Inicializar confirmaciones para eliminación
  initDeleteConfirmations();
});

/**
 * Validación del formulario de login
 */
function initLoginValidation() {
  const loginForm = document.querySelector("form.login-form");

  if (loginForm) {
    loginForm.addEventListener("submit", function (event) {
      // Obtener campos
      const emailField = document.getElementById("email");
      const passwordField = document.getElementById("password");

      let isValid = true;

      // Validar email
      if (!validateEmail(emailField.value)) {
        isValid = false;
        showError(emailField, "Ingresa un email válido");
      } else {
        removeError(emailField);
      }

      // Validar contraseña
      if (passwordField.value.trim() === "") {
        isValid = false;
        showError(passwordField, "La contraseña es obligatoria");
      } else {
        removeError(passwordField);
      }

      // Si no es válido, prevenir envío
      if (!isValid) {
        event.preventDefault();
      }
    });
  }
}

/**
 * Validación del formulario de recursos
 */
function initResourceFormValidation() {
  const resourceForm = document.querySelector("form.resource-form");

  if (resourceForm) {
    resourceForm.addEventListener("submit", function (event) {
      let hasError = false;

      // Validar nombre
      const nombreField = document.getElementById("nombre");
      if (nombreField && nombreField.value.trim() === "") {
        showError(nombreField, "El nombre es obligatorio");
        hasError = true;
      } else if (nombreField) {
        removeError(nombreField);
      }

      // Validar tipo
      const tipoField = document.getElementById("id_tipo");
      if (tipoField && tipoField.value === "") {
        showError(tipoField, "Debe seleccionar un tipo de recurso");
        hasError = true;
      } else if (tipoField) {
        removeError(tipoField);
      }

      // Si hay errores, prevenir envío
      if (hasError) {
        event.preventDefault();
      }
    });
  }
}

/**
 * Inicializar confirmaciones para eliminar
 */
function initDeleteConfirmations() {
  const deleteLinks = document.querySelectorAll(".btn-eliminar");

  deleteLinks.forEach((link) => {
    link.addEventListener("click", function (event) {
      if (
        !confirm(
          "¿Estás seguro de eliminar este elemento? Esta acción no se puede deshacer."
        )
      ) {
        event.preventDefault();
      }
    });
  });
}

/**
 * Validar formato de email
 * @param {string} email - Email a validar
 * @returns {boolean} - true si es válido, false si no
 */
function validateEmail(email) {
  const regex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
  return regex.test(email);
}

/**
 * Mostrar mensaje de error para un campo
 * @param {HTMLElement} field - Campo con error
 * @param {string} message - Mensaje de error
 */
function showError(field, message) {
  // Remover error previo si existe
  removeError(field);

  // Crear elemento de error
  const errorDiv = document.createElement("div");
  errorDiv.className = "error-message";
  errorDiv.textContent = message;

  // Añadir borde rojo al campo
  field.style.borderColor = "#e74c3c";

  // Insertar mensaje de error después del campo
  field.parentNode.appendChild(errorDiv);
}

/**
 * Remover mensaje de error de un campo
 * @param {HTMLElement} field - Campo del que remover error
 */
function removeError(field) {
  field.style.borderColor = "";

  // Buscar y eliminar mensajes de error existentes
  const parent = field.parentNode;
  const errorDiv = parent.querySelector(".error-message");

  if (errorDiv) {
    parent.removeChild(errorDiv);
  }
}

/**
 * Validar fecha
 * @param {string} dateString - Fecha en formato YYYY-MM-DD
 * @returns {boolean} - true si es válido, false si no
 */
function validateDate(dateString) {
  // Formato básico YYYY-MM-DD
  if (!/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
    return false;
  }

  // Validar fecha real
  const parts = dateString.split("-");
  const year = parseInt(parts[0], 10);
  const month = parseInt(parts[1], 10) - 1;
  const day = parseInt(parts[2], 10);

  const date = new Date(year, month, day);

  return (
    date.getFullYear() === year &&
    date.getMonth() === month &&
    date.getDate() === day
  );
}

/**
 * Validar hora
 * @param {string} timeString - Hora en formato HH:MM
 * @returns {boolean} - true si es válido, false si no
 */
function validateTime(timeString) {
  // Formato básico HH:MM
  if (!/^\d{2}:\d{2}$/.test(timeString)) {
    return false;
  }

  // Validar hora real
  const parts = timeString.split(":");
  const hours = parseInt(parts[0], 10);
  const minutes = parseInt(parts[1], 10);

  return hours >= 0 && hours <= 23 && minutes >= 0 && minutes <= 59;
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
 * Deshabilitar botones de submit después de enviar para prevenir doble envío
 * @param {HTMLElement} form - Formulario a procesar
 */
function preventDoubleSubmission(form) {
  const submitButtons = form.querySelectorAll(
    'button[type="submit"], input[type="submit"]'
  );

  form.addEventListener("submit", function () {
    submitButtons.forEach((button) => {
      button.disabled = true;
      if (button.tagName === "BUTTON") {
        const originalText = button.textContent;
        button.setAttribute("data-original-text", originalText);
        button.textContent = "Procesando...";
      }
    });
  });
}
