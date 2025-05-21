<?php
require_once '../config.php';
require_once '../database.php';

// Include header
require_once '../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// Filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-t'); // Last day of current month
$bank_id = $_GET['bank_id'] ?? '';
$status = $_GET['status'] ?? '';

try {
    // Get banks for filter
    $stmt = $conn->query("SELECT id, name FROM banks ORDER BY name");
    $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build query conditions
    $conditions = ["c.created_at BETWEEN ? AND ?"];
    $params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
    
    if ($bank_id) {
        $conditions[] = "c.bank_id = ?";
        $params[] = $bank_id;
    }
    
    if ($status) {
        $conditions[] = "c.status = ?";
        $params[] = $status;
    }
    
    $where_clause = implode(' AND ', $conditions);

    // Get cheques with related information
    $query = "
        SELECT 
            c.*,
            b.name as bank_name,
            i.invoice_number,
            i.amount as invoice_amount,
            i.balance as invoice_balance,
            u.username as created_by_name,
            (SELECT COUNT(*) FROM cheque_history WHERE cheque_id = c.id) as changes_count
        FROM cheques c
        LEFT JOIN banks b ON c.bank_id = b.id
        LEFT JOIN invoices i ON c.invoice_id = i.id
        LEFT JOIN users u ON c.created_by = u.id
        WHERE $where_clause
        ORDER BY c.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $cheques = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totals = [
        'total' => 0,
        'depositado' => 0,
        'pendiente' => 0,
        'devuelto' => 0,
        'anulado' => 0
    ];

    foreach ($cheques as $cheque) {
        $totals['total'] += $cheque['amount'];
        if ($cheque['status'] === 'depositado') {
            $totals['depositado'] += $cheque['amount'];
        } elseif ($cheque['status'] === 'creado') {
            $totals['pendiente'] += $cheque['amount'];
        } elseif ($cheque['status'] === 'devuelto') {
            $totals['devuelto'] += $cheque['amount'];
        } elseif ($cheque['status'] === 'anulado') {
            $totals['anulado'] += $cheque['amount'];
        }
    }

} catch(PDOException $e) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Error al generar el reporte: ' . $e->getMessage()
    ];
}
?>

<div class="row mb-4">
    <div class="col">
        <h2>Estado de Cuenta - Cheques</h2>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Fecha Desde</label>
                <input type="date" class="form-control" name="date_from" 
                       value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha Hasta</label>
                <input type="date" class="form-control" name="date_to" 
                       value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Banco</label>
                <select class="form-select" name="bank_id">
                    <option value="">Todos los bancos</option>
                    <?php foreach ($banks as $bank): ?>
                        <option value="<?php echo $bank['id']; ?>" 
                                <?php echo $bank['id'] == $bank_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($bank['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select class="form-select" name="status">
                    <option value="">Todos los estados</option>
                    <option value="creado" <?php echo $status === 'creado' ? 'selected' : ''; ?>>
                        Creado
                    </option>
                    <option value="depositado" <?php echo $status === 'depositado' ? 'selected' : ''; ?>>
                        Depositado
                    </option>
                    <option value="devuelto" <?php echo $status === 'devuelto' ? 'selected' : ''; ?>>
                        Devuelto
                    </option>
                    <option value="anulado" <?php echo $status === 'anulado' ? 'selected' : ''; ?>>
                        Anulado
                    </option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Filtrar
                </button>
                <a href="account_statement.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Limpiar
                </a>
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Total en Cheques</h5>
                <h3>$<?php echo number_format($totals['total'], 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Total Depositado</h5>
                <h3>$<?php echo number_format($totals['depositado'], 2); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h5 class="card-title">Total Pendiente</h5>
                <h3>$<?php echo number_format($totals['pendiente'], 2); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Report -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Número</th>
                        <th>Beneficiario</th>
                        <th>Banco</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Factura</th>
                        <th>Cambios</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cheques as $cheque): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($cheque['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($cheque['cheque_number']); ?></td>
                        <td><?php echo htmlspecialchars($cheque['beneficiary']); ?></td>
                        <td><?php echo htmlspecialchars($cheque['bank_name']); ?></td>
                        <td class="text-end">$<?php echo number_format($cheque['amount'], 2); ?></td>
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
                        <td class="text-center">
                            <?php if ($cheque['changes_count'] > 0): ?>
                                <span class="badge bg-info">
                                    <?php echo $cheque['changes_count']; ?> cambios
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="../cheques/view.php?id=<?php echo $cheque['id']; ?>" 
                               class="btn btn-sm btn-info" title="Ver detalles">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cheques)): ?>
                    <tr>
                        <td colspan="9" class="text-center">No se encontraron cheques en el período seleccionado</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style media="print">
    .btn, .no-print {
        display: none !important;
    }
    .card {
        border: none !important;
    }
    .card-body {
        padding: 0 !important;
    }
    @page {
        margin: 1cm;
    }
</style>

<?php require_once '../includes/footer.php'; ?>
