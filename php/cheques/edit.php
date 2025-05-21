<?php
require_once '../config.php';
require_once '../database.php';

// Include header
require_once '../includes/header.php';

// Check if user has permission
if (!hasRole('admin') && !hasRole('user')) {
    redirectWith('list.php', 'No tiene permisos para editar cheques', 'danger');
}

$db = new Database();
$conn = $db->getConnection();

$cheque_id = $_GET['id'] ?? 0;

try {
    // Fetch cheque details
    $stmt = $conn->prepare("
        SELECT c.*, b.name as bank_name, i.invoice_number, i.balance as invoice_balance
        FROM cheques c 
        LEFT JOIN banks b ON c.bank_id = b.id
        LEFT JOIN invoices i ON c.invoice_id = i.id
        WHERE c.id = ?
    ");
    $stmt->execute([$cheque_id]);
    $cheque = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cheque) {
        redirectWith('list.php', 'Cheque no encontrado', 'danger');
    }

    // Only admin can edit non-created cheques
    if (!hasRole('admin') && $cheque['status'] !== 'creado') {
        redirectWith('list.php', 'No puede editar un cheque en este estado', 'danger');
    }

    // Fetch banks for dropdown
    $stmt = $conn->query("SELECT id, name FROM banks ORDER BY name");
    $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch invoices for dropdown
    $stmt = $conn->query("SELECT id, invoice_number, balance FROM invoices WHERE balance > 0 OR id = " . 
        ($cheque['invoice_id'] ?? 0) . " ORDER BY invoice_number");
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    redirectWith('list.php', 'Error al cargar los datos: ' . $e->getMessage(), 'danger');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        $new_status = $_POST['status'] ?? $cheque['status'];
        $old_status = $cheque['status'];

        // Update cheque
        $stmt = $conn->prepare("
            UPDATE cheques SET 
                cheque_number = ?, beneficiary = ?, amount = ?, 
                due_date = ?, details = ?, bank_id = ?, 
                invoice_id = ?, status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $_POST['cheque_number'],
            $_POST['beneficiary'],
            $_POST['amount'],
            $_POST['due_date'],
            $_POST['details'],
            $_POST['bank_id'],
            $_POST['invoice_id'] ?: null,
            $new_status,
            $cheque_id
        ]);

        // Record status change in history
        if ($new_status !== $old_status) {
            $stmt = $conn->prepare("
                INSERT INTO cheque_history (
                    cheque_id, previous_status, new_status, changed_by
                ) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$cheque_id, $old_status, $new_status, $_SESSION['user_id']]);

            // If status changed to 'depositado', update invoice balance
            if ($new_status === 'depositado' && $_POST['invoice_id']) {
                $stmt = $conn->prepare("
                    UPDATE invoices 
                    SET balance = balance - ? 
                    WHERE id = ?
                ");
                $stmt->execute([$_POST['amount'], $_POST['invoice_id']]);
            }
        }

        $conn->commit();
        redirectWith('list.php', 'Cheque actualizado exitosamente', 'success');
    } catch(PDOException $e) {
        $conn->rollBack();
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Error al actualizar el cheque: ' . $e->getMessage()
        ];
    }
}
?>

<div class="row mb-4">
    <div class="col">
        <h2>Editar Cheque</h2>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cheque_number" class="form-label">Número de Cheque *</label>
                    <input type="text" class="form-control" id="cheque_number" name="cheque_number" 
                           value="<?php echo htmlspecialchars($cheque['cheque_number']); ?>" required>
                    <div class="invalid-feedback">
                        Por favor ingrese el número de cheque
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="beneficiary" class="form-label">Beneficiario *</label>
                    <input type="text" class="form-control" id="beneficiary" name="beneficiary" 
                           value="<?php echo htmlspecialchars($cheque['beneficiary']); ?>" required>
                    <div class="invalid-feedback">
                        Por favor ingrese el beneficiario
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="amount" class="form-label">Monto *</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               value="<?php echo $cheque['amount']; ?>" step="0.01" required>
                    </div>
                    <div class="invalid-feedback">
                        Por favor ingrese el monto
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="due_date" class="form-label">Fecha de Vencimiento *</label>
                    <input type="date" class="form-control" id="due_date" name="due_date" 
                           value="<?php echo date('Y-m-d', strtotime($cheque['due_date'])); ?>" required>
                    <div class="invalid-feedback">
                        Por favor seleccione la fecha de vencimiento
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="bank_id" class="form-label">Banco *</label>
                    <select class="form-select" id="bank_id" name="bank_id" required>
                        <option value="">Seleccione un banco</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?php echo $bank['id']; ?>" 
                                    <?php echo $bank['id'] == $cheque['bank_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($bank['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">
                        Por favor seleccione un banco
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="invoice_id" class="form-label">Factura Asociada</label>
                    <select class="form-select" id="invoice_id" name="invoice_id">
                        <option value="">Seleccione una factura</option>
                        <?php foreach ($invoices as $invoice): ?>
                            <option value="<?php echo $invoice['id']; ?>" 
                                    <?php echo $invoice['id'] == $cheque['invoice_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($invoice['invoice_number']) . 
                                    ' (Saldo: $' . number_format($invoice['balance'], 2) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="status" class="form-label">Estado *</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="creado" <?php echo $cheque['status'] === 'creado' ? 'selected' : ''; ?>>
                            Creado
                        </option>
                        <option value="depositado" <?php echo $cheque['status'] === 'depositado' ? 'selected' : ''; ?>>
                            Depositado
                        </option>
                        <option value="devuelto" <?php echo $cheque['status'] === 'devuelto' ? 'selected' : ''; ?>>
                            Devuelto
                        </option>
                        <option value="anulado" <?php echo $cheque['status'] === 'anulado' ? 'selected' : ''; ?>>
                            Anulado
                        </option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="details" class="form-label">Detalles</label>
                <textarea class="form-control" id="details" name="details" rows="3"><?php 
                    echo htmlspecialchars($cheque['details']); 
                ?></textarea>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="list.php" class="btn btn-secondary me-md-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
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
</script>

<?php require_once '../includes/footer.php'; ?>
