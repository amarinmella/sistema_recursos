/**
 * JavaScript principal para el Sistema de Gestión de Recursos
 * Maneja la funcionalidad responsiva y móvil
 */

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar funcionalidad móvil
    initMobileMenu();
    
    // Inicializar funcionalidades generales
    initGeneralFeatures();
    
    // Inicializar formularios responsivos
    initResponsiveForms();
});

/**
 * Inicializar menú móvil
 */
function initMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const content = document.querySelector('.content');
    const overlay = document.querySelector('.sidebar-overlay');
    
    // Crear botón de menú si no existe
    if (!menuToggle && sidebar) {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'menu-toggle';
        toggleBtn.innerHTML = '☰';
        toggleBtn.setAttribute('aria-label', 'Abrir menú');
        toggleBtn.style.display = 'block';
        document.body.appendChild(toggleBtn);
        
        // Crear overlay si no existe
        if (!overlay) {
            const overlayDiv = document.createElement('div');
            overlayDiv.className = 'sidebar-overlay';
            document.body.appendChild(overlayDiv);
        }
    }
    
    // Funcionalidad del botón de menú
    if (menuToggle) {
        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            sidebar.classList.toggle('open');
            const overlayElement = document.querySelector('.sidebar-overlay');
            if (overlayElement) {
                overlayElement.classList.toggle('active');
            }
            
            // Cambiar ícono
            if (sidebar.classList.contains('open')) {
                menuToggle.innerHTML = '✕';
                menuToggle.setAttribute('aria-label', 'Cerrar menú');
                document.body.style.overflow = 'hidden'; // Prevenir scroll del body
            } else {
                menuToggle.innerHTML = '☰';
                menuToggle.setAttribute('aria-label', 'Abrir menú');
                document.body.style.overflow = ''; // Restaurar scroll
            }
        });
    }
    
    // Cerrar menú al hacer clic en overlay
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = ''; // Restaurar scroll
            if (menuToggle) {
                menuToggle.innerHTML = '☰';
                menuToggle.setAttribute('aria-label', 'Abrir menú');
            }
        });
    }
    
    // Cerrar menú al hacer clic en enlaces del sidebar
    const navItems = document.querySelectorAll('.sidebar .nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                sidebar.classList.remove('open');
                const overlayElement = document.querySelector('.sidebar-overlay');
                if (overlayElement) {
                    overlayElement.classList.remove('active');
                }
                document.body.style.overflow = ''; // Restaurar scroll
                if (menuToggle) {
                    menuToggle.innerHTML = '☰';
                    menuToggle.setAttribute('aria-label', 'Abrir menú');
                }
            }
        });
    });
    
    // Manejar redimensionamiento de ventana
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('open');
                const overlayElement = document.querySelector('.sidebar-overlay');
                if (overlayElement) {
                    overlayElement.classList.remove('active');
                }
                document.body.style.overflow = ''; // Restaurar scroll
                if (menuToggle) {
                    menuToggle.innerHTML = '☰';
                    menuToggle.setAttribute('aria-label', 'Abrir menú');
                }
            }
        }, 250);
    });
    
    // Cerrar menú con tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            const overlayElement = document.querySelector('.sidebar-overlay');
            if (overlayElement) {
                overlayElement.classList.remove('active');
            }
            document.body.style.overflow = ''; // Restaurar scroll
            if (menuToggle) {
                menuToggle.innerHTML = '☰';
                menuToggle.setAttribute('aria-label', 'Abrir menú');
            }
        }
    });
    
    // Asegurar que el menú esté cerrado al cargar en móvil
    if (window.innerWidth <= 992) {
        sidebar.classList.remove('open');
        const overlayElement = document.querySelector('.sidebar-overlay');
        if (overlayElement) {
            overlayElement.classList.remove('active');
        }
        document.body.style.overflow = '';
    }
}

/**
 * Inicializar funcionalidades generales
 */
function initGeneralFeatures() {
    // Mejorar accesibilidad de tablas
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        // Agregar scroll horizontal en móvil
        if (table.parentElement) {
            table.parentElement.style.overflowX = 'auto';
        }
        
        // Agregar atributos de accesibilidad
        const headers = table.querySelectorAll('th');
        const rows = table.querySelectorAll('tbody tr');
        
        headers.forEach((header, index) => {
            header.setAttribute('scope', 'col');
        });
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, index) => {
                if (headers[index]) {
                    cell.setAttribute('data-label', headers[index].textContent.trim());
                }
            });
        });
    });
    
    // Mejorar formularios
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        // Agregar validación visual
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(this);
            });
        });
    });
    
    // Mejorar botones
    const buttons = document.querySelectorAll('button, .btn, .filtro-btn');
    buttons.forEach(button => {
        // Agregar efecto de carga
        button.addEventListener('click', function() {
            if (this.type === 'submit' && !this.classList.contains('loading')) {
                this.classList.add('loading');
                this.innerHTML = '<span class="spinner"></span> Procesando...';
                
                // Remover clase después de un tiempo (para formularios que no se envían)
                setTimeout(() => {
                    this.classList.remove('loading');
                    this.innerHTML = this.getAttribute('data-original-text') || this.innerHTML;
                }, 3000);
            }
        });
        
        // Guardar texto original
        if (button.type === 'submit') {
            button.setAttribute('data-original-text', button.innerHTML);
        }
  });
}

