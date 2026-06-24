<?php
/**
 * IT Team Space Module Header Component
 */

$path_prefix = $path_prefix ?? '../';
$current_module = 'it-team-space';
$module_title = 'IT Team Space';

// Ensure user is logged in
if (!isset($_SESSION['analyst_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$analyst_name = $_SESSION['analyst_name'] ?? 'Analyst';
$current_page = $current_page ?? '';

// Include the shared waffle menu component
require_once $path_prefix . 'includes/waffle-menu.php';
?>

<div class="header it-team-space-header">
    <div class="waffle-menu-container">
        <?php renderWaffleMenuButton(); ?>
        <?php renderWaffleMenuPanel($modules, $current_module, $path_prefix); ?>
        <span class="module-title"><?php echo $module_title; ?></span>
    </div>
    <nav class="header-nav">
        <a href="<?php echo BASE_URL; ?>it-team-space/" class="nav-btn <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" title="Dashboard">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="9" rx="1"></rect><rect x="14" y="3" width="7" height="5" rx="1"></rect><rect x="14" y="12" width="7" height="9" rx="1"></rect><rect x="3" y="16" width="7" height="5" rx="1"></rect>
            </svg>
            <span>Dashboard</span>
        </a>
    </nav>
    <?php renderHeaderRight($analyst_name, $path_prefix); ?>
</div>

<?php renderWaffleMenuJS(); ?>
