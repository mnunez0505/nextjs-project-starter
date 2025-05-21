<?php
require_once 'config.php';
require_once 'database.php';

// Include header
require_once 'includes/header.php';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Get cheque statistics
$stats = [
    'total' => 0,
    'creado' => 0,
    'depositado' => 0,
    'devuelto' => 0,
    'anulado' => 0
];

try {
    // Get total counts by status
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM cheques GROUP BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = $row['count'];
        $stats['total'] += $row['count'];
    }

    // Get recent cheques
    $stmt = $conn->query("
        SELECT c.*, b.name as bank_name, u.username as created_by_name 
        FROM cheques c 
        LEFT JOIN banks b ON c.bank_id = b.id 
        LEFT JOIN users u ON c.created_by = u.id 
        ORDER BY c.created_at DESC 
        LIMIT 5
    ");
    $recent_cheques = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Error al cargar los datos: ' . $e->getMessage()
    ];
}
?>

<div class="row mb-4">
    <div class="col">
        <h2>Panel de Control</h2>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Total Cheques</h5>
                <h2 class="display-4"><?php echo $stats['total']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="row">
            <div class="col-sm-6 mb-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Depositados</h5>
                        <h3><?php echo $stats['depositado']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 mb-3">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h5 class="card-title">Pendientes</h5>
                        <h3><?php echo $stats['creado']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 mb-3">
                <div class="card bg-danger text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Devueltos</h5>
                        <h3><?php echo $stats['devuelto']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 mb-3">
                <div class="card bg-secondary text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Anulados</h5>
                        <h3><?php echo $stats['anulado']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Cheques Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Cheques Recientes</h5>
        <a href="cheques/list.php" class="btn btn-primary btn-sm">Ver Todos</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Beneficiario</th>
                        <th>Banco</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Creado por</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_cheques as $cheque): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cheque['cheque_number']); ?></td>
                        <td><?php echo htmlspecialchars($cheque['beneficiary']); ?></td>
                        <td><?php echo htmlspecialchars($cheque['bank_name']); ?></td>
                        <td>$<?php echo number_format($cheque['amount'], 2); ?></td>
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
                        <td><?php echo htmlspecialchars($cheque['created_by_name']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($cheque['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recent_cheques)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No hay cheques registrados</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
