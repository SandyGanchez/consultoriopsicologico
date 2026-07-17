
<div class="d-flex">
   
    <div class="container-fluid p-4 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-custom-dark">Calendario General</h3>
            <div class="d-flex align-items-center">
                <span class="d-flex align-items-center me-3"><span class="bg-primary rounded-circle me-1" style="width:10px;height:10px;"></span> Programado</span>
                <span class="d-flex align-items-center me-3"><span class="bg-success rounded-circle me-1" style="width:10px;height:10px;"></span> Asistida</span>
                <span class="d-flex align-items-center me-3"><span class="bg-danger rounded-circle me-1" style="width:10px;height:10px;"></span> Inasistencia</span>
                <span class="d-flex align-items-center"><span class="bg-secondary rounded-circle me-1" style="width:10px;height:10px;"></span> Cancelada</span>
            </div>
        </div>
        
        <div id='calendar'></div>
        <a href="#" class="btn-floating-add"><i class="bi bi-plus"></i></a>
    </div>
</div>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
<style>

    .fc-theme-standard th { border-color: #e9ecef; }
    .fc-col-header-cell-cushion { color: #333; }
    .fc-event { border: none; border-radius: 8px; padding: 4px 8px; font-size: 12px; }
    .fc-daygrid-event { white-space: normal; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        slotMinTime: '08:00:00',
        slotMaxTime: '20:00:00',
        events: '<?php echo BASE_URL; ?>psicologo/obtenerEventos'
    });
    calendar.render();
});
</script>
</body></html>