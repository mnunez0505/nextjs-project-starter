<?php
require_once '../config.php';
require_once '../database.php';

// Include header
require_once '../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// Fetch banks for dropdown
try {
    $stmt = $conn->query("SELECT id, name FROM banks ORDER BY name");
    $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch invoices for dropdown
    $stmt = $conn->query("SELECT id, invoice_number, balance FROM invoices WHERE balance > 0 ORDER BY invoice_number");
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Error al cargar datos: ' . $e->getMessage()
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("
            INSERT INTO cheques (
                cheque_number, beneficiary, amount, due_date, 
                details, bank_id, invoice_id, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $_POST['cheque_number'],
            $_POST['beneficiary'],
            $_POST['amount'],
            $_POST['due_date'],
            $_POST['details'],
            $_POST['bank_id'],
            $_POST['invoice_id'],
            $_SESSION['user_id']
        ]);

        if ($result) {
            // Record in history
            $cheque_id = $conn->lastInsertId();
            $stmt = $conn->prepare("
                INSERT INTO cheque_history (
                    cheque_id, previous_status, new_status, changed_by
                ) VALUES (?, NULL, 'creado', ?)
            ");
            $stmt->execute([$cheque_id, $_SESSION['user_id']]);

            redirectWith('list.php', 'Cheque registrado exitosamente', 'success');
        }
    } catch(PDOException $e) {
        $_SESSION['flash'] = [
            'type' => 'danger',
            'message' => 'Error al registrar el cheque: ' . $e->getMessage()
        ];
    }
}
?>

<div class="row mb-4">
    <div class="col">
        <h2>Registrar Nuevo Cheque</h2>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cheque_number" class="form-label">Número de Cheque *</label>
                    <input type="text" class="form-control" id="cheque_number" name="cheque_number" required>
                    <div class="invalid-feedback">
                        Por favor ingrese el número de cheque
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="beneficiary" class="form-label">Beneficiario *</label>
                    <input type="text" class="form-control" id="beneficiary" name="beneficiary" required>
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
                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                    </div>
                    <div class="invalid-feedback">
                        Por favor ingrese el monto
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="due_date" class="form-label">Fecha de Vencimiento *</label>
                    <input type="date" class="form-control" id="due_date" name="due_date" required>
                    <div class="invalid-feedback">
                        Por favor seleccione la fecha de vencimiento
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="bank_id" class="form-label">Banco *</label>
                    <select class="form-select" id="bank_id" name="bank_id" required>
                        <option value="">Seleccione un banco</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?php echo $bank['id']; ?>">
                                <?php echo htmlspecialchars($bank['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">
                        Por favor seleccione un banco
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="invoice_id" class="form-label">Factura Asociada</label>
                    <select class="form-select" id="invoice_id" name="invoice_id">
                        <option value="">Seleccione una factura</option>
                        <?php foreach ($invoices as $invoice): ?>
                            <option value="<?php echo $invoice['id']; ?>">
                                <?php echo htmlspecialchars($invoice['invoice_number']) . 
                                    ' (Saldo: $' . number_format($invoice['balance'], 2) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="details" class="form-label">Detalles</label>
                <textarea class="form-control" id="details" name="details" rows="3"></textarea>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="list.php" class="btn btn-secondary me-md-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Registrar Cheque</button>
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
