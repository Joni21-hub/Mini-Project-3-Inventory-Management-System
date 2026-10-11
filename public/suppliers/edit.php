<?php
$pageTitle = 'Edit Supplier';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../helpers/flash.php';

require_role('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'ID Supplier tidak valid.');
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id");
$stmt->execute(['id' => $id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    set_flash('danger', 'Supplier tidak ditemukan.');
    header("Location: index.php");
    exit;
}

$errors = [];
$name        = $supplier['name'];
$contactName = $supplier['contact_name'];
$phone       = $supplier['phone'];
$email       = $supplier['email'];
$address     = $supplier['address'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors[] = 'Sesi form telah kedaluwarsa. Silakan refresh halaman dan coba lagi.';
    } else {
        $name        = trim($_POST['name'] ?? '');
        $contactName = trim($_POST['contact_name'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $address     = trim($_POST['address'] ?? '');

        validate_string_required($name, 'Nama Supplier', 150, $errors);

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format alamat email tidak valid.';
        }

        if (empty($errors)) {
            $update = $pdo->prepare("
                UPDATE suppliers 
                SET name = :name, contact_name = :contact_name, phone = :phone, email = :email, address = :address
                WHERE id = :id
            ");
            $update->execute([
                'name'         => $name,
                'contact_name' => $contactName ?: null,
                'phone'        => $phone ?: null,
                'email'        => $email ?: null,
                'address'      => $address ?: null,
                'id'           => $id,
            ]);

            set_flash('success', "Data supplier '{$name}' berhasil diperbarui.");
            header("Location: view.php?id=" . $id);
            exit;
        }
    }
}

require_once __DIR__ . '/../../views/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center">
            <a href="index.php" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <div>
                <h3 class="fw-bold text-dark mb-0">Edit Supplier: <?= e($supplier['name']) ?></h3>
                <small class="text-muted">Perbarui data informasi kontak dan alamat mitra</small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm" role="alert">
        <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-circle me-1"></i> Terjadi kesalahan:</h6>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="edit.php?id=<?= $id ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">Nama Perusahaan / Supplier <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($name) ?>" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Nama PIC / Kontak Person</label>
                            <input type="text" name="contact_name" class="form-control" value="<?= e($contactName) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Nomor Telepon</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($phone) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">Email Resmi</label>
                        <input type="email" name="email" class="form-control" value="<?= e($email) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold text-secondary small">Alamat Kantor / Gudang</label>
                        <textarea name="address" rows="3" class="form-control"><?= e($address) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
