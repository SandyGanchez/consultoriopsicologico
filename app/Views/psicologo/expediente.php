
<div class="d-flex">
    
    <div class="container-fluid p-4 bg-white" style="min-height: 100vh;">
     
        <div class="d-flex align-items-center mb-4">
            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                <i class="bi bi-person fs-3 text-muted"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0"><?php echo isset($data['paciente']['nombrePer']) ? $data['paciente']['nombrePer'] . ' ' . $data['paciente']['apPatPer'] : 'Nombre del Paciente'; ?></h4>
                <small class="text-muted">ID: #<?php echo isset($data['clvPac']) ? substr($data['clvPac'], -6) : 'EXP-2024-0812'; ?> | Última visita: Hace 1 semana</small>
            </div>
        </div>

        
        <ul class="nav nav-tabs border-0 mb-4">
            <li class="nav-item"><a class="nav-link text-muted" href="#">Ficha de Identificación</a></li>
            <li class="nav-item"><a class="nav-link text-muted" href="#">Historia Clínica</a></li>
            <li class="nav-item"><a class="nav-link active fw-bold text-dark border-0 border-bottom border-dark" style="border-radius: 0;" href="#">Seguimiento de Sesiones</a></li>
        </ul>

      
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-custom-dark">Gestión de Seguimiento - [Nombre del Paciente]</h5>
            <button class="btn btn-dark-green rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalNota">
                <i class="bi bi-plus-lg me-1"></i> Agregar Nueva Sesión
            </button>
        </div>

      
        <div class="card card-custom">
            <div class="card-body p-0">
                <table class="table mb-0 align-middle">
                    <thead class="bg-light">
                        <tr><th class="ps-4 py-3">FECHA</th><th>MOTIVO DE CONSULTA</th><th>OBSERVACIONES</th><th>DIAGNÓSTICO CLÍNICO</th><th class="text-end pe-4">ACCIONES</th></tr>
                    </thead>
                    <tbody>
                        <?php if(isset($data['notas']) && count($data['notas']) > 0): ?>
                            <?php foreach($data['notas'] as $n): ?>
                            <tr>
                                <td class="ps-4 py-3 text-muted small"><?php echo $n['fechaRegHist']; ?></td>
                                <td class="fw-bold"><?php echo $n['motivoHist']; ?></td>
                                <td class="text-muted small">"<?php echo $n['observacionesHist']; ?>"</td>
                                <td><span class="badge-status status-active"><?php echo $n['diagnosticoHist']; ?></span></td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-light border rounded-circle me-2"><i class="bi bi-eye"></i></button>
                                    <button class="btn btn-sm btn-light border rounded-circle"><i class="bi bi-pencil"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No hay sesiones registradas para este paciente.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="modalNota" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content card-custom">
        <div class="modal-header"><h5 class="fw-bold">Agregar Nueva Sesión</h5></div>
        <div class="modal-body">
            <form action="<?php echo BASE_URL; ?>psicologo/agregarNotaPost" method="POST">
                <input type="hidden" name="clvPac" value="<?php echo isset($data['clvPac']) ? $data['clvPac'] : ''; ?>">
                <input type="text" name="motivo" placeholder="Motivo de consulta" class="form-control mb-2" required>
                <textarea name="observaciones" class="form-control mb-2" placeholder="Observaciones clínicas del paciente" rows="3"></textarea>
                <input type="text" name="diagnostico" placeholder="Diagnóstico Clínico" class="form-control mb-2" required>
                <button type="submit" class="btn btn-dark-green w-100">Guardar Nota</button>
            </form>
        </div>
    </div></div>
</div>
</body></html>