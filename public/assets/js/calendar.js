/**
 * Funcionalidad JavaScript para el calendario de reservas
 */

document.addEventListener("DOMContentLoaded", function () {
  // Elementos del DOM
  const eventElements = document.querySelectorAll(".event");
  const calendarDays = document.querySelectorAll(".calendar td:not(.empty)");
  const filterForm = document.querySelector(".filtros");
  const recursoSelect = document.getElementById("recurso");
  const tipoSelect = document.getElementById("tipo");
  const mesInput = document.querySelector('input[name="mes"]');
  const anioInput = document.querySelector('input[name="anio"]');

  // Configurar eventos de clic para los eventos del calendario
  eventElements.forEach((event) => {
    event.addEventListener("click", function (e) {
      e.stopPropagation(); // Evitar que el clic se propague al día
      const reservaId = this.getAttribute("data-id");
      window.location.href = `ver.php?id=${reservaId}`;
    });

    // Mejor manejo de tooltips para dispositivos móviles
    if ("ontouchstart" in window) {
      let tooltipVisible = false;
      const tooltip = event.nextElementSibling;

      event.addEventListener("touchstart", function (e) {
        e.preventDefault();

        // Ocultar todos los tooltips visibles
        document.querySelectorAll(".event-tooltip").forEach((t) => {
          t.style.display = "none";
        });

        // Mostrar/ocultar el tooltip actual
        if (!tooltipVisible) {
          tooltip.style.display = "block";
          tooltipVisible = true;
        } else {
          tooltip.style.display = "none";
          tooltipVisible = false;
        }
      });
    }
  });

  // Añadir evento de clic a los días para crear reservas
  calendarDays.forEach((day) => {
    day.addEventListener("click", function () {
      // Obtener el número de día
      const dayNumber = this.querySelector(".day-number").textContent;

      // Obtener mes y año actuales del calendario
      const mes = mesInput.value;
      const anio = anioInput.value;

      // Formatear la fecha (YYYY-MM-DD)
      const fechaFormateada = `${anio}-${mes.padStart(
        2,
        "0"
      )}-${dayNumber.padStart(2, "0")}`;

      // Redirigir al formulario de creación con la fecha preseleccionada
      window.location.href = `crear.php?fecha=${fechaFormateada}`;
    });
  });

  // Actualizar filtros al cambiar recurso o tipo
  recursoSelect.addEventListener("change", submitFilterForm);
  tipoSelect.addEventListener("change", submitFilterForm);

  function submitFilterForm() {
    filterForm.submit();
  }

  // Función para detectar la visibilidad de los tooltips
  function positionTooltips() {
    const tooltips = document.querySelectorAll(".event-tooltip");

    tooltips.forEach((tooltip) => {
      // Obtener la posición del evento asociado
      const event = tooltip.previousElementSibling;
      const eventRect = event.getBoundingClientRect();

      // Calcular la posición ideal para el tooltip
      const idealLeft = eventRect.left + eventRect.width + 5;
      const idealTop = eventRect.top - 10;

      // Verificar si el tooltip se sale de la ventana
      const tooltipWidth = 250; // Ancho definido en CSS
      const windowWidth = window.innerWidth;

      // Si el tooltip se sale por la derecha, mostrarlo a la izquierda del evento
      if (idealLeft + tooltipWidth > windowWidth) {
        tooltip.style.left = eventRect.left - tooltipWidth - 5 + "px";
      } else {
        tooltip.style.left = idealLeft + "px";
      }

      tooltip.style.top = idealTop + "px";
    });
  }

  // Posicionar tooltips al pasar el ratón sobre eventos
  eventElements.forEach((event) => {
    event.addEventListener("mouseenter", positionTooltips);
  });

  // Ajustar posición de tooltips al cambiar el tamaño de la ventana
  window.addEventListener("resize", positionTooltips);

  // Implementar funcionalidad de arrastrar para ver más días en móviles
  let touchStartX = 0;
  let touchEndX = 0;

  const calendarContainer = document.querySelector(".calendar");

  calendarContainer.addEventListener(
    "touchstart",
    (e) => {
      touchStartX = e.changedTouches[0].screenX;
    },
    false
  );

  calendarContainer.addEventListener(
    "touchend",
    (e) => {
      touchEndX = e.changedTouches[0].screenX;
      handleSwipe();
    },
    false
  );

  function handleSwipe() {
    const swipeThreshold = 100; // Mínima distancia para considerar un swipe

    if (touchEndX < touchStartX - swipeThreshold) {
      // Swipe izquierda - Mes siguiente
      const nextMonthLink = document.querySelector(
        ".calendar-nav a:last-child"
      );
      if (nextMonthLink) {
        window.location.href = nextMonthLink.href;
      }
    }

    if (touchEndX > touchStartX + swipeThreshold) {
      // Swipe derecha - Mes anterior
      const prevMonthLink = document.querySelector(
        ".calendar-nav a:first-child"
      );
      if (prevMonthLink) {
        window.location.href = prevMonthLink.href;
      }
    }
  }

  // Inicializar vista de calendario
  initCalendarView();

  function initCalendarView() {
    // Resaltar fin de semana
    highlightWeekends();

    // Verificar si hay días con muchos eventos y ajustar la visualización
    adjustEventsDisplay();
  }

  function highlightWeekends() {
    // Obtener todos los sábados y domingos
    const weekends = document.querySelectorAll(
      ".calendar tr td:nth-child(6), .calendar tr td:nth-child(7)"
    );

    // Añadir clase para estilo de fin de semana
    weekends.forEach((day) => {
      if (!day.classList.contains("empty")) {
        day.style.backgroundColor = "#f9f9f9";
      }
    });
  }

  function adjustEventsDisplay() {
    // Verificar las celdas que tienen muchos eventos
    calendarDays.forEach((day) => {
      const events = day.querySelectorAll(".event");

      if (events.length > 4) {
        // Si hay más de 4 eventos, mostrar indicador de "más eventos"
        const container = day.querySelector(".events-container");

        // Mostrar solo los primeros 3 eventos
        for (let i = 3; i < events.length; i++) {
          events[i].style.display = "none";
        }

        // Crear indicador de más eventos
        const moreIndicator = document.createElement("div");
        moreIndicator.className = "event more-events";
        moreIndicator.textContent = `+ ${events.length - 3} más`;

        // Añadir evento de clic para mostrar todos los eventos
        moreIndicator.addEventListener("click", function (e) {
          e.stopPropagation();

          // Mostrar/ocultar todos los eventos
          if (this.classList.contains("showing-all")) {
            // Ocultar eventos después del tercero
            for (let i = 3; i < events.length; i++) {
              events[i].style.display = "none";
            }
            this.textContent = `+ ${events.length - 3} más`;
            this.classList.remove("showing-all");
          } else {
            // Mostrar todos los eventos
            for (let i = 0; i < events.length; i++) {
              events[i].style.display = "block";
            }
            this.textContent = "Mostrar menos";
            this.classList.add("showing-all");
          }
        });

        // Añadir al final del contenedor
        container.appendChild(moreIndicator);
      }
    });
  }
});
