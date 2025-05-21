<?php
require_once '../config.php';
require_once '../database.php';

// Include header
require_once '../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$cheque_id = $_GET['id'] ?? 0;

try {
    // Fetch cheque details with related information
    $stmt = $conn->prepare("
        SELECT c.*, 
               b.name as bank_name, 
               i.invoice_number, 
               i.balance as invoice_balance,
               u.username as created_by_name
        FROM cheques c 
        LEFT JOIN banks b ON c.bank_id = b.id
        LEFT JOIN invoices i ON c.invoice_id = i.id
        LEFT JOIN users u ON c.created_by = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$cheque_id]);
    $cheque = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cheque) {
        redirectWith('list.php', 'Cheque no encontrado', 'danger');
    }

    // Fetch cheque history
    $stmt = $conn->prepare("
        SELECT ch.*, u.username as changed_by_name
        FROM cheque_history ch
        LEFT JOIN users u ON ch.changed_by = u.id
        WHERE ch.cheque_id = ?
        ORDER BY ch.changed_at DESC
    ");
    $stmt->execute([$cheque_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    redirectWith('list.php', 'Error al cargar los datos: ' . $e->getMessage(), 'danger');
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    return match($status) {
        'creado' => 'warning',
        'depositado' => 'success',
        'devuelto' => 'danger',
        'anulado' => 'secondary',
        default => 'info'
    };
}
?>

<div class="row mb-4">
    <div class="col">
        <h2>Detalles del Cheque</h2>
    </div>
    <div class="col text-end">
        <?php if (hasRole('admin') || $cheque['status'] === 'creado'): ?>
        <a href="edit.php?id=<?php echo $cheque_id; ?>" class="btn btn-warning">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <?php endif; ?>
        <a href="list.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="row">
    <!-- Cheque Details -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Información del Cheque</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Número de Cheque</label>
                        <p class="form-control-plaintext">
                            <?php echo htmlspecialchars($cheque['cheque_number']); ?>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Beneficiario</label>
                        <p class="form-control-plaintext">
                            <?php echo htmlspecialchars($cheque['beneficiary']); ?>
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Monto</label>
                        <p class="form-control-plaintext">
                            $<?php echo number_format($cheque['amount'], 2); ?>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Fecha de Vencimiento</label>
                        <p class="form-control-plaintext">
                            <?php echo date('d/m/Y', strtotime($cheque['due_date'])); ?>
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Banco</label>
                        <p class="form-control-plaintext">
                            <?php echo htmlspecialchars($cheque['bank_name']); ?>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Estado</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-<?php echo getStatusBadgeClass($cheque['status']); ?>">
                                <?php echo ucfirst($cheque['status']); ?>
                            </span>
                        </p>
                    </div>
                </div>

                <?php if ($cheque['invoice_number']): ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Factura Asociada</label>
                        <p class="form-control-plaintext">
                            <?php echo htmlspecialchars($cheque['invoice_number']); ?>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Saldo de Factura</label>
                        <p class="form-control-plaintext">
                            $<?php echo number_format($cheque['invoice_balance'], 2); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($cheque['details']): ?>
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Detalles</label>
                        <p class="form-control-plaintext">
                            <?php echo nl2br(htmlspecialchars($cheque['details'])); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Creado por</label>
                        <p class="form-control-plaintext">
                            <?php echo htmlspecialchars($cheque['created_by_name']); ?>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Fecha de Creación</label>
                        <p class="form-control-plaintext">
                            <?php echo date('d/m/Y H:i', strtotime($cheque['created_at'])); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- History Timeline -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Historial de Cambios</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($history as $record): ?>
                    <div class="timeline-item mb-4">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="timeline-badge bg-<?php 
                                    echo getStatusBadgeClass($record['new_status']); 
                                ?>">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">
                                    Cambio de Estado: 
                                    <?php if ($record['previous_status']): ?>
                                        <span class="badge bg-<?php 
                                            echo getStatusBadgeClass($record['previous_status']); 
                                        ?>">
                                            <?php echo ucfirst($record['previous_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inicial</span>
                                    <?php endif; ?>
                                    <i class="bi bi-arrow-right mx-2"></i>
                                    <span class="badge bg-<?php 
                                        echo getStatusBadgeClass($record['new_status']); 
                                    ?>">
                                        <?php echo ucfirst($record['new_status']); ?>
                                    </span>
                                </h6>
                                <p class="mb-0 text-muted">
                                    <small>
                                        Por: <?php echo htmlspecialchars($record['changed_by_name']); ?>
                                        <br>
                                        <?php echo date('d/m/Y H:i', strtotime($record['changed_at'])); ?>
                                    </small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($history)): ?>
                    <p class="text-muted">No hay cambios registrados</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 0;
    list-style: none;
}

.timeline-badge {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.timeline-badge i {
    font-size: 1.2rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>
