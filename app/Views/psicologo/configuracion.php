<?php include_once '../app/views/layouts/header.php'; ?>
<div class="d-flex">
    <?php include_once '../app/views/layouts/sidebar_psicologo.php'; ?>
    <div class="container-fluid p-4 bg-white" style="min-height: 100vh;">
        <div class="mb-4">
            <h3 class="fw-bold text-custom-dark">Configuración del Consultorio</h3>
            <small class="text-muted">Personalice la información pública, horarios de atención y políticas de su clínica.</small>
        </div>

        <form action="<?php echo BASE_URL; ?>psicologo/guardarConfigPost" method="POST">
            <div class="row g-4">
                <!-- Columna Izquierda -->
                <div class="col-md-7">
                    <div class="card card-custom p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i> Información General</h5>
                        <label class="small fw-bold text-muted mb-1">Nombre del Consultorio *</label>
                        <input type="text" name="nombreCons" value="<?php echo isset($data['nombreCons']) ? $data['nombreCons'] : 'Consultorio Psicológico Integral'; ?>" class="form-control border-0 bg-light mb-3">
                        
                        <label class="small fw-bold text-muted mb-1">Logotipo del Consultorio</label>
                        <div class="border border-2 border-dashed rounded p-4 text-center bg-light mb-3">
                            <i class="bi bi-image text-muted fs-3"></i>
                            <p class="text-muted small mb-0">Arrastra y suelta la imagen aquí<br>Formatos soportados: PNG, JPG (Máx. 5MB)</p>
                            <button class="btn btn-sm btn-outline-secondary mt-2 rounded-pill">Seleccionar Archivo</button>
                        </div>

                        <label class="small fw-bold text-muted mb-1">Dirección Completa</label>
                        <div class="row g-2">
                            <div class="col-8"><input type="text" placeholder="Calle, Ej. Av. Reforma" class="form-control border-0 bg-light"></div>
                            <div class="col-4"><input type="text" placeholder="Número" class="form-control border-0 bg-light"></div>
                            <div class="col-12"><input type="text" placeholder="Colonia" class="form-control border-0 bg-light"></div>
                            <div class="col-6"><input type="text" placeholder="Municipio" class="form-control border-0 bg-light"></div>
                            <div class="col-6"><input type="text" placeholder="Código Postal" class="form-control border-0 bg-light"></div>
                            <div class="col-12"><input type="text" placeholder="Teléfono de Contacto" class="form-control border-0 bg-light"></div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="col-md-5">
                    <!-- Horario -->
                    <div class="card card-custom p-4 mb-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-clock me-2"></i> Horario de Atención</h5>
                        <label class="small fw-bold text-muted mb-2">Días Laborables</label>
                        <div class="mb-3">
                            <?php 
                            $diasGuardados = isset($data['diasLaboralesCons']) ? explode(',', $data['diasLaboralesCons']) : [];
                            $diasSemana = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
                            foreach($diasSemana as $d) {
                                $checked = in_array($d, $diasGuardados) ? 'checked' : '';
                                echo "<div class='form-check form-check-inline'>";
                                echo "<input class='form-check-input' type='checkbox' name='dias[]' value='$d' $checked>";
                                echo "<label class='form-check-label small'>$d</label></div>";
                            }
                            ?>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="small fw-bold text-muted">Hora de Inicio</label>
                                <select name="hraInicioCons" class="form-select border-0 bg-light">
                                    <option value="08:00:00" <?php echo (isset($data['hraInicioCons']) && $data['hraInicioCons']=='08:00:00')?'selected':''; ?>>08:00 AM</option>
                                    <option value="09:00:00" <?php echo (isset($data['hraInicioCons']) && $data['hraInicioCons']=='09:00:00')?'selected':''; ?>>09:00 AM</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted">Hora de Fin</label>
                                <select name="hraFinCons" class="form-select border-0 bg-light">
                                    <option value="18:00:00" <?php echo (isset($data['hraFinCons']) && $data['hraFinCons']=='18:00:00')?'selected':''; ?>>06:00 PM</option>
                                    <option value="20:00:00" <?php echo (isset($data['hraFinCons']) && $data['hraFinCons']=='20:00:00')?'selected':''; ?>>08:00 PM</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Políticas -->
                    <div class="card card-custom p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-shield-check me-2"></i> Políticas de Cancelación</h5>
                        <label class="small fw-bold text-muted mb-2">Tiempo máximo de cancelación sin cargo</label>
                        <div class="input-group mb-2">
                            <input type="number" name="limiteCancHoras" class="form-control border-0 bg-light" value="<?php echo isset($data['limiteCancHoras']) ? $data['limiteCancHoras'] : 24; ?>" style="max-width: 70px;">
                            <span class="input-group-text bg-light border-0 fw-bold">Horas</span>
                        </div>
                        <small class="text-muted d-block mb-3" style="font-size: 11px;">Se notificará automáticamente al paciente cuando intente cancelar fuera de este rango.</small>
                        
                        <button type="submit" class="btn btn-dark-green w-100 py-2 rounded-pill">
                            <i class="bi bi-floppy me-2"></i> Actualizar Configuración
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
</body></html>