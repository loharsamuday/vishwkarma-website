<?php
// admin/categories.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = 'Manage Categories';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $slug = generateSlug($name);
    
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
            $stmt->execute([$name, $slug]);
            $success_msg = "Category added successfully.";
        } catch(PDOException $e) {
            $error_msg = "Error adding category. It might already exist.";
        }
    }
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: categories.php");
    exit();
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Categories</h1>
</div>

<?php if (isset($success_msg)): ?>
    <div class="alert alert-success"><?= escape($success_msg) ?></div>
<?php endif; ?>
<?php if (isset($error_msg)): ?>
    <div class="alert alert-danger"><?= escape($error_msg) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">Add New Category</div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary bg-primary-custom w-100">Add Category</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">Categories List</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM categories ORDER BY id DESC");
                            while ($row = $stmt->fetch()):
                            ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= escape($row['name']) ?></td>
                                <td><?= escape($row['slug']) ?></td>
                                <td class="text-end">
                                    <a href="categories.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this category? Stories under it will become uncategorized.');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
