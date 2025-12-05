<?php
/**
 * WAPOS - Site Content Editor
 * Edit website content without touching code
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once 'includes/bootstrap.php';
require_once 'app/Services/ContentManager.php';

use App\Services\ContentManager;

// Require super_admin or developer access
$auth->requireLogin();
$userRole = $auth->getRole();
if (!in_array($userRole, ['super_admin', 'developer'], true)) {
    header('Location: ' . APP_URL . '/access-denied.php');
    exit;
}

$contentManager = ContentManager::getInstance();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save_content') {
            $key = $_POST['content_key'] ?? '';
            $content = $_POST['content'] ?? '';
            $type = $_POST['content_type'] ?? 'text';
            $page = $_POST['page'] ?? 'home';
            
            if (!empty($key)) {
                $contentManager->set($key, $content, $type, $page);
                $message = 'Content saved successfully!';
                $messageType = 'success';
            }
        }
        
        if ($action === 'add_content') {
            $key = preg_replace('/[^a-z0-9_]/', '_', strtolower($_POST['new_key'] ?? ''));
            $content = $_POST['new_content'] ?? '';
            $type = $_POST['new_type'] ?? 'text';
            $page = $_POST['new_page'] ?? 'home';
            
            if (!empty($key)) {
                $contentManager->set($key, $content, $type, $page);
                $message = 'New content added!';
                $messageType = 'success';
            }
        }
        
        if ($action === 'delete_content') {
            $key = $_POST['delete_key'] ?? '';
            if (!empty($key)) {
                $contentManager->delete($key);
                $message = 'Content deleted.';
                $messageType = 'warning';
            }
        }
        
        if ($action === 'seed_defaults') {
            $contentManager->seedDefaults();
            $message = 'Default content has been loaded!';
            $messageType = 'success';
        }
    }
}

// Get all content grouped by page
$allContent = $contentManager->getAllContent();
$pages = [];
foreach ($allContent as $item) {
    $pages[$item['page']][] = $item;
}

$pageTitle = 'Site Content Editor';
include 'includes/header.php';
?>

<style>
    .editor-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    .content-card {
        background: var(--surface-card);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        margin-bottom: 16px;
        overflow: hidden;
    }
    .content-card-header {
        background: var(--surface-muted);
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .content-key {
        font-family: monospace;
        font-size: 0.85rem;
        color: var(--primary);
        background: rgba(102, 126, 234, 0.1);
        padding: 2px 8px;
        border-radius: 4px;
    }
    .content-card-body {
        padding: 16px;
    }
    .page-section {
        margin-bottom: 32px;
    }
    .page-section h3 {
        text-transform: capitalize;
        margin-bottom: 16px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--primary);
        display: inline-block;
    }
    .btn-delete {
        padding: 4px 8px;
        font-size: 0.75rem;
    }
    .preview-text {
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-top: 8px;
        padding: 8px;
        background: var(--surface-muted);
        border-radius: 4px;
        max-height: 100px;
        overflow: hidden;
    }
    .quick-edit {
        display: none;
    }
    .content-card:hover .quick-edit {
        display: inline-block;
    }
    .add-content-form {
        background: var(--surface-card);
        border-radius: 12px;
        padding: 24px;
        border: 2px dashed var(--border-color);
    }
    .type-badge {
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 4px;
        background: var(--surface-muted);
        color: var(--text-muted);
        text-transform: uppercase;
    }
</style>

<div class="container-fluid py-4">
    <div class="editor-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-pencil-square me-2"></i>Site Content Editor</h1>
                <p class="text-muted mb-0">Edit website content without touching code</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>" target="_blank" class="btn btn-outline-secondary">
                    <i class="bi bi-eye me-1"></i> Preview Site
                </a>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="seed_defaults">
                    <button type="submit" class="btn btn-outline-primary" onclick="return confirm('Load default content? This won\'t overwrite existing content.')">
                        <i class="bi bi-magic me-1"></i> Load Defaults
                    </button>
                </form>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Tips -->
        <div class="alert alert-info mb-4">
            <h6 class="alert-heading"><i class="bi bi-lightbulb me-2"></i>How to Use</h6>
            <ul class="mb-0 small">
                <li>Click on any content to edit it directly</li>
                <li>Changes are saved immediately when you click "Save"</li>
                <li>Use <strong>text</strong> for short content, <strong>textarea</strong> for longer text</li>
                <li>Content keys are used in templates: <code>content('key_name')</code></li>
            </ul>
        </div>

        <!-- Existing Content by Page -->
        <?php foreach ($pages as $pageName => $pageContent): ?>
            <div class="page-section">
                <h3><i class="bi bi-file-earmark-text me-2"></i><?= htmlspecialchars(ucfirst($pageName)) ?> Page</h3>
                
                <?php foreach ($pageContent as $item): ?>
                    <div class="content-card">
                        <div class="content-card-header">
                            <div>
                                <span class="content-key"><?= htmlspecialchars($item['content_key']) ?></span>
                                <span class="type-badge ms-2"><?= htmlspecialchars($item['content_type']) ?></span>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-primary quick-edit" 
                                        onclick="toggleEdit('<?= htmlspecialchars($item['content_key']) ?>')">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="action" value="delete_content">
                                    <input type="hidden" name="delete_key" value="<?= htmlspecialchars($item['content_key']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-delete" 
                                            onclick="return confirm('Delete this content?')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="content-card-body">
                            <!-- View Mode -->
                            <div id="view-<?= htmlspecialchars($item['content_key']) ?>">
                                <div class="preview-text">
                                    <?= nl2br(htmlspecialchars(substr($item['content'], 0, 300))) ?>
                                    <?= strlen($item['content']) > 300 ? '...' : '' ?>
                                </div>
                                <button class="btn btn-sm btn-primary mt-2" 
                                        onclick="toggleEdit('<?= htmlspecialchars($item['content_key']) ?>')">
                                    <i class="bi bi-pencil me-1"></i> Edit Content
                                </button>
                            </div>
                            
                            <!-- Edit Mode -->
                            <form method="POST" id="edit-<?= htmlspecialchars($item['content_key']) ?>" style="display: none;">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="save_content">
                                <input type="hidden" name="content_key" value="<?= htmlspecialchars($item['content_key']) ?>">
                                <input type="hidden" name="content_type" value="<?= htmlspecialchars($item['content_type']) ?>">
                                <input type="hidden" name="page" value="<?= htmlspecialchars($item['page']) ?>">
                                
                                <?php if ($item['content_type'] === 'text'): ?>
                                    <input type="text" name="content" class="form-control" 
                                           value="<?= htmlspecialchars($item['content']) ?>">
                                <?php else: ?>
                                    <textarea name="content" class="form-control" rows="4"><?= htmlspecialchars($item['content']) ?></textarea>
                                <?php endif; ?>
                                
                                <div class="mt-2">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="bi bi-check-lg me-1"></i> Save
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" 
                                            onclick="toggleEdit('<?= htmlspecialchars($item['content_key']) ?>')">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php if (empty($pages)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <h4 class="mt-3">No Content Yet</h4>
                <p class="text-muted">Click "Load Defaults" to get started with pre-configured content.</p>
            </div>
        <?php endif; ?>

        <!-- Add New Content -->
        <div class="page-section">
            <h3><i class="bi bi-plus-circle me-2"></i>Add New Content</h3>
            <div class="add-content-form">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_content">
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Content Key</label>
                            <input type="text" name="new_key" class="form-control" 
                                   placeholder="e.g., home_welcome_message" required
                                   pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only">
                            <div class="form-text">Lowercase, no spaces (use underscores)</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Page</label>
                            <select name="new_page" class="form-select">
                                <option value="home">Home</option>
                                <option value="about">About</option>
                                <option value="contact">Contact</option>
                                <option value="global">Global (All Pages)</option>
                                <option value="seo">SEO</option>
                                <option value="footer">Footer</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <select name="new_type" class="form-select">
                                <option value="text">Text (Single Line)</option>
                                <option value="textarea">Textarea (Multi-line)</option>
                                <option value="richtext">Rich Text</option>
                                <option value="html">HTML</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Content</label>
                            <textarea name="new_content" class="form-control" rows="3" 
                                      placeholder="Enter your content here..."></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-lg me-1"></i> Add Content
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleEdit(key) {
    const viewEl = document.getElementById('view-' + key);
    const editEl = document.getElementById('edit-' + key);
    
    if (viewEl.style.display === 'none') {
        viewEl.style.display = 'block';
        editEl.style.display = 'none';
    } else {
        viewEl.style.display = 'none';
        editEl.style.display = 'block';
        // Focus on input
        const input = editEl.querySelector('input[name="content"], textarea[name="content"]');
        if (input) input.focus();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
