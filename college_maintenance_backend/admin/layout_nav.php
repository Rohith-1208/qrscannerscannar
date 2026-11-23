<?php
// ==============================================================================
// Admin Navigation Component
// ==============================================================================

function renderAdminSidebar(string $activePage, array $adminUser): void {
?>
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="logo-badge">QM</div>
      <div>
        <h2>College SysMaint</h2>
        <p>Geo-Verified Maintenance</p>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="dashboard.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        Dashboard
      </a>

      <a href="systems.php" class="nav-link <?= $activePage === 'systems' ? 'active' : '' ?>">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
        Equipment & QR
      </a>

      <a href="agents.php" class="nav-link <?= $activePage === 'agents' ? 'active' : '' ?>">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        Service Agents
      </a>

      <a href="reports.php" class="nav-link <?= $activePage === 'reports' ? 'active' : '' ?>">
        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Maintenance Reports
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="admin-info">
        <h4><?= htmlspecialchars($adminUser['name']) ?></h4>
        <p><?= htmlspecialchars($adminUser['email']) ?></p>
      </div>
      <a href="logout.php" class="btn-logout">Logout</a>
    </div>
  </aside>
<?php
}
