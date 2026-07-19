<?php
$page_title = "My Dashboard";
require_once 'includes/db.php';
require_once 'includes/session.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Check if user has a matrimony profile
$stmt = $pdo->prepare("SELECT id, is_premium FROM matrimony_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$matrimony_profile = $stmt->fetch();

// Check if user has a member profile (for profile picture)
$stmt2 = $pdo->prepare("SELECT profile_pic FROM member_profiles WHERE user_id = ?");
$stmt2->execute([$user_id]);
$member_profile = $stmt2->fetch();
$user_profile_pic = ($member_profile && $member_profile['profile_pic']) ? BASE_URL . "uploads/profile/" . $member_profile['profile_pic'] : "https://placehold.co/150x150/f39c12/white?text=User";

$is_admin = (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1);

// Get user's ID verification status
$stmt_id = $pdo->prepare("SELECT id_status, id_document FROM users WHERE id = ?");
$stmt_id->execute([$user_id]);
$user_id_data = $stmt_id->fetch();
$id_status = $user_id_data ? $user_id_data['id_status'] : null;

// Fetch online members (last 5 minutes)
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, p.profile_pic 
    FROM users u 
    LEFT JOIN member_profiles p ON u.id = p.user_id 
    WHERE u.id != ? AND u.last_active >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY u.last_active DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$online_members = $stmt->fetchAll();

// Admin Actions
if ($is_admin && isset($_GET['admin_action'], $_GET['profile_id'])) {
    $action = $_GET['admin_action'];
    $p_id = (int)$_GET['profile_id'];
    
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM matrimony_profiles WHERE id = ?")->execute([$p_id]);
        setFlashMessage('success', 'Matrimony profile deleted successfully.');
    } elseif ($action === 'block') {
        $stmt = $pdo->prepare("SELECT user_id FROM matrimony_profiles WHERE id = ?");
        $stmt->execute([$p_id]);
        $u_id = $stmt->fetchColumn();
        if ($u_id) {
            $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$u_id]);
            setFlashMessage('success', 'User account has been blocked.');
        }
    } elseif ($action === 'approve_id') {
        $u_id = (int)$_GET['user_id'];
        $pdo->prepare("UPDATE users SET id_status = 'approved' WHERE id = ?")->execute([$u_id]);
        setFlashMessage('success', 'User ID has been approved and Profile verified.');
    } elseif ($action === 'reject_id') {
        $u_id = (int)$_GET['user_id'];
        // delete document file if it exists
        $stmt = $pdo->prepare("SELECT id_document FROM users WHERE id = ?");
        $stmt->execute([$u_id]);
        $doc = $stmt->fetchColumn();
        if($doc && file_exists("uploads/id_documents/" . $doc)) {
            unlink("uploads/id_documents/" . $doc);
        }
        $pdo->prepare("UPDATE users SET id_status = 'rejected', id_document = NULL WHERE id = ?")->execute([$u_id]);
        setFlashMessage('success', 'User ID has been rejected.');
    }
    header("Location: dashboard.php");
    exit;
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-3">
            <div class="card card-custom mb-4 shadow-sm">
                <div class="card-body text-center">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?= htmlspecialchars($user_profile_pic) ?>" class="rounded-circle border border-3 border-warning" alt="Avatar" style="width: 150px; height: 150px; object-fit: cover;">
                        <button class="btn btn-sm btn-light position-absolute bottom-0 end-0 rounded-circle shadow border" type="button" data-bs-toggle="modal" data-bs-target="#profilePicModal" title="Change Profile Picture">
                            <i class="fa-solid fa-camera text-primary"></i>
                        </button>
                    </div>
                    <h5 class="fw-bold"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></h5>
                    <?php if($matrimony_profile && $matrimony_profile['is_premium']): ?>
                        <span class="badge bg-warning text-dark mb-0"><i class="fa-solid fa-crown me-1"></i> Premium Member</span>
                        <div class="text-success mt-1 small fw-bold">Validity: Lifetime</div>
                    <?php else: ?>
                        <span class="badge bg-secondary mb-0">Free Member</span>
                        <div class="text-muted mt-1 small">Validity: Basic Plan</div>
                    <?php endif; ?>

                    <hr class="my-3">
                    <!-- ID Verification Badge -->
                    <?php if($id_status === 'approved'): ?>
                        <div class="badge bg-success p-2 w-100"><i class="fa-solid fa-circle-check me-1"></i> Verified Profile</div>
                    <?php elseif($id_status === 'pending'): ?>
                        <div class="badge bg-warning text-dark p-2 w-100"><i class="fa-solid fa-clock me-1"></i> Verification Pending</div>
                    <?php else: ?>
                        <button class="btn btn-sm btn-outline-primary w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#idUploadModal">
                            <i class="fa-solid fa-shield-halved me-1"></i> Verify Profile
                        </button>
                        <?php if($id_status === 'rejected'): ?>
                            <div class="text-danger small mt-1 fw-bold">Previous ID Rejected.</div>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
                <div class="list-group list-group-flush text-start">
                    <a href="dashboard.php" class="list-group-item list-group-item-action active bg-warning border-warning"><i class="fa-solid fa-gauge me-2"></i> Dashboard</a>
                    <?php if($matrimony_profile): ?>
                        <a href="discussion.php" class="list-group-item list-group-item-action"><i class="fa-solid fa-comments me-2"></i> Discussions</a>
                    <?php endif; ?>
                    <a href="logout.php" class="list-group-item list-group-item-action text-danger"><i class="fa-solid fa-sign-out-alt me-2"></i> Logout</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <?php displayFlashMessage(); ?>
            <h3 class="fw-bold mb-4">Welcome to Your Dashboard</h3>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card card-custom h-100 p-4 border-top border-4 border-warning shadow-sm">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="fw-bold"><i class="fa-solid fa-heart text-danger me-2"></i> Matrimony</h5>
                            <?php if($matrimony_profile && $matrimony_profile['is_premium']): ?>
                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-crown"></i> Premium</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($matrimony_profile): ?>
                            <p class="text-success mt-2 mb-1"><i class="fa-solid fa-check-circle me-1"></i> Profile Active</p>
                            <?php if(!$matrimony_profile['is_premium']): ?>
                                <p class="text-muted small mb-2">You are a Free Member. Upgrade to chat with matches.</p>
                                <div class="mt-auto d-flex flex-column gap-2">
                                    <a href="matrimony.php" class="btn btn-outline-danger fw-bold"><i class="fa-solid fa-search me-1"></i> Find Matches</a>
                                    <div class="d-flex gap-2">
                                        <a href="upgrade.php" class="btn btn-warning fw-bold flex-grow-1"><i class="fa-solid fa-crown me-1"></i> Upgrade</a>
                                        <a href="matrimony-edit.php" class="btn btn-outline-secondary" title="Premium Required"><i class="fa-solid fa-lock me-1"></i> Edit</a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small mb-2">You have full access to view profiles and start discussions.</p>
                                <div class="mt-auto d-flex flex-column gap-2">
                                    <a href="matrimony.php" class="btn btn-danger fw-bold text-white"><i class="fa-solid fa-search me-1"></i> Find Matches</a>
                                    <div class="d-flex gap-2">
                                        <a href="discussion.php" class="btn btn-outline-warning flex-grow-1">Discussions</a>
                                        <a href="matrimony-edit.php" class="btn btn-outline-secondary"><i class="fa-solid fa-edit me-1"></i> Edit</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-danger mt-2"><i class="fa-solid fa-times-circle me-1"></i> Not Created</p>
                            <p class="text-muted small">Create your matrimony profile to find your perfect match.</p>
                            <a href="matrimony-register.php" class="btn btn-warning mt-auto">Create Profile Now</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card card-custom h-100 p-4 border-top border-4 border-info shadow-sm">
                        <h5 class="fw-bold"><i class="fa-solid fa-briefcase text-info me-2"></i> Business Listing</h5>
                        <p class="text-muted small mt-2">Add your business to the community directory and grow your network.</p>
                        <a href="business-register.php" class="btn btn-outline-info mt-auto">Add Business</a>
                    </div>
                </div>
                
                <div class="col-md-6 mt-4">
                    <div class="card card-custom h-100 p-4 border-top border-4 border-primary shadow-sm">
                        <h5 class="fw-bold"><i class="fa-solid fa-user-tie text-primary me-2"></i> Jobs & Opportunities</h5>
                        <p class="text-muted small mt-2">Post a job to hire talent directly from the community.</p>
                        <a href="job-post.php" class="btn btn-outline-primary mt-auto">Post a Job</a>
                    </div>
                </div>
                
                <div class="col-md-6 mt-4">
                    <div class="card card-custom h-100 p-4 border-top border-4 border-danger shadow-sm">
                        <h5 class="fw-bold"><i class="fa-solid fa-droplet text-danger me-2"></i> Blood Bank</h5>
                        <p class="text-muted small mt-2">Register as a blood donor and save lives during emergencies.</p>
                        <a href="blood-register.php" class="btn btn-outline-danger mt-auto">Register as Donor</a>
                    </div>
                </div>
            </div>
            </div>
            
            <!-- Online Members Widget -->
            <div class="card card-custom mt-4 p-4 border-top border-4 border-success shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-satellite-dish text-success me-2 fa-fade"></i> Currently Online Members</h5>
                    <span class="badge bg-success rounded-pill"><?= count($online_members) ?> Online</span>
                </div>
                
                <?php if(empty($online_members)): ?>
                    <p class="text-muted small">No other members are currently online.</p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach($online_members as $ou): 
                            $pic = $ou['profile_pic'] ? BASE_URL . "uploads/profile/" . $ou['profile_pic'] : "https://placehold.co/50x50/f39c12/white?text=U";
                        ?>
                            <div class="text-center">
                                <a href="discussion.php?user_id=<?= $ou['id'] ?>" class="text-decoration-none d-block position-relative" title="Chat with <?= htmlspecialchars($ou['first_name']) ?>">
                                    <img src="<?= htmlspecialchars($pic) ?>" class="rounded-circle border border-2 border-success" style="width: 50px; height: 50px; object-fit: cover;">
                                    <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-light rounded-circle" style="transform: translate(-25%, -25%);">
                                        <span class="visually-hidden">Online</span>
                                    </span>
                                </a>
                                <small class="text-dark fw-bold d-block mt-1" style="font-size: 0.75rem;"><?= htmlspecialchars($ou['first_name']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
            
            <?php if ($is_admin): ?>
                <?php
                $admin_profiles = $pdo->query("
                    SELECT m.id, m.user_id, m.profile_for, m.gender, u.first_name, u.last_name, u.status 
                    FROM matrimony_profiles m 
                    JOIN users u ON m.user_id = u.id 
                    ORDER BY m.created_at DESC
                ")->fetchAll();
                ?>
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card card-custom p-4 border-top border-4 border-danger shadow-sm">
                            <h4 class="fw-bold text-danger mb-4"><i class="fa-solid fa-shield-halved me-2"></i> Admin Controls: Manage Matrimony Profiles</h4>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Profile For</th>
                                            <th>Gender</th>
                                            <th>User Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($admin_profiles as $ap): ?>
                                        <tr>
                                            <td>VISH-<?= str_pad($ap['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                            <td><?= htmlspecialchars($ap['first_name'] . ' ' . $ap['last_name']) ?></td>
                                            <td><?= htmlspecialchars($ap['profile_for']) ?></td>
                                            <td><?= htmlspecialchars($ap['gender']) ?></td>
                                            <td>
                                                <?php if($ap['status'] == 'banned'): ?>
                                                    <span class="badge bg-danger">Blocked</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="profile.php?id=<?= $ap['id'] ?>" class="btn btn-sm btn-info text-white"><i class="fa-solid fa-eye"></i> View</a>
                                                
                                                <?php if($ap['status'] != 'banned'): ?>
                                                    <a href="dashboard.php?admin_action=block&profile_id=<?= $ap['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to block this user?')"><i class="fa-solid fa-ban"></i> Block</a>
                                                <?php endif; ?>
                                                
                                                <a href="dashboard.php?admin_action=delete&profile_id=<?= $ap['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete this matrimony profile?')"><i class="fa-solid fa-trash"></i> Delete</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if(empty($admin_profiles)): ?>
                                            <tr><td colspan="6" class="text-center text-muted">No profiles found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                $pending_ids = $pdo->query("SELECT id, first_name, last_name, id_document, created_at FROM users WHERE id_status = 'pending' ORDER BY created_at ASC")->fetchAll();
                ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card card-custom p-4 border-top border-4 border-warning shadow-sm">
                            <h4 class="fw-bold text-warning mb-4"><i class="fa-solid fa-id-card me-2"></i> Pending ID Verifications</h4>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>User ID</th>
                                            <th>Name</th>
                                            <th>Document</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($pending_ids as $pid): ?>
                                        <tr>
                                            <td><?= $pid['id'] ?></td>
                                            <td><?= htmlspecialchars($pid['first_name'] . ' ' . $pid['last_name']) ?></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>uploads/id_documents/<?= $pid['id_document'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-file me-1"></i> View ID</a>
                                            </td>
                                            <td>
                                                <a href="dashboard.php?admin_action=approve_id&user_id=<?= $pid['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this ID and give a Blue Tick?')"><i class="fa-solid fa-check"></i> Approve</a>
                                                <a href="dashboard.php?admin_action=reject_id&user_id=<?= $pid['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this ID? Document will be deleted.')"><i class="fa-solid fa-xmark"></i> Reject</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if(empty($pending_ids)): ?>
                                            <tr><td colspan="4" class="text-center text-muted">No pending ID verifications.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Profile Picture Modal -->
<div class="modal fade" id="profilePicModal" tabindex="-1" aria-labelledby="profilePicModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning border-0">
                <h5 class="modal-title fw-bold text-dark" id="profilePicModalLabel">Update Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="api/update_profile_pic.php" method="POST" enctype="multipart/form-data" id="profilePicForm">
                    <input type="hidden" name="action" value="upload" id="profilePicAction">
                    
                    <div class="mb-4 text-center">
                        <label for="profile_pic_input" class="form-label fw-bold d-block">Select New Photo</label>
                        <input class="form-control" type="file" id="profile_pic_input" name="profile_pic" accept="image/jpeg, image/png, image/webp, image/avif" required>
                        <small class="text-muted mt-2 d-block">Allowed formats: JPG, PNG, WEBP. Max size: 2MB.</small>
                    </div>
                    
                    <div class="d-flex justify-content-between gap-2">
                        <?php if($member_profile && !empty($member_profile['profile_pic'])): ?>
                            <button type="button" class="btn btn-outline-danger fw-bold flex-grow-1" onclick="document.getElementById('profilePicAction').value='delete'; document.getElementById('profile_pic_input').removeAttribute('required'); document.getElementById('profilePicForm').submit();">
                                <i class="fa-solid fa-trash me-1"></i> Remove
                            </button>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-warning fw-bold text-dark flex-grow-1">
                            <i class="fa-solid fa-upload me-1"></i> Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ID Verification Upload Modal -->
<div class="modal fade" id="idUploadModal" tabindex="-1" aria-labelledby="idUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold" id="idUploadModalLabel"><i class="fa-solid fa-shield-halved me-2"></i> Get Verified (Blue Tick)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="api/upload_id.php" method="POST" enctype="multipart/form-data">
                    <p class="text-muted small mb-4">Upload a valid Government ID (Aadhar, PAN, Driving License, or Voter ID) to get a blue tick on your profile. This increases trust among other members.</p>
                    
                    <div class="mb-4">
                        <label for="id_document" class="form-label fw-bold">Select ID Document</label>
                        <input class="form-control" type="file" id="id_document" name="id_document" accept="image/jpeg, image/png, application/pdf" required>
                        <small class="text-muted mt-2 d-block">Formats: JPG, PNG, PDF. Max size: 5MB.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary fw-bold w-100">
                        <i class="fa-solid fa-upload me-1"></i> Submit for Verification
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
