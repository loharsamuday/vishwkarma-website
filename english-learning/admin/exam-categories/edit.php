<?php
// admin/exam-categories/edit.php
session_start();
require_once '../../config/database.php';



$page_title = "Edit Exam Category";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM exam_categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['slug'])));
    $description = trim($_POST['description']);
    $status = $_POST['status'];

    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    }

    try {
        $stmt = $pdo->prepare("UPDATE exam_categories SET name = ?, slug = ?, description = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $description, $status, $id]);
        $_SESSION['success_msg'] = "Category updated successfully!";
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}
?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Edit Exam Category</h1>
    </div>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <form action="" method="POST" class="needs-validation" novalidate>
        <div class="mb-3">
            <label for="name" class="form-label">Category Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($category['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="slug" class="form-label">Slug</label>
            <input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars($category['slug']) ?>">
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($category['description']) ?></textarea>
        </div>
        <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="active" <?= ($category['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($category['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Update Category</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</main>
<?php require_once '../includes/footer.php'; ?>

