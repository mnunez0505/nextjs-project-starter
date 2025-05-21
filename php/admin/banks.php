<?php
require_once '../config.php';
require_once '../database.php';

// Include header
require_once '../includes/header.php';

// Check if user is admin
if (!hasRole('admin')) {
    redirectWith('../index.php', 'Acceso denegado', 'danger');
}

$db = new Database();
$conn = $db->getConnection();

// Handle bank actions (create/update/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            // Create new bank
            $stmt = $conn->prepare("INSERT INTO banks (name) VALUES (?)");
            $stmt->execute([$_POST['name']]);
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Banco creado exitosamente'
            ];
        } 
        elseif ($action === 'update' && !empty($_POST['bank_id'])) {
            // Update bank
            $stmt = $conn->prepare("UPDATE banks SET name = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['bank_id']]);
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Banco actualizado exitosamente'
            ];
        }
        elseif ($action === 'delete' && !empty($_POST['bank_id'])) {
            // Check if bank has associated cheques
            $stmt = $conn->prepare("SELECT COUNT(*) FROM cheques WHERE bank_id = ?");
            $stmt->execute([$_POST['bank_id']]);
            $cheque_count = $stmt->fetchColumn();
            
            if ($cheque_count > 0) {
                throw new Exception('No se puede eliminar el banco porque tiene cheques asociados');
            }
            
            // Delete bank
            $stmt = $conn->prepare("DELETE FROM banks WHERE id = ?");
            $stmt->execute([$_POST['bank_id']]);
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Banco eliminado exitosamente'
            ];
        }
    } catch(PDOException $e) {
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Error en la operación: ' . $e->getMessage()
        ];
    } catch(Exception $e) {
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => $e->getMessage()
        ];
    }
}

// Fetch banks with cheque counts
try {
    $stmt = $conn->query("
        SELECT b.*, 
               (SELECT COUNT(*) FROM cheques WHERE bank_id = b.id) as cheques_count
        FROM banks b 
        ORDER BY b.name
    ");
    $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Error al cargar bancos: ' . $e->getMessage()
    ];
    $banks = [];
}
?>

<div class="row mb-4">
    <div class="col">
        <h2>Gestión de Bancos</h2>
    </div>
    <div class="col text-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBankModal">
            <i class="bi bi-plus-circle"></i> Nuevo Banco
        </button>
    </div>
</div>

<!-- Banks Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nombre del Banco</th>
                        <th>Cheques Asociados</th>
                        <th>Fecha Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banks as $bank): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($bank['name']); ?></td>
                        <td><?php echo $bank['cheques_count']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($bank['created_at'])); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning" 
                                    data-bs-toggle="modal" data-bs-target="#editBankModal"
                                    data-bank-id="<?php echo $bank['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($bank['name']); ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($bank['cheques_count'] == 0): ?>
                            <button type="button" class="btn btn-sm btn-danger" 
                                    data-bs-toggle="modal" data-bs-target="#deleteBankModal"
                                    data-bank-id="<?php echo $bank['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($bank['name']); ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($banks)): ?>
                    <tr>
                        <td colspan="4" class="text-center">No hay bancos registrados</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Bank Modal -->
<div class="modal fade" id="createBankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Banco</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre del Banco *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="invalid-feedback">
                            Por favor ingrese el nombre del banco
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Banco</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bank Modal -->
<div class="modal fade" id="editBankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="bank_id" id="edit_bank_id">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Banco</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Nombre del Banco *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                        <div class="invalid-feedback">
                            Por favor ingrese el nombre del banco
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Bank Modal -->
<div class="modal fade" id="deleteBankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="bank_id" id="delete_bank_id">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar el banco <strong id="delete_bank_name"></strong>?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar Banco</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()

// Handle edit bank modal
document.getElementById('editBankModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget
    const bankId = button.getAttribute('data-bank-id')
    const name = button.getAttribute('data-name')
    
    this.querySelector('#edit_bank_id').value = bankId
    this.querySelector('#edit_name').value = name
})

// Handle delete bank modal
document.getElementById('deleteBankModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget
    const bankId = button.getAttribute('data-bank-id')
    const name = button.getAttribute('data-name')
    
    this.querySelector('#delete_bank_id').value = bankId
    this.querySelector('#delete_bank_name').textContent = name
})
</script>

<?php require_once '../includes/footer.php'; ?>