/**
 * Inicializar formularios responsivos
 */
function initResponsiveForms() {
    // Manejar formularios de filtros
    const filterForms = document.querySelectorAll('.filtros');
    filterForms.forEach(form => {
        // Agregar funcionalidad de filtros móviles
        if (window.innerWidth <= 768) {
            const filterGroups = form.querySelectorAll('.filtro-grupo');
            filterGroups.forEach(group => {
                const label = group.querySelector('.filtro-label');
                const input = group.querySelector('input, select');
                
                if (label && input) {
                    // Hacer que el label sea clickeable
                    label.style.cursor = 'pointer';
                    label.addEventListener('click', function() {
                        input.focus();
                    });
                }
            });
        }
    });
    
    // Mejorar campos de fecha en móvil
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        // Establecer fecha actual por defecto si está vacío
        if (!input.value && input.required) {
            const today = new Date().toISOString().split('T')[0];
            input.value = today;
        }
    });
}

/**
 * Validar campo de formulario
 */
function validateField(field) {
    const value = field.value.trim();
    const isRequired = field.hasAttribute('required');
    const type = field.type;
    
    clearFieldError(field);
    
    if (isRequired && !value) {
        showFieldError(field, 'Este campo es obligatorio');
        return false;
    }
    
    if (value) {
        switch (type) {
            case 'email':
                if (!isValidEmail(value)) {
                    showFieldError(field, 'Ingrese un email válido');
                    return false;
                }
                break;
            case 'number':
                if (field.min && parseFloat(value) < parseFloat(field.min)) {
                    showFieldError(field, `El valor mínimo es ${field.min}`);
                    return false;
                }
                if (field.max && parseFloat(value) > parseFloat(field.max)) {
                    showFieldError(field, `El valor máximo es ${field.max}`);
                    return false;
                }
                break;
        }
    }
    
    return true;
}

/**
 * Mostrar error en campo
 */
function showFieldError(field, message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.marginTop = '5px';
    
    field.parentNode.appendChild(errorDiv);
    field.style.borderColor = '#dc3545';
}

/**
 * Limpiar error de campo
 */
function clearFieldError(field) {
    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
    field.style.borderColor = '#ddd';
}

/**
 * Validar email
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Función para mostrar notificaciones
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Estilos de la notificación
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.padding = '12px 20px';
    notification.style.borderRadius = '4px';
    notification.style.color = 'white';
    notification.style.fontWeight = '500';
    notification.style.zIndex = '10000';
    notification.style.transform = 'translateX(100%)';
    notification.style.transition = 'transform 0.3s ease';
    
    // Colores según tipo
    switch (type) {
        case 'success':
            notification.style.backgroundColor = '#28a745';
            break;
        case 'error':
            notification.style.backgroundColor = '#dc3545';
            break;
        case 'warning':
            notification.style.backgroundColor = '#ffc107';
            notification.style.color = '#212529';
            break;
        default:
            notification.style.backgroundColor = '#17a2b8';
    }
    
    document.body.appendChild(notification);
    
    // Animar entrada
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Remover después de 5 segundos
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 5000);
}

/**
 * Función para confirmar acciones
 */
function confirmAction(message, callback) {
    if (window.innerWidth <= 768) {
        // En móvil, usar confirm nativo
        if (confirm(message)) {
            callback();
        }
      } else {
        // En desktop, crear modal personalizado
        const modal = document.createElement('div');
        modal.className = 'confirm-modal';
        modal.innerHTML = `
            <div class="confirm-content">
                <h3>Confirmar acción</h3>
                <p>${message}</p>
                <div class="confirm-buttons">
                    <button class="btn btn-secondary" onclick="this.closest('.confirm-modal').remove()">Cancelar</button>
                    <button class="btn btn-danger" onclick="this.closest('.confirm-modal').remove(); ${callback.toString()}()">Confirmar</button>
                </div>
            </div>
        `;
        
        // Estilos del modal
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.width = '100%';
        modal.style.height = '100%';
        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        modal.style.display = 'flex';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        modal.style.zIndex = '10000';
        
        const content = modal.querySelector('.confirm-content');
        content.style.backgroundColor = 'white';
        content.style.padding = '20px';
        content.style.borderRadius = '8px';
        content.style.maxWidth = '400px';
        content.style.width = '90%';
        
        const buttons = modal.querySelector('.confirm-buttons');
        buttons.style.display = 'flex';
        buttons.style.gap = '10px';
        buttons.style.justifyContent = 'flex-end';
        buttons.style.marginTop = '20px';
        
        document.body.appendChild(modal);
  }
}

/**
 * Función para manejar scroll suave
 */
function smoothScrollTo(element) {
    element.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
}

/**
 * Función para detectar si es dispositivo móvil
 */
function isMobile() {
    return window.innerWidth <= 768;
}

/**
 * Función para detectar si es tablet
 */
function isTablet() {
    return window.innerWidth > 768 && window.innerWidth <= 1024;
}

/**
 * Función para detectar si es desktop
 */
function isDesktop() {
    return window.innerWidth > 1024;
}

// Exportar funciones para uso global
window.showNotification = showNotification;
window.confirmAction = confirmAction;
window.smoothScrollTo = smoothScrollTo;
window.isMobile = isMobile;
window.isTablet = isTablet;
window.isDesktop = isDesktop;
