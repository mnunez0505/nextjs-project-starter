<?php
require_once '../config.php';
require_once '../database.php';

// Include header
require_once '../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

try {
    // Build query conditions
    $conditions = [];
    $params = [];
    
    if ($search) {
        $conditions[] = "(c.cheque_number LIKE ? OR c.beneficiary LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($status_filter) {
        $conditions[] = "c.status = ?";
        $params[] = $status_filter;
    }
    
    if ($date_from) {
        $conditions[] = "c.created_at >= ?";
        $params[] = $date_from . ' 00:00:00';
    }
    
    if ($date_to) {
        $conditions[] = "c.created_at <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    
    $where_clause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Get total records for pagination
    $count_query = "SELECT COUNT(*) FROM cheques c $where_clause";
    $stmt = $conn->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    // Get cheques with pagination
    $query = "
        SELECT c.*, b.name as bank_name, u.username as created_by_name,
               i.invoice_number, i.balance as invoice_balance
        FROM cheques c 
        LEFT JOIN banks b ON c.bank_id = b.id 
        LEFT JOIN users u ON c.created_by = u.id
        LEFT JOIN invoices i ON c.invoice_id = i.id
        $where_clause
        ORDER BY c.created_at DESC 
        LIMIT $records_per_page OFFSET $offset
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $cheques = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Error al cargar los cheques: ' . $e->getMessage()
    ];
}
?>

<div class="row mb-4">
    <div class="col">
        <h2>Listado de Cheques</h2>
    </div>
    <div class="col text-end">
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo Cheque
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" 
                       placeholder="Buscar por número o beneficiario" 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">Todos los estados</option>
                    <option value="creado" <?php echo $status_filter === 'creado' ? 'selected' : ''; ?>>Creado</option>
                    <option value="depositado" <?php echo $status_filter === 'depositado' ? 'selected' : ''; ?>>Depositado</option>
                    <option value="devuelto" <?php echo $status_filter === 'devuelto' ? 'selected' : ''; ?>>Devuelto</option>
                    <option value="anulado" <?php echo $status_filter === 'anulado' ? 'selected' : ''; ?>>Anulado</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from" 
                       value="<?php echo $date_from; ?>" placeholder="Fecha desde">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to" 
                       value="<?php echo $date_to; ?>" placeholder="Fecha hasta">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="list.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Limpiar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Cheques Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Beneficiario</th>
                        <th>Banco</th>
                        <th>Monto</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th>Factura</th>
                        <th>Creado por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cheques as $cheque): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cheque['cheque_number']); ?></td>
                        <td><?php echo htmlspecialchars($cheque['beneficiary']); ?></td>
                        <td><?php echo htmlspecialchars($cheque['bank_name']); ?></td>
                        <td>$<?php echo number_format($cheque['amount'], 2); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($cheque['due_date'])); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo match($cheque['status']) {
                                    'creado' => 'warning',
                                    'depositado' => 'success',
                                    'devuelto' => 'danger',
                                    'anulado' => 'secondary',
                                    default => 'info'
                                };
                            ?>">
                                <?php echo ucfirst($cheque['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($cheque['invoice_number']): ?>
                                <?php echo htmlspecialchars($cheque['invoice_number']); ?>
                                <br>
                                <small class="text-muted">
                                    Saldo: $<?php echo number_format($cheque['invoice_balance'], 2); ?>
                                </small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($cheque['created_by_name']); ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="view.php?id=<?php echo $cheque['id']; ?>" 
                                   class="btn btn-sm btn-info" title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (hasRole('admin') || $cheque['status'] === 'creado'): ?>
                                <a href="edit.php?id=<?php echo $cheque['id']; ?>" 
                                   class="btn btn-sm btn-warning" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cheques)): ?>
                    <tr>
                        <td colspan="9" class="text-center">No se encontraron cheques</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                        Anterior
                    </a>
                </li>
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                        Siguiente
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
