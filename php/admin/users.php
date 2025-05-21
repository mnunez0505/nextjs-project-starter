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

// Handle user actions (create/update/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            // Create new user
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (username, password, role) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $_POST['username'],
                $password_hash,
                $_POST['role']
            ]);
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Usuario creado exitosamente'
            ];
        } 
        elseif ($action === 'update' && !empty($_POST['user_id'])) {
            // Update user
            if (!empty($_POST['password'])) {
                // Update with new password
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET username = ?, password = ?, role = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['username'],
                    $password_hash,
                    $_POST['role'],
                    $_POST['user_id']
                ]);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET username = ?, role = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['username'],
                    $_POST['role'],
                    $_POST['user_id']
                ]);
            }
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Usuario actualizado exitosamente'
            ];
        }
        elseif ($action === 'delete' && !empty($_POST['user_id'])) {
            // Prevent deleting self
            if ($_POST['user_id'] == $_SESSION['user_id']) {
                throw new Exception('No puede eliminar su propio usuario');
            }
            
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_POST['user_id']]);
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Usuario eliminado exitosamente'
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

// Fetch users
try {
    $stmt = $conn->query("
        SELECT id, username, role, created_at,
        (SELECT COUNT(*) FROM cheques WHERE created_by = users.id) as cheques_count
        FROM users 
        ORDER BY username
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Error al cargar usuarios: ' . $e->getMessage()
    ];
    $users = [];
}
?>

<div class="row mb-4">
    <div class="col">
        <h2>Gestión de Usuarios</h2>
    </div>
    <div class="col text-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="bi bi-person-plus"></i> Nuevo Usuario
        </button>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Cheques Creados</th>
                        <th>Fecha Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td><?php echo $user['cheques_count']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning" 
                                    data-bs-toggle="modal" data-bs-target="#editUserModal"
                                    data-user-id="<?php echo $user['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                    data-role="<?php echo $user['role']; ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button type="button" class="btn btn-sm btn-danger" 
                                    data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                    data-user-id="<?php echo $user['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                        <div class="invalid-feedback">
                            Por favor ingrese un nombre de usuario
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="invalid-feedback">
                            Por favor ingrese una contraseña
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Rol *</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="user">Usuario</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Usuario *</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                        <div class="invalid-feedback">
                            Por favor ingrese un nombre de usuario
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" id="edit_password" name="password">
                        <small class="form-text text-muted">
                            Dejar en blanco para mantener la contraseña actual
                        </small>
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Rol *</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="user">Usuario</option>
                            <option value="admin">Administrador</option>
                        </select>
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

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar al usuario <strong id="delete_username"></strong>?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar Usuario</button>
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

// Handle edit user modal
document.getElementById('editUserModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget
    const userId = button.getAttribute('data-user-id')
    const username = button.getAttribute('data-username')
    const role = button.getAttribute('data-role')
    
    this.querySelector('#edit_user_id').value = userId
    this.querySelector('#edit_username').value = username
    this.querySelector('#edit_role').value = role
})

// Handle delete user modal
document.getElementById('deleteUserModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget
    const userId = button.getAttribute('data-user-id')
    const username = button.getAttribute('data-username')
    
    this.querySelector('#delete_user_id').value = userId
    this.querySelector('#delete_username').textContent = username
})
</script>

<?php require_once '../includes/footer.php'; ?>
