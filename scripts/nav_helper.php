<?php
/**
 * StudentHub: Dynamic Role-Based Navigation Helper (Practical 14)
 * Generates role-aware navigation menus and topbars based on server-side session role.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

/**
 * Returns navigation items dynamically tailored to the authenticated user's role.
 *
 * @param string $activeKey Key of current page to highlight
 * @return array
 */
function getRoleNavItems(string $activeKey = ''): array {
    $admin = isAdmin();

    if ($admin) {
        // Administrator Navigation Items (Strict RBAC: admin only)
        return [
            [
                'key'   => 'admin_dashboard',
                'label' => 'Admin Dashboard',
                'url'   => 'admin_dashboard.php',
                'icon'  => '📊',
                'badge' => 'Admin',
                'badge_class' => 'badge-danger'
            ],
            [
                'key'   => 'students',
                'label' => 'Student Management',
                'url'   => 'students.php',
                'icon'  => '👥',
                'badge' => 'CRUD',
                'badge_class' => 'badge-info'
            ],
            [
                'key'   => 'events',
                'label' => 'Event Management',
                'url'   => 'events.php',
                'icon'  => '📅',
                'badge' => 'CRUD & Poster',
                'badge_class' => 'badge-info'
            ],
            [
                'key'   => 'registrations',
                'label' => 'Event Registrations',
                'url'   => 'Event_registration.php',
                'icon'  => '📝',
                'badge' => null,
                'badge_class' => null
            ],
            [
                'key'   => 'db_status',
                'label' => 'Database Status',
                'url'   => 'db_status.php',
                'icon'  => '🗄️',
                'badge' => null,
                'badge_class' => null
            ],
            [
                'key'   => 'home',
                'label' => 'Student Portal View',
                'url'   => 'home.php',
                'icon'  => '🎓',
                'badge' => 'View',
                'badge_class' => 'badge-info'
            ],
            [
                'key'   => 'settings',
                'label' => 'Settings',
                'url'   => 'settings.html',
                'icon'  => '⚙️',
                'badge' => null,
                'badge_class' => null
            ],
            [
                'key'   => 'logout',
                'label' => 'Logout',
                'url'   => 'logout.php',
                'icon'  => '🚪',
                'badge' => null,
                'badge_class' => null,
                'is_danger' => true
            ]
        ];
    }

    // Student Navigation Items (Standard authorized user)
    return [
        [
            'key'   => 'home',
            'label' => 'Student Dashboard',
            'url'   => 'home.php',
            'icon'  => '🏠',
            'badge' => null,
            'badge_class' => null
        ],
        [
            'key'   => 'events',
            'label' => 'Campus Events',
            'url'   => 'events.php',
            'icon'  => '📅',
            'badge' => null,
            'badge_class' => null
        ],
        [
            'key'   => 'profile',
            'label' => 'Profile',
            'url'   => 'profile.html',
            'icon'  => '👤',
            'badge' => null,
            'badge_class' => null
        ],
        [
            'key'   => 'academics',
            'label' => 'Academics',
            'url'   => 'academics.html',
            'icon'  => '📚',
            'badge' => null,
            'badge_class' => null
        ],
        [
            'key'   => 'fees',
            'label' => 'Fee Details',
            'url'   => 'fees.html',
            'icon'  => '💳',
            'badge' => null,
            'badge_class' => null
        ],
        [
            'key'   => 'clubs',
            'label' => 'Student Clubs',
            'url'   => 'clubs.html',
            'icon'  => '🏆',
            'badge' => null,
            'badge_class' => null
        ],
        [
            'key'   => 'extracurricular',
            'label' => 'Extracurriculars',
            'url'   => 'extracurricular.html',
            'icon'  => '🎨',
            'badge' => null,
            'badge_class' => null
        ],
        [
            'key'   => 'registrations',
            'label' => 'Event Registration',
            'url'   => 'Event_registration.php',
            'icon'  => '📝',
            'badge' => null,
            'badge_class' => null
        ],
        [
            'key'   => 'students',
            'label' => 'Student Directory',
            'url'   => 'students.php',
            'icon'  => '👥',
            'badge' => 'Directory',
            'badge_class' => 'badge-info'
        ],
        [
            'key'   => 'db_status',
            'label' => 'Database Status',
            'url'   => 'db_status.php',
            'icon'  => '🗄️',
            'badge' => null,
            'badge_class' => null
        ],
        [
            'key'   => 'settings',
            'label' => 'Settings',
            'url'   => 'settings.html',
            'icon'  => '⚙️',
            'badge' => null,
            'badge_class' => null
        ],
        [
            'key'   => 'logout',
            'label' => 'Logout',
            'url'   => 'logout.php',
            'icon'  => '🚪',
            'badge' => null,
            'badge_class' => null,
            'is_danger' => true
        ]
    ];
}

/**
 * Renders the HTML sidebar menu buttons according to user role.
 *
 * @param string $activeKey Page identifier to highlight
 */
function renderSidebarNavigation(string $activeKey = ''): void {
    $items = getRoleNavItems($activeKey);
    $admin = isAdmin();

    echo '<div id="menu-options" class="role-menu">';
    echo '<div style="margin-bottom:12px; padding:6px 8px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:var(--text-muted); border-bottom:1px solid var(--border);">';
    echo $admin ? '🛡️ Administrator Navigation' : '🎓 Student Navigation';
    echo '</div>';

    foreach ($items as $item) {
        $isActive = ($item['key'] === $activeKey);
        $isDanger = !empty($item['is_danger']);

        $style = 'width:100%; margin-bottom:8px; text-align:left; display:flex; align-items:center; justify-content:space-between; padding:9px 12px;';
        $class = '';

        if ($isDanger) {
            $class = 'btn-danger';
            $style .= ' margin-top:8px; justify-content:center;';
        } elseif ($isActive) {
            $style .= ' font-weight:700; background:var(--primary); color:#ffffff; border:1px solid var(--primary-dark);';
        }

        echo '<a href="' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '" style="text-decoration:none; display:block;">';
        echo '<button type="button" class="' . $class . '" style="' . $style . '">';
        echo '<span>' . $item['icon'] . ' ' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span>';
        if (!empty($item['badge']) && !$isActive) {
            echo '<span class="badge ' . htmlspecialchars($item['badge_class'], ENT_QUOTES, 'UTF-8') . '" style="font-size:10px; margin-left:6px;">' . htmlspecialchars($item['badge'], ENT_QUOTES, 'UTF-8') . '</span>';
        }
        echo '</button>';
        echo '</a>';
    }

    echo '</div>';
}
