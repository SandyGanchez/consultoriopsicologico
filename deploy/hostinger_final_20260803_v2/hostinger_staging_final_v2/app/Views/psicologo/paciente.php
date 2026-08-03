<?php include_once '../app/views/layouts/header.php'; ?>
<div class="d-flex">
    <?php include_once '../app/views/layouts/sidebar_psicologo.php'; ?>
    <div class="container-fluid p-4 bg-white" style="min-height: 100vh;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold text-custom-dark">Catálogo de Pacientes</h3>
                <small class="text-muted">Portal > Pacientes</small>
            </div>
            <button class="btn btn-dark-green rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalRegistro">
                <i class="bi bi-plus-lg me-1"></i> Registrar Paciente Nuevo
            </button>
        </div>

        <!-- Filtros y búsqueda -->
        <div class="card card-custom p-3 mb-4">
            <div class="d-flex gap-3 align-items-center">
                <div class="input-group flex-grow-1 bg-light rounded-pill border-0">
                    <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control bg-transparent border-0" placeholder="Buscar paciente por nombre, correo o ID...">
                </div>
                <select class="form-select bg-light rounded-pill border-0" style="width: 200px;">
                    <option>Todos</option>
                    <option>Activos</option>
                    <option>Inactivos</option>
                </select>
            </div>
        </div>

        <!-- Lista de pacientes -->
        <div class="card card-custom">
            <div class="card-body p-0">
                <?php if(isset($data['pacientes']) && count($data['pacientes']) > 0): ?>
                    <?php foreach($data['pacientes'] as $index => $p): ?>
                    <div class="d-flex align-items-center p-3 border-bottom">
                        <div class="avatar-circle <?php 
                            if($index % 4 == 0) echo 'bg-blue-avatar';
                            elseif($index % 4 == 1) echo 'bg-peach-avatar';
                            elseif($index % 4 == 2) echo 'bg-salm-avatar';
                            else echo 'bg-dark text-white';
                        ?> me-3">
                            <?php echo strtoupper(substr($p['nombrePer'], 0, 2)); ?>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-bold"><?php echo $p['nombrePer'] . ' ' . $p['apPatPer']; ?></h6>
                            <small class="text-muted">ID: #<?php echo substr($p['clvPac'], -6); ?></small>
                        </div>
                        <div class="flex-grow-1"><small class="text-muted"><?php echo $p['correoUsu']; ?></small></div>
                        <div class="flex-grow-1">
                            <?php 
                            $estadoClase = $p['estadoActivoPac'] ? 'status-active' : 'status-inactive';
                            $estadoTexto = $p['estadoActivoPac'] ? 'Active' : 'Inactive';
                            ?>
                            <span class="badge-status <?php echo $estadoClase; ?>"><?php echo $estadoTexto; ?></span>
                        </div>
                        <div>
                            <a href="<?php echo BASE_URL; ?>psicologo/expediente/<?php echo $p['clvPac']; ?>" class="btn btn-sm bg-blue-avatar text-white rounded-pill px-3" style="background-color: var(--color-blue);">
                                <i class="bi bi-eye me-1"></i> Ver expediente
                            </a>
                            <button class="btn btn-sm btn-light rounded-circle ms-2 border"><i class="bi bi-pencil"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-muted p-4">Aún no hay pacientes registrados en el sistema.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Registro (Igual que antes) -->
<div class="modal fade" id="modalRegistro" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content card-custom">
        <div class="modal-header"><h5 class="fw-bold">Registrar Paciente</h5></div>
        <div class="modal-body">
            <form action="<?php echo BASE_URL; ?>psicologo/registrarPacientePost" method="POST">
                <input type="text" name="nombre" placeholder="Nombre" class="form-control mb-2" required>
                <input type="text" name="apPat" placeholder="Apellido Paterno" class="form-control mb-2" required>
                <input type="text" name="apMat" placeholder="Apellido Materno" class="form-control mb-2" required>
                <input type="number" name="edad" placeholder="Edad" class="form-control mb-2" required>
                <input type="email" name="correo" placeholder="Correo" class="form-control mb-2" required>
                <select name="genero" class="form-select mb-2" required>
                    <option value="Masculino">Masculino</option>
                    <option value="Femenino">Femenino</option>
                </select>
                <button type="submit" class="btn btn-dark-green w-100">Guardar</button>
            </form>
        </div>
    </div></div>
</div>
</body></html>