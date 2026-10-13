<?php
/**
 * GS Remera TSS School System
 * Single-file school management system
 *
 * Combines: config, auth, admin, teacher, student, parent, api, includes, assets, uploads, reports, templates, logs, storage
 *
 * Usage:
 *   1. Place this file in your web root (e.g., /var/www/html/gs-remera-tss.php)
 *   2. Create a MySQL database named `gs_remera` and import the schema below (or let the script auto-create)
 *   3. Edit the DB credentials in the CONFIG section
 *   4. Open http://localhost/gs-remera-tss.php
 *
 * Default admin: admin@gsremera.rw / bebe 2007@
 */

// ============================================================
// 1. CONFIG
// ============================================================
define('DB_HOST', 'localhost:3307');
define('DB_NAME', 'gs_remera');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('SCHOOL_NAME', 'GS Remera TSS');
define('SCHOOL_MOTTO', 'Knowledge, Skills, Service');
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('LOG_DIR', __DIR__ . '/logs/');
define('STORAGE_DIR', __DIR__ . '/storage/');
define('TEMPLATE_DIR', __DIR__ . '/templates/');

define('SESSION_LIFETIME', 3600);
define('DEFAULT_PASSWORD', 'bebe 2007@');

// ============================================================
// 2. ERROR & SESSION SETUP
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

foreach ([LOG_DIR, STORAGE_DIR . 'cache/', STORAGE_DIR . 'sessions/', STORAGE_DIR . 'temp/',
          UPLOAD_DIR . 'students/', UPLOAD_DIR . 'teachers/', UPLOAD_DIR . 'parents/',
          UPLOAD_DIR . 'documents/', UPLOAD_DIR . 'school/',
          __DIR__ . '/reports/pdf/', __DIR__ . '/reports/excel/', __DIR__ . '/reports/generated/'] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
}

ini_set('error_log', LOG_DIR . 'error.log');
ini_set('session.save_path', STORAGE_DIR . 'sessions/');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_start();

// ============================================================
// 3. DATABASE
// ============================================================
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}

function db_install(): void {
    $pdo = db();
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','headmaster','teacher','student','parent') NOT NULL,
    phone VARCHAR(20),
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20),
    description TEXT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    level VARCHAR(20),
    department_id INT,
    capacity INT DEFAULT 40,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    admission_no VARCHAR(30) UNIQUE,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    gender ENUM('M','F'),
    dob DATE,
    class_id INT,
    parent_id INT,
    address TEXT,
    photo VARCHAR(255) DEFAULT 'default-avatar.png',
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    employee_no VARCHAR(30) UNIQUE,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    gender ENUM('M','F'),
    qualification VARCHAR(100),
    department_id INT,
    phone VARCHAR(20),
    address TEXT,
    photo VARCHAR(255) DEFAULT 'default-avatar.png',
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    address TEXT,
    occupation VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    department_id INT,
    teacher_id INT,
    class_id INT,
    credits INT DEFAULT 1,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    class_id INT,
    subject_id INT,
    date DATE,
    status ENUM('present','absent','late','excused') DEFAULT 'present',
    remarks VARCHAR(255),
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    term VARCHAR(20),
    year YEAR,
    start_date DATE,
    end_date DATE,
    status ENUM('draft','active','completed') DEFAULT 'draft'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    subject_id INT,
    exam_id INT,
    score DECIMAL(5,2),
    max_score DECIMAL(5,2) DEFAULT 100,
    grade VARCHAR(5),
    remarks VARCHAR(255),
    entered_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    term VARCHAR(20),
    year YEAR,
    amount DECIMAL(10,2),
    paid DECIMAL(10,2) DEFAULT 0,
    balance DECIMAL(10,2) GENERATED ALWAYS AS (amount - paid) STORED,
    due_date DATE,
    status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fee_id INT,
    student_id INT,
    amount DECIMAL(10,2),
    method ENUM('cash','bank','mobile','cheque') DEFAULT 'cash',
    reference VARCHAR(100),
    receipt_no VARCHAR(50) UNIQUE,
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recorded_by INT,
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    audience ENUM('all','students','teachers','parents','staff') DEFAULT 'all',
    posted_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT,
    subject_id INT,
    teacher_id INT,
    day ENUM('Mon','Tue','Wed','Thu','Fri') DEFAULT 'Mon',
    start_time TIME,
    end_time TIME,
    room VARCHAR(50),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),
    ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
SQL;

    foreach (explode(';', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            try { $pdo->exec($stmt); } catch (PDOException $e) { /* ignore */ }
        }
    }

    // Seed admin
    $count = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if ($count == 0) {
        $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")
            ->execute(['System Administrator', 'admin@gsremera.rw', DEFAULT_PASSWORD, 'admin']);
    }

    // Seed sample data
    if ($pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO departments (name,code,description) VALUES
            ('Science','SCI','Physics, Chemistry, Biology'),
            ('Mathematics','MATH','Pure and Applied Mathematics'),
            ('Languages','LANG','English, Kinyarwanda, French'),
            ('ICT','ICT','Computer Science and IT'),
            ('Humanities','HUM','History, Geography, Economics')");

        $pdo->exec("INSERT INTO classes (name,level,department_id,capacity) VALUES
            ('S1', 'S1', 1, 40),
            ('S2', 'S2', 2, 40),
            ('S3', 'S3', 3, 40),
            ('S4', 'S4', 4, 40),
            ('S5', 'S5', 1, 35),
            ('S6', 'S6', 5, 35)");

        $pdo->exec("INSERT INTO exams (name,term,year,start_date,end_date,status) VALUES
            ('Term 1 Midterm','Term 1',2025,'2025-03-10','2025-03-21','completed'),
            ('Term 1 Final','Term 1',2025,'2025-05-12','2025-05-23','active'),
            ('Term 2 Midterm','Term 2',2025,'2025-07-14','2025-07-25','draft')");

        // Sample users
        $users = [
            ['Alice Teacher','teacher@gsremera.rw','teacher'],
            ['Bob Student','student@gsremera.rw','student'],
            ['Carol Parent','parent@gsremera.rw','parent'],
            ['David Headmaster','headmaster@gsremera.rw','headmaster'],
        ];
        $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
        foreach ($users as $u) $stmt->execute([$u[0],$u[1],DEFAULT_PASSWORD,$u[2]]);

        // Teacher profile
        $pdo->exec("INSERT INTO teachers (user_id,employee_no,first_name,last_name,gender,qualification,department_id,phone)
            VALUES (2,'EMP001','Alice','Teacher','F','BSc Education',1,'0788000001')");
        // Student profile
        $pdo->exec("INSERT INTO students (user_id,admission_no,first_name,last_name,gender,dob,class_id,parent_id,address)
            VALUES (3,'STU001','Bob','Student','M','2008-05-15',6,1,'Kigali')");
        // Parent profile
        $pdo->exec("INSERT INTO parents (user_id,first_name,last_name,phone,address,occupation)
            VALUES (4,'Carol','Parent','0788000003','Kigali','Engineer')");

        // Subjects
        $pdo->exec("INSERT INTO subjects (name,code,department_id,teacher_id,class_id,credits) VALUES
            ('Mathematics','MATH101',2,1,6,4),
            ('Physics','PHY101',1,1,6,4),
            ('Chemistry','CHE101',1,1,6,3),
            ('Biology','BIO101',1,1,6,3),
            ('English','ENG101',3,1,6,2),
            ('ICT','ICT101',4,1,6,3)");

        // Fees
        $pdo->exec("INSERT INTO fees (student_id,term,year,amount,paid,due_date,status) VALUES
            (1,'Term 1',2025,150000,100000,'2025-03-31','partial'),
            (1,'Term 2',2025,150000,0,'2025-07-31','unpaid')");

        // Announcements
        $pdo->exec("INSERT INTO announcements (title,content,audience,posted_by) VALUES
            ('Welcome to Term 1','Dear students, welcome back to school. Term 1 begins on 6th January 2025.','all',1),
            ('Staff Meeting','All teachers are requested to attend the staff meeting on Friday at 2PM.','teachers',1)");

        // Timetable
        $pdo->exec("INSERT INTO timetable (class_id,subject_id,teacher_id,day,start_time,end_time,room) VALUES
            (6,1,1,'Mon','08:00:00','09:30:00','Lab 1'),
            (6,2,1,'Mon','09:30:00','11:00:00','Lab 2'),
            (6,3,1,'Tue','08:00:00','09:30:00','Lab 1'),
            (6,4,1,'Wed','08:00:00','09:30:00','Lab 3'),
            (6,5,1,'Thu','08:00:00','09:30:00','Room 101'),
            (6,6,1,'Fri','08:00:00','09:30:00','Computer Lab')");
    }
}

// ============================================================
// 4. AUTH
// ============================================================
function auth_login(string $email, string $password): bool {
    $stmt = db()->prepare("SELECT * FROM users WHERE email=? AND status=1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && $user['password'] === $password) {
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        log_activity($user['id'], 'login');
        return true;
    }
    return false;
}

function auth_register(array $data): bool {
    $name = trim((string)($data['name'] ?? ''));
    $email = trim(strtolower((string)($data['email'] ?? '')));
    $role = in_array(($data['role'] ?? ''), ['student', 'parent', 'teacher'], true) ? ($data['role'] ?? 'student') : 'student';
    $password = (string)($data['password'] ?? '');
    $confirm = (string)($data['confirm_password'] ?? '');
    if ($name === '' || $email === '' || strlen($password) < 6 || $password !== $confirm) {
        return false;
    }

    $classId = isset($data['class_id']) && $data['class_id'] !== '' && $data['class_id'] !== null ? (int)$data['class_id'] : null;
    $departmentId = isset($data['department_id']) && $data['department_id'] !== '' && $data['department_id'] !== null ? (int)$data['department_id'] : null;
    $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' && $data['parent_id'] !== null ? (int)$data['parent_id'] : null;

    try {
        db()->beginTransaction();
        db()->prepare("INSERT INTO users (name,email,password,role,phone,status) VALUES (?,?,?,?,?,1)")
            ->execute([
                $name,
                $email,
                $password,
                $role,
                $data['phone'] ?? null,
            ]);
        $uid = db()->lastInsertId();

        if ($role === 'student') {
            $first = trim((string)($data['first_name'] ?? ''));
            $last = trim((string)($data['last_name'] ?? ''));
            db()->prepare("INSERT INTO students (user_id,admission_no,first_name,last_name,gender,dob,class_id,parent_id,address)
                VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $uid,
                    generate_admission_no(),
                    $first !== '' ? $first : 'Student',
                    $last !== '' ? $last : 'User',
                    $data['gender'] ?? 'M',
                    $data['dob'] ?? null,
                    $classId,
                    $parentId,
                    $data['address'] ?? '',
                ]);
        } elseif ($role === 'parent') {
            db()->prepare("INSERT INTO parents (user_id,first_name,last_name,phone,address,occupation)
                VALUES (?,?,?,?,?,?)")
                ->execute([
                    $uid,
                    trim((string)($data['first_name'] ?? '')) ?: 'Parent',
                    trim((string)($data['last_name'] ?? '')) ?: 'User',
                    $data['phone'] ?? '',
                    $data['address'] ?? '',
                    $data['occupation'] ?? '',
                ]);
        } elseif ($role === 'teacher') {
            db()->prepare("INSERT INTO teachers (user_id,employee_no,first_name,last_name,gender,qualification,department_id,phone,address)
                VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $uid,
                    'EMP' . str_pad((string)$uid, 4, '0', STR_PAD_LEFT),
                    trim((string)($data['first_name'] ?? '')) ?: 'Teacher',
                    trim((string)($data['last_name'] ?? '')) ?: 'User',
                    $data['gender'] ?? 'M',
                    $data['qualification'] ?? '',
                    $departmentId,
                    $data['phone'] ?? '',
                    $data['address'] ?? '',
                ]);
        }

        db()->commit();
        return true;
    } catch (Exception $e) {
        try { db()->rollBack(); } catch (Exception $ignored) {}
        return false;
    }
}

function auth_logout(): void {
    if (isset($_SESSION['user'])) log_activity($_SESSION['user']['id'], 'logout');
    session_destroy();
    header('Location: ' . BASE_URL . '?page=login');
    exit;
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '?page=login');
        exit;
    }
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_LIFETIME) {
        auth_logout();
    }
    $_SESSION['last_activity'] = time();
}

function require_role(...$roles): void {
    require_login();
    if (!in_array(current_user()['role'], $roles)) {
        http_response_code(403);
        die('Access denied.');
    }
}

function log_activity(?int $user_id, string $action): void {
    try {
        db()->prepare("INSERT INTO activity_log (user_id,action,ip) VALUES (?,?,?)")
            ->execute([$user_id, $action, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}
}

// ============================================================
// 5. HELPERS
// ============================================================
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function redirect(string $page): void {
    header('Location: ' . BASE_URL . '?page=' . $page);
    exit;
}

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function get_flash(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function grade_from_score($score, $max = 100): string {
    if ($max <= 0) return '-';
    $pct = ($score / $max) * 100;
    if ($pct >= 90) return 'A';
    if ($pct >= 80) return 'B';
    if ($pct >= 70) return 'C';
    if ($pct >= 60) return 'D';
    if ($pct >= 50) return 'E';
    return 'F';
}

function generate_receipt_no(): string {
    return 'RCP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function generate_admission_no(): string {
    return 'STU-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function money($v): string { return number_format((float)$v, 2); }

function paginate($total, $per_page = 20, $page = 1): array {
    $pages = max(1, (int)ceil($total / $per_page));
    $page = max(1, min($page, $pages));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $per_page, 'per_page' => $per_page];
}

function render_pagination($p): string {
    if ($p['pages'] <= 1) return '';
    $html = '<nav><ul class="pagination">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        $active = $i == $p['page'] ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="?page=' . e($_GET['page'] ?? '') . '&p=' . $i . '">' . $i . '</a></li>';
    }
    return $html . '</ul></nav>';
}

// ============================================================
// 6. ROUTER
// ============================================================
$page = $_GET['page'] ?? (is_logged_in() ? 'dashboard' : 'login');
$action = $_GET['action'] ?? '';

// Handle POST actions before rendering
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post($page, $action);
}

// ============================================================
// 7. POST HANDLERS
// ============================================================
function handle_post(string $page, string $action): void {
    // Login
    if ($page === 'login') {
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (auth_login($email, $pass)) {
            flash('Welcome back, ' . current_user()['name']);
            redirect('dashboard');
        } else {
            flash('Invalid email or password.', 'danger');
            redirect('login');
        }
    }

    // Register
    if ($page === 'register') {
        $role = in_array($_POST['role'] ?? '', ['student', 'parent', 'teacher'], true) ? $_POST['role'] : 'student';
        $payload = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'role' => $role,
            'phone' => trim($_POST['phone'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'gender' => $_POST['gender'] ?? 'M',
            'dob' => $_POST['dob'] ?? null,
            'class_id' => $_POST['class_id'] ?? null,
            'address' => trim($_POST['address'] ?? ''),
            'occupation' => trim($_POST['occupation'] ?? ''),
            'qualification' => trim($_POST['qualification'] ?? ''),
            'department_id' => $_POST['department_id'] ?? null,
        ];

        if (auth_register($payload)) {
            flash('Registration successful. Please sign in with your new account.');
            redirect('login');
        }

        flash('Registration failed. Please check all required fields and ensure your passwords match.', 'danger');
        redirect('register');
    }

    // Logout
    if ($page === 'logout') auth_logout();

    // Change password
    if ($page === 'change_password' && is_logged_in()) {
        $cur = $_POST['current'] ?? '';
        $new = $_POST['new'] ?? '';
        $conf = $_POST['confirm'] ?? '';
        $u = current_user();
        if ($u['password'] !== $cur) {
            flash('Current password is incorrect.', 'danger');
        } elseif ($new !== $conf) {
            flash('New passwords do not match.', 'danger');
        } elseif (strlen($new) < 6) {
            flash('Password must be at least 6 characters.', 'danger');
        } else {
            db()->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([$new, $u['id']]);
            flash('Password changed successfully.');
        }
        redirect('change_password');
    }

    // Admin: add student
    if ($page === 'admin_students' && $action === 'add') {
        require_role('admin');
        $data = [
            'name' => trim($_POST['first_name'] . ' ' . $_POST['last_name']),
            'email' => trim($_POST['email']),
            'password' => $_POST['password'] ?: DEFAULT_PASSWORD,
            'role' => 'student',
        ];
        try {
            db()->beginTransaction();
            db()->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")
                ->execute([$data['name'], $data['email'], $data['password'], $data['role']]);
            $uid = db()->lastInsertId();
            db()->prepare("INSERT INTO students (user_id,admission_no,first_name,last_name,gender,dob,class_id,address)
                VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$uid, generate_admission_no(), $_POST['first_name'], $_POST['last_name'],
                           $_POST['gender'] ?? 'M', $_POST['dob'] ?: null, $_POST['class_id'] ?: null, $_POST['address'] ?? '']);
            db()->commit();
            flash('Student added successfully.');
        } catch (Exception $e) {
            db()->rollBack();
            flash('Error: ' . $e->getMessage(), 'danger');
        }
        redirect('admin_students');
    }

    // Admin: add teacher
    if ($page === 'admin_teachers' && $action === 'add') {
        require_role('admin');
        try {
            db()->beginTransaction();
            $name = trim($_POST['first_name'] . ' ' . $_POST['last_name']);
            db()->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")
                ->execute([$name, $_POST['email'], $_POST['password'] ?: DEFAULT_PASSWORD, 'teacher']);
            $uid = db()->lastInsertId();
            db()->prepare("INSERT INTO teachers (user_id,employee_no,first_name,last_name,gender,qualification,department_id,phone,address)
                VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$uid, 'EMP' . str_pad($uid, 4, '0', STR_PAD_LEFT), $_POST['first_name'], $_POST['last_name'],
                           $_POST['gender'] ?? 'M', $_POST['qualification'] ?? '', $_POST['department_id'] ?: null,
                           $_POST['phone'] ?? '', $_POST['address'] ?? '']);
            db()->commit();
            flash('Teacher added successfully.');
        } catch (Exception $e) {
            db()->rollBack();
            flash('Error: ' . $e->getMessage(), 'danger');
        }
        redirect('admin_teachers');
    }

    // Admin: add class
    if ($page === 'admin_classes' && $action === 'add') {
        require_role('admin');
        db()->prepare("INSERT INTO classes (name,level,department_id,capacity) VALUES (?,?,?,?)")
            ->execute([$_POST['name'], $_POST['level'] ?? '', $_POST['department_id'] ?: null, $_POST['capacity'] ?? 40]);
        flash('Class added.');
        redirect('admin_classes');
    }

    // Admin: add subject
    if ($page === 'admin_subjects' && $action === 'add') {
        require_role('admin');
        db()->prepare("INSERT INTO subjects (name,code,department_id,teacher_id,class_id,credits) VALUES (?,?,?,?,?,?)")
            ->execute([$_POST['name'], $_POST['code'] ?? '', $_POST['department_id'] ?: null,
                       $_POST['teacher_id'] ?: null, $_POST['class_id'] ?: null, $_POST['credits'] ?? 1]);
        flash('Subject added.');
        redirect('admin_subjects');
    }

    // Teacher: take attendance
    if ($page === 'teacher_attendance' && $action === 'save') {
        require_role('teacher', 'admin');
        $class_id = (int)$_POST['class_id'];
        $date = $_POST['date'] ?? date('Y-m-d');
        $subject_id = $_POST['subject_id'] ?: null;
        $records = $_POST['status'] ?? [];
        $stmt = db()->prepare("INSERT INTO attendance (student_id,class_id,subject_id,date,status,recorded_by) VALUES (?,?,?,?,?,?)");
        foreach ($records as $sid => $status) {
            $stmt->execute([$sid, $class_id, $subject_id, $date, $status, current_user()['id']]);
        }
        flash('Attendance saved for ' . count($records) . ' students.');
        redirect('teacher_attendance');
    }

    // Teacher: enter marks
    if ($page === 'teacher_marks' && $action === 'save') {
        require_role('teacher', 'admin');
        $subject_id = (int)$_POST['subject_id'];
        $exam_id = (int)$_POST['exam_id'];
        $scores = $_POST['score'] ?? [];
        $max = (float)($_POST['max_score'] ?? 100);
        $stmt = db()->prepare("INSERT INTO marks (student_id,subject_id,exam_id,score,max_score,grade,entered_by) VALUES (?,?,?,?,?,?,?)");
        foreach ($scores as $sid => $score) {
            if ($score === '') continue;
            $grade = grade_from_score((float)$score, $max);
            $stmt->execute([$sid, $subject_id, $exam_id, (float)$score, $max, $grade, current_user()['id']]);
        }
        flash('Marks saved.');
        redirect('teacher_marks');
    }

    // Fees: add payment
    if ($page === 'admin_fees' && $action === 'pay') {
        require_role('admin');
        $fee_id = (int)$_POST['fee_id'];
        $amount = (float)$_POST['amount'];
        $fee = db()->query("SELECT * FROM fees WHERE id=$fee_id")->fetch();
        if ($fee) {
            db()->beginTransaction();
            db()->prepare("INSERT INTO payments (fee_id,student_id,amount,method,reference,receipt_no,recorded_by) VALUES (?,?,?,?,?,?,?)")
                ->execute([$fee_id, $fee['student_id'], $amount, $_POST['method'] ?? 'cash', $_POST['reference'] ?? '',
                           generate_receipt_no(), current_user()['id']]);
            $new_paid = $fee['paid'] + $amount;
            $status = $new_paid >= $fee['amount'] ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');
            db()->prepare("UPDATE fees SET paid=?, status=? WHERE id=?")->execute([$new_paid, $status, $fee_id]);
            db()->commit();
            flash('Payment recorded.');
        }
        redirect('admin_fees');
    }

    // Admin: announcement
    if ($page === 'admin_announcements' && $action === 'add') {
        require_role('admin');
        db()->prepare("INSERT INTO announcements (title,content,audience,posted_by) VALUES (?,?,?,?)")
            ->execute([$_POST['title'], $_POST['content'], $_POST['audience'] ?? 'all', current_user()['id']]);
        flash('Announcement posted.');
        redirect('admin_announcements');
    }

    // Admin: exam
    if ($page === 'admin_exams' && $action === 'add') {
        require_role('admin');
        db()->prepare("INSERT INTO exams (name,term,year,start_date,end_date,status) VALUES (?,?,?,?,?,?)")
            ->execute([$_POST['name'], $_POST['term'], $_POST['year'], $_POST['start_date'], $_POST['end_date'], $_POST['status'] ?? 'draft']);
        flash('Exam created.');
        redirect('admin_exams');
    }

    // Admin: department
    if ($page === 'admin_departments' && $action === 'add') {
        require_role('admin');
        db()->prepare("INSERT INTO departments (name,code,description) VALUES (?,?,?)")
            ->execute([$_POST['name'], $_POST['code'] ?? '', $_POST['description'] ?? '']);
        flash('Department added.');
        redirect('admin_departments');
    }

    // Timetable
    if ($page === 'admin_timetable' && $action === 'add') {
        require_role('admin');
        db()->prepare("INSERT INTO timetable (class_id,subject_id,teacher_id,day,start_time,end_time,room) VALUES (?,?,?,?,?,?,?)")
            ->execute([$_POST['class_id'], $_POST['subject_id'], $_POST['teacher_id'] ?: null, $_POST['day'],
                       $_POST['start_time'], $_POST['end_time'], $_POST['room'] ?? '']);
        flash('Timetable entry added.');
        redirect('admin_timetable');
    }

    // Delete handlers
    if ($action === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $table = $_GET['table'] ?? '';
        $allowed = ['students','teachers','classes','subjects','departments','announcements','exams','fees','timetable'];
        if (in_array($table, $allowed)) {
            require_role('admin');
            db()->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
            flash('Record deleted.');
        }
        redirect($_GET['return'] ?? 'dashboard');
    }
}

// ============================================================
// 8. PAGE RENDERERS
// ============================================================
function render_header(string $title = ''): void {
    $u = current_user();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — <?= e(SCHOOL_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root { --primary:#1a3a5c; --accent:#e8a020; }
body { background:#f4f6f9; font-family:'Segoe UI',sans-serif; }
.sidebar { background:var(--primary); color:#fff; min-height:100vh; padding:0; }
.sidebar a { color:rgba(255,255,255,.8); text-decoration:none; display:block; padding:.6rem 1rem; border-left:3px solid transparent; }
.sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,.1); color:#fff; border-left-color:var(--accent); }
.sidebar .brand { padding:1rem; font-weight:700; font-size:1.1rem; border-bottom:1px solid rgba(255,255,255,.15); }
.sidebar .section { padding:.5rem 1rem; font-size:.7rem; text-transform:uppercase; color:rgba(255,255,255,.4); margin-top:.5rem; }
.card { border:none; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.stat-card { border-left:4px solid var(--accent); }
.topbar { background:#fff; padding:.75rem 1.5rem; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.login-wrapper { min-height:100vh; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,var(--primary),#2c5f8a); }
.login-card { max-width:420px; width:100%; }
.table th { background:#f8f9fa; font-size:.85rem; text-transform:uppercase; letter-spacing:.5px; }
.badge-role { text-transform:capitalize; }
@media print { .no-print { display:none !important; } }
</style>
</head>
<body>
<?php
}

function render_footer(): void {
    ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); });
});
</script>
</body>
</html>
<?php
}

function render_sidebar(): void {
    $u = current_user();
    if (!$u) return;
    $page = $_GET['page'] ?? 'dashboard';
    $role = $u['role'];
    ?>
<div class="col-md-2 sidebar p-0">
    <div class="brand"><i class="fas fa-school me-2"></i><?= e(SCHOOL_NAME) ?></div>
    <nav>
        <a href="<?= BASE_URL ?>?page=dashboard" class="<?= $page=='dashboard'?'active':'' ?>"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
        <?php if ($role == 'admin'): ?>
            <div class="section">Management</div>
            <a href="?page=admin_students" class="<?= $page=='admin_students'?'active':'' ?>"><i class="fas fa-user-graduate me-2"></i>Students</a>
            <a href="?page=admin_teachers" class="<?= $page=='admin_teachers'?'active':'' ?>"><i class="fas fa-chalkboard-teacher me-2"></i>Teachers</a>
            <a href="?page=admin_parents" class="<?= $page=='admin_parents'?'active':'' ?>"><i class="fas fa-users me-2"></i>Parents</a>
            <a href="?page=admin_classes" class="<?= $page=='admin_classes'?'active':'' ?>"><i class="fas fa-layer-group me-2"></i>Classes</a>
            <a href="?page=admin_subjects" class="<?= $page=='admin_subjects'?'active':'' ?>"><i class="fas fa-book me-2"></i>Subjects</a>
            <a href="?page=admin_departments" class="<?= $page=='admin_departments'?'active':'' ?>"><i class="fas fa-building me-2"></i>Departments</a>
            <div class="section">Academics</div>
            <a href="?page=admin_exams" class="<?= $page=='admin_exams'?'active':'' ?>"><i class="fas fa-file-alt me-2"></i>Exams</a>
            <a href="?page=admin_marks" class="<?= $page=='admin_marks'?'active':'' ?>"><i class="fas fa-marker me-2"></i>Marks</a>
            <a href="?page=admin_report_cards" class="<?= $page=='admin_report_cards'?'active':'' ?>"><i class="fas fa-file-invoice me-2"></i>Report Cards</a>
            <a href="?page=admin_attendance" class="<?= $page=='admin_attendance'?'active':'' ?>"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a href="?page=admin_timetable" class="<?= $page=='admin_timetable'?'active':'' ?>"><i class="fas fa-clock me-2"></i>Timetable</a>
            <div class="section">Finance</div>
            <a href="?page=admin_fees" class="<?= $page=='admin_fees'?'active':'' ?>"><i class="fas fa-money-bill me-2"></i>Fees</a>
            <div class="section">Communication</div>
            <a href="?page=admin_announcements" class="<?= $page=='admin_announcements'?'active':'' ?>"><i class="fas fa-bullhorn me-2"></i>Announcements</a>
            <a href="?page=admin_reports" class="<?= $page=='admin_reports'?'active':'' ?>"><i class="fas fa-chart-bar me-2"></i>Reports</a>
            <div class="section">System</div>
            <a href="?page=admin_settings" class="<?= $page=='admin_settings'?'active':'' ?>"><i class="fas fa-cog me-2"></i>Settings</a>
        <?php elseif ($role == 'teacher'): ?>
            <div class="section">Teaching</div>
            <a href="?page=teacher_classes"><i class="fas fa-layer-group me-2"></i>My Classes</a>
            <a href="?page=teacher_subjects"><i class="fas fa-book me-2"></i>My Subjects</a>
            <a href="?page=teacher_students"><i class="fas fa-user-graduate me-2"></i>Students</a>
            <a href="?page=teacher_attendance" class="<?= $page=='teacher_attendance'?'active':'' ?>"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a href="?page=teacher_marks" class="<?= $page=='teacher_marks'?'active':'' ?>"><i class="fas fa-marker me-2"></i>Marks</a>
            <a href="?page=teacher_timetable"><i class="fas fa-clock me-2"></i>Timetable</a>
            <a href="?page=teacher_announcements"><i class="fas fa-bullhorn me-2"></i>Announcements</a>
        <?php elseif ($role == 'student'): ?>
            <div class="section">Academics</div>
            <a href="?page=student_subjects"><i class="fas fa-book me-2"></i>My Subjects</a>
            <a href="?page=student_marks"><i class="fas fa-marker me-2"></i>My Marks</a>
            <a href="?page=student_report_card"><i class="fas fa-file-invoice me-2"></i>Report Card</a>
            <a href="?page=student_attendance"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a href="?page=student_fees"><i class="fas fa-money-bill me-2"></i>Fees</a>
            <a href="?page=student_timetable"><i class="fas fa-clock me-2"></i>Timetable</a>
            <a href="?page=student_announcements"><i class="fas fa-bullhorn me-2"></i>Announcements</a>
        <?php elseif ($role == 'parent'): ?>
            <div class="section">My Children</div>
            <a href="?page=parent_children"><i class="fas fa-child me-2"></i>Children</a>
            <a href="?page=parent_marks"><i class="fas fa-marker me-2"></i>Marks</a>
            <a href="?page=parent_report_card"><i class="fas fa-file-invoice me-2"></i>Report Card</a>
            <a href="?page=parent_attendance"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a href="?page=parent_fees"><i class="fas fa-money-bill me-2"></i>Fees</a>
            <a href="?page=parent_payment_history"><i class="fas fa-history me-2"></i>Payment History</a>
            <a href="?page=parent_timetable"><i class="fas fa-clock me-2"></i>Timetable</a>
            <a href="?page=parent_announcements"><i class="fas fa-bullhorn me-2"></i>Announcements</a>
        <?php elseif ($role == 'headmaster'): ?>
            <div class="section">Overview</div>
            <a href="?page=headmaster_students"><i class="fas fa-user-graduate me-2"></i>Students</a>
            <a href="?page=headmaster_teachers"><i class="fas fa-chalkboard-teacher me-2"></i>Teachers</a>
            <a href="?page=headmaster_classes"><i class="fas fa-layer-group me-2"></i>Classes</a>
            <a href="?page=headmaster_attendance"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a href="?page=headmaster_marks"><i class="fas fa-marker me-2"></i>Marks</a>
            <a href="?page=headmaster_fees"><i class="fas fa-money-bill me-2"></i>Fees</a>
            <a href="?page=headmaster_reports"><i class="fas fa-chart-bar me-2"></i>Reports</a>
            <a href="?page=headmaster_announcements"><i class="fas fa-bullhorn me-2"></i>Announcements</a>
        <?php endif; ?>
        <div class="section">Account</div>
        <a href="?page=profile"><i class="fas fa-user me-2"></i>Profile</a>
        <a href="?page=change_password"><i class="fas fa-key me-2"></i>Change Password</a>
        <a href="?page=logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </nav>
</div>
<?php
}

function render_topbar(): void {
    $u = current_user();
    ?>
<div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><?= e(ucfirst(str_replace('_', ' ', $_GET['page'] ?? 'Dashboard'))) ?></h5>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted small"><?= date('D, d M Y') ?></span>
        <span class="badge bg-primary badge-role"><?= e($u['role']) ?></span>
        <span><?= e($u['name']) ?></span>
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($u['name']) ?>&background=1a3a5c&color=fff" width="32" height="32" class="rounded-circle">
    </div>
</div>
<?php
}

function render_layout(callable $content, string $title = ''): void {
    render_header($title);
    ?>
<div class="container-fluid">
    <div class="row">
        <?php render_sidebar(); ?>
        <div class="col-md-10 p-0">
            <?php render_topbar(); ?>
            <div class="p-4">
                <?php foreach (get_flash() as $f): ?>
                    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show">
                        <?= e($f['msg']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
                <?php $content(); ?>
            </div>
        </div>
    </div>
</div>
<?php
    render_footer();
}

// ============================================================
// 9. PAGE LOGIC
// ============================================================
if ($page === 'login') {
    if (is_logged_in()) redirect('dashboard');
    render_header('Login');
    ?>
<div class="login-wrapper">
    <div class="login-card">
        <div class="card shadow-lg">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-school fa-3x text-primary"></i>
                    <h4 class="mt-2"><?= e(SCHOOL_NAME) ?></h4>
                    <p class="text-muted small"><?= e(SCHOOL_MOTTO) ?></p>
                </div>
                <?php foreach (get_flash() as $f): ?>
                    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
                <?php endforeach; ?>
                <form method="post" action="?page=login">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required autofocus value="admin@gsremera.rw">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required value="password">
                    </div>
                    <button class="btn btn-primary w-100">Sign In</button>
                </form>
                <div class="d-flex justify-content-between mt-3 small">
                    <a href="?page=register">Create account</a>
                    <a href="?page=forgot_password">Forgot password?</a>
                </div>
                <hr>
                <div class="small text-muted">
                    <strong>Demo accounts (password: password)</strong><br>
                    admin@gsremera.rw · headmaster@gsremera.rw<br>
                    teacher@gsremera.rw · student@gsremera.rw · parent@gsremera.rw
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    render_footer();
    exit;
}

if ($page === 'register') {
    if (is_logged_in()) redirect('dashboard');
    $classes = db()->query("SELECT id,name FROM classes ORDER BY name")->fetchAll();
    $departments = db()->query("SELECT id,name FROM departments ORDER BY name")->fetchAll();
    render_header('Register');
    ?>
<div class="login-wrapper">
    <div class="login-card" style="max-width: 620px;">
        <div class="card shadow-lg">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-user-plus fa-3x text-primary"></i>
                    <h4 class="mt-2">Create an Account</h4>
                    <p class="text-muted small">Register as a student, parent, or teacher.</p>
                </div>
                <?php foreach (get_flash() as $f): ?>
                    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
                <?php endforeach; ?>
                <form method="post" action="?page=register">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="student">Student</option>
                                <option value="parent">Parent</option>
                                <option value="teacher">Teacher</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Class</label>
                            <select name="class_id" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Qualification</label>
                            <input type="text" name="qualification" class="form-control" placeholder="For teachers">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Occupation</label>
                            <input type="text" name="occupation" class="form-control" placeholder="For parents">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 mt-3">Create Account</button>
                </form>
                <div class="text-center mt-3">
                    <a href="?page=login" class="small">Already have an account? Sign in</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    render_footer();
    exit;
}

if ($page === 'logout') auth_logout();

if ($page === 'forgot_password') {
    render_header('Forgot Password');
    ?>
<div class="login-wrapper">
    <div class="login-card card shadow-lg">
        <div class="card-body p-4">
            <h4>Reset Password</h4>
            <p class="text-muted small">Enter your email and we'll send reset instructions (demo: no email sent).</p>
            <form method="post" action="?page=forgot_password">
                <div class="mb-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
                <button class="btn btn-primary w-100">Send Reset Link</button>
            </form>
            <div class="text-center mt-3"><a href="?page=login">Back to login</a></div>
        </div>
    </div>
</div>
<?php
    render_footer();
    exit;
}

// All pages below require login
require_login();

// Dashboard
if ($page === 'dashboard') {
    render_layout(function () {
        $u = current_user();
        $role = $u['role'];
        $stats = [];
        if (in_array($role, ['admin', 'headmaster'])) {
            $stats = [
                ['Students', db()->query("SELECT COUNT(*) FROM students")->fetchColumn(), 'user-graduate', 'primary'],
                ['Teachers', db()->query("SELECT COUNT(*) FROM teachers")->fetchColumn(), 'chalkboard-teacher', 'success'],
                ['Classes', db()->query("SELECT COUNT(*) FROM classes")->fetchColumn(), 'layer-group', 'info'],
                ['Subjects', db()->query("SELECT COUNT(*) FROM subjects")->fetchColumn(), 'book', 'warning'],
                ['Fees Collected', money(db()->query("SELECT COALESCE(SUM(paid),0) FROM fees")->fetchColumn()), 'money-bill', 'success'],
                ['Outstanding', money(db()->query("SELECT COALESCE(SUM(amount-paid),0) FROM fees")->fetchColumn()), 'exclamation-circle', 'danger'],
            ];
        } elseif ($role == 'teacher') {
            $tid = db()->query("SELECT id FROM teachers WHERE user_id={$u['id']}")->fetchColumn();
            $stats = [
                ['My Subjects', db()->query("SELECT COUNT(*) FROM subjects WHERE teacher_id=" . (int)$tid)->fetchColumn(), 'book', 'primary'],
                ['My Students', db()->query("SELECT COUNT(DISTINCT student_id) FROM attendance WHERE recorded_by={$u['id']}")->fetchColumn(), 'user-graduate', 'success'],
                ['Announcements', db()->query("SELECT COUNT(*) FROM announcements WHERE audience IN ('all','teachers')")->fetchColumn(), 'bullhorn', 'info'],
            ];
        } elseif ($role == 'student') {
            $sid = db()->query("SELECT id FROM students WHERE user_id={$u['id']}")->fetchColumn();
            $stats = [
                ['Subjects', db()->query("SELECT COUNT(*) FROM subjects WHERE class_id=(SELECT class_id FROM students WHERE id=" . (int)$sid . ")")->fetchColumn(), 'book', 'primary'],
                ['Average', number_format((float)db()->query("SELECT COALESCE(AVG(score),0) FROM marks WHERE student_id=" . (int)$sid)->fetchColumn(), 1), 'chart-line', 'success'],
                ['Attendance', db()->query("SELECT COUNT(*) FROM attendance WHERE student_id=" . (int)$sid . " AND status='present'")->fetchColumn(), 'calendar-check', 'info'],
            ];
        } elseif ($role == 'parent') {
            $pid = db()->query("SELECT id FROM parents WHERE user_id={$u['id']}")->fetchColumn();
            $stats = [
                ['Children', db()->query("SELECT COUNT(*) FROM students WHERE parent_id=" . (int)$pid)->fetchColumn(), 'child', 'primary'],
                ['Fees Due', money(db()->query("SELECT COALESCE(SUM(amount-paid),0) FROM fees WHERE student_id IN (SELECT id FROM students WHERE parent_id=" . (int)$pid . ")")->fetchColumn()), 'money-bill', 'danger'],
            ];
        }
        ?>
        <div class="row g-3 mb-4">
            <?php foreach ($stats as $s): ?>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted small text-uppercase"><?= e($s[0]) ?></div>
                            <h3 class="mb-0"><?= e($s[1]) ?></h3>
                        </div>
                        <i class="fas fa-<?= e($s[2]) ?> fa-2x text-<?= e($s[3]) ?> opacity-50"></i>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <div class="col-md-8">
                <div class="card p-3">
                    <h6>Recent Announcements</h6>
                    <table class="table table-sm">
                        <thead><tr><th>Title</th><th>Audience</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach (db()->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5") as $a): ?>
                            <tr><td><?= e($a['title']) ?></td><td><span class="badge bg-secondary"><?= e($a['audience']) ?></span></td><td><?= e($a['created_at']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3">
                    <h6>Quick Links</h6>
                    <div class="d-grid gap-2">
                        <?php if (in_array($role, ['admin','headmaster'])): ?>
                            <a href="?page=admin_students&action=add_form" class="btn btn-outline-primary btn-sm">Add Student</a>
                            <a href="?page=admin_announcements&action=add_form" class="btn btn-outline-primary btn-sm">Post Announcement</a>
                        <?php elseif ($role == 'teacher'): ?>
                            <a href="?page=teacher_attendance" class="btn btn-outline-primary btn-sm">Take Attendance</a>
                            <a href="?page=teacher_marks" class="btn btn-outline-primary btn-sm">Enter Marks</a>
                        <?php endif; ?>
                        <a href="?page=profile" class="btn btn-outline-secondary btn-sm">My Profile</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }, 'Dashboard');
    exit;
}

// Profile
if ($page === 'profile') {
    $u = current_user();
    render_layout(function () use ($u) {
        ?>
        <div class="card p-4" style="max-width:600px">
            <h5>My Profile</h5>
            <table class="table">
                <tr><th>Name</th><td><?= e($u['name']) ?></td></tr>
                <tr><th>Email</th><td><?= e($u['email']) ?></td></tr>
                <tr><th>Role</th><td><span class="badge bg-primary"><?= e($u['role']) ?></span></td></tr>
                <tr><th>Phone</th><td><?= e($u['phone'] ?? '-') ?></td></tr>
                <tr><th>Joined</th><td><?= e($u['created_at']) ?></td></tr>
            </table>
            <a href="?page=change_password" class="btn btn-outline-primary">Change Password</a>
        </div>
        <?php
    }, 'Profile');
    exit;
}

// Change password
if ($page === 'change_password') {
    render_layout(function () {
        ?>
        <div class="card p-4" style="max-width:500px">
            <h5>Change Password</h5>
            <form method="post" action="?page=change_password">
                <div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" name="confirm" class="form-control" required></div>
                <button class="btn btn-primary">Update Password</button>
            </form>
        </div>
        <?php
    }, 'Change Password');
    exit;
}

// ============================================================
// ADMIN PAGES
// ============================================================
if ($page === 'admin_students') {
    require_role('admin');
    if ($action === 'add_form') {
        render_layout(function () {
            $classes = db()->query("SELECT id,name FROM classes")->fetchAll();
            ?>
            <div class="card p-4">
                <h5>Add Student</h5>
                <form method="post" action="?page=admin_students&action=add">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">First Name</label><input name="first_name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Last Name</label><input name="last_name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Password</label><input type="text" name="password" class="form-control" placeholder="Leave blank for default"></div>
                        <div class="col-md-4"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="M">Male</option><option value="F">Female</option></select></div>
                        <div class="col-md-4"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Class</label><select name="class_id" class="form-select"><option value="">—</option><?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control"></textarea></div>
                    </div>
                    <button class="btn btn-primary mt-3">Save Student</button>
                    <a href="?page=admin_students" class="btn btn-secondary mt-3">Cancel</a>
                </form>
            </div>
            <?php
        }, 'Add Student');
        exit;
    }

    render_layout(function () {
        $p = paginate(db()->query("SELECT COUNT(*) FROM students")->fetchColumn(), 20, $_GET['p'] ?? 1);
        $rows = db()->query("SELECT s.*, c.name class_name, u.email FROM students s
            LEFT JOIN classes c ON c.id=s.class_id
            LEFT JOIN users u ON u.id=s.user_id
            ORDER BY s.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}")->fetchAll();
        ?>
        <div class="card p-3">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0">Students (<?= $p['pages'] * 0 + count($rows) ?>)</h5>
                <a href="?page=admin_students&action=add_form" class="btn btn-primary btn-sm">+ Add Student</a>
            </div>
            <table class="table table-hover">
                <thead><tr><th>Admission No</th><th>Name</th><th>Class</th><th>Gender</th><th>Email</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['admission_no']) ?></td>
                        <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                        <td><?= e($r['class_name'] ?? '-') ?></td>
                        <td><?= e($r['gender']) ?></td>
                        <td><?= e($r['email']) ?></td>
                        <td>
                            <a href="?page=admin_students&action=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                            <a href="?page=admin_students&action=edit_form&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                            <a href="?page=admin_students&action=delete&id=<?= $r['id'] ?>&table=students&return=admin_students" class="btn btn-sm btn-outline-danger" data-confirm="Delete this student?"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?= render_pagination($p) ?>
        </div>
        <?php
    }, 'Students');
    exit;
}

if ($page === 'admin_teachers') {
    require_role('admin');
    if ($action === 'add_form') {
        render_layout(function () {
            $depts = db()->query("SELECT id,name FROM departments")->fetchAll();
            ?>
            <div class="card p-4">
                <h5>Add Teacher</h5>
                <form method="post" action="?page=admin_teachers&action=add">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">First Name</label><input name="first_name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Last Name</label><input name="last_name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Password</label><input type="text" name="password" class="form-control" placeholder="Leave blank for default"></div>
                        <div class="col-md-4"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="M">Male</option><option value="F">Female</option></select></div>
                        <div class="col-md-4"><label class="form-label">Qualification</label><input name="qualification" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">—</option><?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Address</label><input name="address" class="form-control"></div>
                    </div>
                    <button class="btn btn-primary mt-3">Save Teacher</button>
                    <a href="?page=admin_teachers" class="btn btn-secondary mt-3">Cancel</a>
                </form>
            </div>
            <?php
        }, 'Add Teacher');
        exit;
    }

    render_layout(function () {
        $rows = db()->query("SELECT t.*, d.name dept_name, u.email FROM teachers t
            LEFT JOIN departments d ON d.id=t.department_id
            LEFT JOIN users u ON u.id=t.user_id ORDER BY t.id DESC")->fetchAll();
        ?>
        <div class="card p-3">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0">Teachers (<?= count($rows) ?>)</h5>
                <a href="?page=admin_teachers&action=add_form" class="btn btn-primary btn-sm">+ Add Teacher</a>
            </div>
            <table class="table table-hover">
                <thead><tr><th>Employee No</th><th>Name</th><th>Department</th><th>Phone</th><th>Email</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['employee_no']) ?></td>
                        <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                        <td><?= e($r['dept_name'] ?? '-') ?></td>
                        <td><?= e($r['phone']) ?></td>
                        <td><?= e($r['email']) ?></td>
                        <td>
                            <a href="?page=admin_teachers&action=view&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                            <a href="?page=admin_teachers&action=delete&id=<?= $r['id'] ?>&table=teachers&return=admin_teachers" class="btn btn-sm btn-outline-danger" data-confirm="Delete this teacher?"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }, 'Teachers');
    exit;
}

if ($page === 'admin_classes') {
    require_role('admin');
    if ($action === 'add_form') {
        render_layout(function () {
            $depts = db()->query("SELECT id,name FROM departments")->fetchAll();
            ?>
            <div class="card p-4" style="max-width:600px">
                <h5>Add Class</h5>
                <form method="post" action="?page=admin_classes&action=add">
                    <div class="mb-3"><label class="form-label">Class Name</label><input name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Level</label><input name="level" class="form-control" placeholder="e.g. S1"></div>
                    <div class="mb-3"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">—</option><?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Capacity</label><input type="number" name="capacity" class="form-control" value="40"></div>
                    <button class="btn btn-primary">Save</button>
                    <a href="?page=admin_classes" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
            <?php
        }, 'Add Class');
        exit;
    }
    render_layout(function () {
        $rows = db()->query("SELECT c.*, d.name dept FROM classes c LEFT JOIN departments d ON d.id=c.department_id ORDER BY c.id")->fetchAll();
        ?>
        <div class="card p-3">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0">Classes (<?= count($rows) ?>)</h5>
                <a href="?page=admin_classes&action=add_form" class="btn btn-primary btn-sm">+ Add Class</a>
            </div>
            <table class="table table-hover">
                <thead><tr><th>Name</th><th>Level</th><th>Department</th><th>Capacity</th><th>Students</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['name']) ?></td>
                        <td><?= e($r['level']) ?></td>
                        <td><?= e($r['dept'] ?? '-') ?></td>
                        <td><?= e($r['capacity']) ?></td>
                        <td><?= db()->query("SELECT COUNT(*) FROM students WHERE class_id=" . (int)$r['id'])->fetchColumn() ?></td>
                        <td><a href="?page=admin_classes&action=delete&id=<?= $r['id'] ?>&table=classes&return=admin_classes" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="fas fa-trash"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }, 'Classes');
    exit;
}

if ($page === 'admin_subjects') {
    require_role('admin');
    if ($action === 'add_form') {
        render_layout(function () {
            $depts = db()->query("SELECT id,name FROM departments")->fetchAll();
            $teachers = db()->query("SELECT id,first_name,last_name FROM teachers")->fetchAll();
            $classes = db()->query("SELECT id,name FROM classes")->fetchAll();
            ?>
            <div class="card p-4" style="max-width:700px">
                <h5>Add Subject</h5>
                <form method="post" action="?page=admin_subjects&action=add">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Code</label><input name="code" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">—</option><?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Teacher</label><select name="teacher_id" class="form-select"><option value="">—</option><?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['first_name'].' '.$t['last_name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Class</label><select name="class_id" class="form-select"><option value="">—</option><?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Credits</label><input type="number" name="credits" class="form-control" value="1"></div>
                    </div>
                    <button class="btn btn-primary mt-3">Save</button>
                    <a href="?page=admin_subjects" class="btn btn-secondary mt-3">Cancel</a>
                </form>
            </div>
            <?php
        }, 'Add Subject');
        exit;
    }
    render_layout(function () {
        $rows = db()->query("SELECT s.*, d.name dept, c.name class_name, t.first_name, t.last_name FROM subjects s
            LEFT JOIN departments d ON d.id=s.department_id
            LEFT JOIN classes c ON c.id=s.class_id
            LEFT JOIN teachers t ON t.id=s.teacher_id ORDER BY s.id")->fetchAll();
        ?>
        <div class="card p-3">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0">Subjects (<?= count($rows) ?>)</h5>
                <a href="?page=admin_subjects&action=add_form" class="btn btn-primary btn-sm">+ Add Subject</a>
            </div>
            <table class="table table-hover">
                <thead><tr><th>Code</th><th>Name</th><th>Department</th><th>Teacher</th><th>Class</th><th>Credits</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['code']) ?></td>
                        <td><?= e($r['name']) ?></td>
                        <td><?= e($r['dept'] ?? '-') ?></td>
                        <td><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?></td>
                        <td><?= e($r['class_name'] ?? '-') ?></td>
                        <td><?= e($r['credits']) ?></td>
                        <td><a href="?page=admin_subjects&action=delete&id=<?= $r['id'] ?>&table=subjects&return=admin_subjects" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="fas fa-trash"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }, 'Subjects');
    exit;
}

if ($page === 'admin_departments') {
    require_role('admin');
    if ($action === 'add_form') {
        render_layout(function () {
            ?>
            <div class="card p-4" style="max-width:500px">
                <h5>Add Department</h5>
                <form method="post" action="?page=admin_departments&action=add">
                    <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Code</label><input name="code" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control"></textarea></div>
                    <button class="btn btn-primary">Save</button>
                    <a href="?page=admin_departments" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
            <?php
        }, 'Add Department');
        exit;
    }
    render_layout(function () {
        $rows = db()->query("SELECT * FROM departments ORDER BY id")->fetchAll();
        ?>
        <div class="card p-3">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0">Departments (<?= count($rows) ?>)</h5>
                <a href="?page=admin_departments&action=add_form" class="btn btn-primary btn-sm">+ Add Department</a>
            </div>
            <table class="table">
                <thead><tr><th>Code</th><th>Name</th><th>Description</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr><td><?= e($r['code']) ?></td><td><?= e($r['name']) ?></td><td><?= e($r['description']) ?></td>
                    <td><a href="?page=admin_departments&action=delete&id=<?= $r['id'] ?>&table=departments&return=admin_departments" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="fas fa-trash"></i></a></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }, 'Departments');
    exit;
}

if ($page === 'admin_exams') {
    require_role('admin');
    if ($action === 'add_form') {
        render_layout(function () {
            ?>
            <div class="card p-4" style="max-width:600px">
                <h5>Add Exam</h5>
                <form method="post" action="?page=admin_exams&action=add">
                    <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Term</label><input name="term" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Year</label><input type="number" name="year" class="form-control" value="<?= date('Y') ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="draft">Draft</option><option value="active">Active</option><option value="completed">Completed</option></select></div>
                        <div class="col-md-6"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control"></div>
                    </div>
                    <button class="btn btn-primary mt-3">Save</button>
                    <a href="?page=admin_exams" class="btn btn-secondary mt-3">Cancel</a>
                </form>
            </div>
            <?php
        }, 'Add Exam');
        exit;
    }
    render_layout(function () {
        $rows = db()->query("SELECT * FROM exams ORDER BY id DESC")->fetchAll();
        ?>
        <div class="card p-3">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0">Exams (<?= count($rows) ?>)</h5>
                <a href="?page=admin_exams&action=add_form" class="btn btn-primary btn-sm">+ Add Exam</a>
            </div>
            <table class="table">
                <thead><tr><th>Name</th><th>Term</th><th>Year</th><th>Dates</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr><td><?= e($r['name']) ?></td><td><?= e($r['term']) ?></td><td><?= e($r['year']) ?></td>
                    <td><?= e($r['start_date']) ?> → <?= e($r['end_date']) ?></td>
                    <td><span class="badge bg-<?= $r['status']=='active'?'success':($r['status']=='completed'?'secondary':'warning') ?>"><?= e($r['status']) ?></span></td>
                    <td><a href="?page=admin_exams&action=delete&id=<?= $r['id'] ?>&table=exams&return=admin_exams" class="btn btn-sm btn-outline-danger" data-confirm="Delete?"><i class="fas fa-trash"></i></a></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }, 'Exams');
    exit;
}

if ($page === 'admin_marks' || $page === 'admin_report_cards' || $page === 'admin_attendance' || $page === 'admin_timetable') {
    require_role('admin');
    $simple = [
        'admin_marks' => ['Marks', "SELECT m.*, s.first_name, s.last_name, sub.name subject, e.name exam FROM marks m LEFT JOIN students s ON s.id=m.student_id LEFT JOIN subjects sub ON sub.id=m.subject_id LEFT JOIN exams e ON e.id=m.exam_id ORDER BY m.id DESC LIMIT 100", ['Student','Subject','Exam','Score','Grade']],
        'admin_report_cards' => ['Report Cards', "SELECT s.id, s.first_name, s.last_name, c.name class FROM students s LEFT JOIN classes c ON c.id=s.class_id ORDER BY s.first_name", ['Student','Class','Action']],
        'admin_attendance' => ['Attendance', "SELECT a.*, s.first_name, s.last_name, c.name class FROM attendance a LEFT JOIN students s ON s.id=a.student_id LEFT JOIN classes c ON c.id=a.class_id ORDER BY a.date DESC LIMIT 100", ['Date','Student','Class','Status']],
        'admin_timetable' => ['Timetable', "SELECT t.*, c.name class, sub.name subject, tc.first_name, tc.last_name FROM timetable t LEFT JOIN classes c ON c.id=t.class_id LEFT JOIN subjects sub ON sub.id=t.subject_id LEFT JOIN teachers tc ON tc.id=t.teacher_id ORDER BY t.day, t.start_time", ['Day','Time','Class','Subject','Teacher','Room']],
    ];
    if ($page === 'admin_timetable' && $action === 'add_form') {
        render_layout(function () {
            $classes = db()->query("SELECT id,name FROM classes")->fetchAll();
            $subjects = db()->query("SELECT id,name FROM subjects")->fetchAll();
            $teachers = db()->query("SELECT id,first_name,last_name FROM teachers")->fetchAll();
            ?>
            <div class="card p-4" style="max-width:700px">
                <h5>Add Timetable Entry</h5>
                <form method="post" action="?page=admin_timetable&action=add">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Class</label><select name="class_id" class="form-select" required><option value="">—</option><?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Subject</label><select name="subject_id" class="form-select" required><option value="">—</option><?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Teacher</label><select name="teacher_id" class="form-select"><option value="">—</option><?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['first_name'].' '.$t['last_name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label class="form-label">Day</label><select name="day" class="form-select"><option>Mon</option><option>Tue</option><option>Wed</option><option>Thu</option><option>Fri</option></select></div>
                        <div class="col-md-3"><label class="form-label">Start</label><input type="time" name="start_time" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label">End</label><input type="time" name="end_time" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label">Room</label><input name="room" class="form-control"></div>
                    </div>
                    <button class="btn btn-primary mt-3">Save</button>
                    <a href="?page=admin_timetable" class="btn btn-secondary mt-3">Cancel</a>
                </form>
            </div>
            <?php
        }, 'Add Timetable Entry');
        exit;
    }
    [$title, $sql, $headers] = $simple[$page];
    render_layout(function () use ($title, $sql, $headers, $page) {
        $rows = db()->query($sql)->fetchAll();
        ?>
        <div class="card p-3">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0"><?= e($title) ?></h5>
                <?php if ($page === 'admin_timetable'): ?><a href="?page=admin_timetable&action=add_form" class="btn btn-primary btn-sm">+ Add Entry</a><?php endif; ?>
                <?php if ($page === 'admin_report_cards'): ?><a href="?page=admin_report_cards&action=generate" class="btn btn-primary btn-sm">Generate Report Cards</a><?php endif; ?>
            </div>
            <table class="table table-sm table-hover">
                <thead><tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr><?php foreach ($r as $k => $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }, $title);
    exit;
}

if ($page === 'admin_fees') {
    require_role('admin');
    if ($action === 'pay_form') {
        render_layout(function () {
            $fees = db()->query("SELECT f.*, s.first_name, s.last_name, s.admission_no FROM fees f LEFT JOIN students s ON s.id=f.student_id WHERE f.status != 'paid' ORDER BY f.id DESC")->fetchAll();
            ?>
            <div class="card p-4" style="max-width:600px">
                <h5>Record Payment</h5>
                <form method="post" action="?page=admin_fees&action=pay">
                    <div class="mb-3"><label class="form-label">Fee Record</label>
                        <select name="fee_id" class="form-select" required>
                            <?php foreach ($fees as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= e($f['admission_no'] . ' ' . $f['first_name'] . ' ' . $f['last_name']) ?> — Balance: <?= money($f['amount'] - $f['paid']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Method</label><select name="method" class="form-select"><option value="cash">Cash</option><option value="bank">Bank</option><option value="mobile">Mobile Money</option><option value="cheque">Cheque</option></select></div>
                    <div class="mb-3"><label class="form-label">Reference</label><input name="reference" class="form-control"></div>
                    <button class="btn btn-primary">Record Payment</button>
                    <a href="?page=admin_fees" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
            <?php
        }, 'Record Payment');
        exit;
    }
    render_layout(function () {
        $rows = db()->query("SELECT f.*, s.first_name, s.last_name, s.admission_no FROM fees f LEFT JOIN students s ON s.id=f.student_id ORDER BY f.id DESC")->fetchAll();
        $total = db()->query("SELECT COALESCE(SUM(paid),0) FROM fees")->fetchColumn();
        $outstanding = db()->query("SELECT COALESCE(SUM(amount-paid),0) FROM fees")->fetchColumn();
        ?>
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="card stat-card p-3"><div class="text-muted small">Total Collected</div><h4><?= money($total) ?> RWF</h4></div></div>
            <div class="col-md-4"><div class="card stat-card p-3"><div class="text-muted small">Outstanding</div><h4 class="text-danger"><?= money($outstanding) ?> RWF</h4></div></div>
            <div class="col-md-4"><div class="card stat-card p-3"><div class="text-muted small">Records</div><h4><?= count($rows) ?></h4></div></div>
        </div>
        <div class="card p-3">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0">Fee Records</h5>
                <a href="?page=admin_fees&action=pay_form" class="btn btn-primary btn-sm">+ Record Payment</a>
            </div>
            <table class="table table-hover">
                <thead><tr><th>Student</th><th>Term</th><th>Year</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['admission_no'] . ' ' . $r['first_name'] . ' ' . $r['last_name']) ?></td>
                        <td><?= e($r['term']) ?></td><td><?= e($r['year']) ?></td>
                        <td><?= money($r['amount']) ?></td><td><?= money($r['paid']) ?></td>
                        <td><?= money($r['amount'] - $r['paid']) ?></td>
                        <td><span class="badge bg-<?= $r['status']=='paid'?'success':($r['status']=='partial'?'warning':'danger') ?>"><?= e($r['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }, 'Fees');
    exit;
}

if ($page === 'admin_announcements') {
    require_role('admin');
    if ($action === 'add_form') {
        render_layout(function () {
            ?>
            <div class="card p-4" style="max-width:600px">
                <h5>Post Announcement</h5>
                <form method="post" action="?page=admin_announcements&action=add">
                    <div class="mb-3"><label class="form-label">Title</label><input name="title" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Content</label><textarea name="content" class="form-control" rows="5" required></textarea></div>
                    <div class="mb-3"><label class="form-label">Audience</label><select name="audience" class="form-select"><option value="all">All</option><option value="students">Students</option><option value="teachers">Teachers</option><option value="parents">Parents</option><option value="staff">Staff</option></select></div>
                    <button class="btn btn-primary">Post</button>
                    <a href="?page=admin_announcements" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
            <?php
        }, 'Post Announcement');
        exit;
    }
    render_layout(function () {
        $rows = db()->query("SELECT a.*, u.name author FROM announcements a LEFT JOIN users u ON u.id=a.posted_by ORDER BY a.id DESC")->fetchAll();
        ?>
        <div class="card p-3">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0">Announcements</h5>
                <a href="?page=admin_announcements&action=add_form" class="btn btn-primary btn-sm">+ Post</a>
            </div>
            <?php foreach ($rows as $r): ?>
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <h6><?= e($r['title']) ?></h6>
                        <small class="text-muted"><?= e($r['created_at']) ?></small>
                    </div>
                    <p class="mb-1"><?= nl2br(e($r['content'])) ?></p>
                    <small class="text-muted">By <?= e($r['author'] ?? 'System') ?> · Audience: <span class="badge bg-secondary"><?= e($r['audience']) ?></span></small>
                    <a href="?page=admin_announcements&action=delete&id=<?= $r['id'] ?>&table=announcements&return=admin_announcements" class="btn btn-sm btn-outline-danger float-end" data-confirm="Delete?"><i class="fas fa-trash"></i></a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }, 'Announcements');
    exit;
}

if ($page === 'admin_reports' || $page === 'admin_settings') {
    require_role('admin');
    render_layout(function () use ($page) {
        if ($page === 'admin_reports') {
            $data = [
                'Students per Class' => db()->query("SELECT c.name, COUNT(s.id) total FROM classes c LEFT JOIN students s ON s.class_id=c.id GROUP BY c.id ORDER BY c.name")->fetchAll(),
                'Teachers per Department' => db()->query("SELECT d.name, COUNT(t.id) total FROM departments d LEFT JOIN teachers t ON t.department_id=d.id GROUP BY d.id")->fetchAll(),
                'Fee Collection' => db()->query("SELECT status, COUNT(*) total, SUM(paid) collected FROM fees GROUP BY status")->fetchAll(),
            ];
            foreach ($data as $title => $rows): ?>
                <div class="card p-3 mb-3">
                    <h6><?= e($title) ?></h6>
                    <table class="table table-sm">
                        <?php foreach ($rows as $r): ?>
                            <tr><?php foreach ($r as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endforeach;
        } else {
            ?>
            <div class="card p-4" style="max-width:600px">
                <h5>System Settings</h5>
                <table class="table">
                    <tr><th>School Name</th><td><?= e(SCHOOL_NAME) ?></td></tr>
                    <tr><th>Motto</th><td><?= e(SCHOOL_MOTTO) ?></td></tr>
                    <tr><th>Database</th><td><?= e(DB_NAME) ?></td></tr>
                    <tr><th>Timezone</th><td><?= date_default_timezone_get() ?></td></tr>
                    <tr><th>PHP Version</th><td><?= PHP_VERSION ?></td></tr>
                </table>
                <p class="text-muted small">To change settings, edit the CONFIG section at the top of this file.</p>
            </div>
            <?php
        }
    }, ucfirst(str_replace('_', ' ', $page)));
    exit;
}

// ============================================================
// TEACHER PAGES
// ============================================================
if (str_starts_with($page, 'teacher_')) {
    require_role('teacher', 'admin');
    $u = current_user();
    $tid = db()->query("SELECT id FROM teachers WHERE user_id={$u['id']}")->fetchColumn();

    if ($page === 'teacher_attendance') {
        render_layout(function () use ($tid) {
            $classes = db()->query("SELECT DISTINCT c.* FROM classes c JOIN subjects s ON s.class_id=c.id WHERE s.teacher_id=" . (int)$tid)->fetchAll();
            $class_id = $_GET['class_id'] ?? ($classes[0]['id'] ?? 0);
            $students = $class_id ? db()->query("SELECT * FROM students WHERE class_id=" . (int)$class_id . " ORDER BY first_name")->fetchAll() : [];
            $subjects = $class_id ? db()->query("SELECT * FROM subjects WHERE class_id=" . (int)$class_id)->fetchAll() : [];
            ?>
            <div class="card p-3 mb-3">
                <form method="get" class="row g-2 align-items-end">
                    <input type="hidden" name="page" value="teacher_attendance">
                    <div class="col-md-4">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $c['id']==$class_id?'selected':'' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <?php if ($students): ?>
            <div class="card p-3">
                <h5>Take Attendance — <?= e(date('Y-m-d')) ?></h5>
                <form method="post" action="?page=teacher_attendance&action=save">
                    <input type="hidden" name="class_id" value="<?= (int)$class_id ?>">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Subject</label>
                            <select name="subject_id" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <table class="table table-sm">
                        <thead><tr><th>#</th><th>Student</th><th>Present</th><th>Absent</th><th>Late</th><th>Excused</th></tr></thead>
                        <tbody>
                        <?php foreach ($students as $i => $s): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                <?php foreach (['present','absent','late','excused'] as $st): ?>
                                    <td><input type="radio" name="status[<?= $s['id'] ?>]" value="<?= $st ?>" <?= $st=='present'?'checked':'' ?>></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button class="btn btn-primary">Save Attendance</button>
                </form>
            </div>
            <?php endif; ?>
            <?php
        }, 'Take Attendance');
        exit;
    }

    if ($page === 'teacher_marks') {
        if ($action === 'save') { /* handled in POST */ }
        render_layout(function () use ($tid) {
            $subjects = db()->query("SELECT * FROM subjects WHERE teacher_id=" . (int)$tid)->fetchAll();
            $exams = db()->query("SELECT * FROM exams WHERE status IN ('active','completed')")->fetchAll();
            $subject_id = $_GET['subject_id'] ?? ($subjects[0]['id'] ?? 0);
            $exam_id = $_GET['exam_id'] ?? ($exams[0]['id'] ?? 0);
            $students = $subject_id ? db()->query("SELECT s.* FROM students s WHERE s.class_id=(SELECT class_id FROM subjects WHERE id=" . (int)$subject_id . ") ORDER BY s.first_name")->fetchAll() : [];
            ?>
            <div class="card p-3 mb-3">
                <form method="get" class="row g-2">
                    <input type="hidden" name="page" value="teacher_marks">
                    <div class="col-md-4"><label class="form-label">Subject</label>
                        <select name="subject_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>" <?= $s['id']==$subject_id?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Exam</label>
                        <select name="exam_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($exams as $e): ?><option value="<?= $e['id'] ?>" <?= $e['id']==$exam_id?'selected':'' ?>><?= e($e['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <?php if ($students && $subject_id && $exam_id): ?>
            <div class="card p-3">
                <h5>Enter Marks</h5>
                <form method="post" action="?page=teacher_marks&action=save">
                    <input type="hidden" name="subject_id" value="<?= (int)$subject_id ?>">
                    <input type="hidden" name="exam_id" value="<?= (int)$exam_id ?>">
                    <div class="mb-3" style="max-width:200px"><label class="form-label">Max Score</label><input type="number" name="max_score" class="form-control" value="100"></div>
                    <table class="table table-sm">
                        <thead><tr><th>#</th><th>Student</th><th>Score</th></tr></thead>
                        <tbody>
                        <?php foreach ($students as $i => $s): ?>
                            <tr><td><?= $i+1 ?></td><td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                            <td><input type="number" step="0.01" name="score[<?= $s['id'] ?>]" class="form-control form-control-sm" style="max-width:120px"></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button class="btn btn-primary">Save Marks</button>
                </form>
            </div>
            <?php endif; ?>
            <?php
        }, 'Enter Marks');
        exit;
    }

    // Generic teacher pages
    $teacherPages = [
        'teacher_classes' => ['My Classes', "SELECT DISTINCT c.* FROM classes c JOIN subjects s ON s.class_id=c.id WHERE s.teacher_id=" . (int)$tid],
        'teacher_subjects' => ['My Subjects', "SELECT s.*, c.name class_name FROM subjects s LEFT JOIN classes c ON c.id=s.class_id WHERE s.teacher_id=" . (int)$tid],
        'teacher_students' => ['My Students', "SELECT DISTINCT s.*, c.name class_name FROM students s LEFT JOIN classes c ON c.id=s.class_id JOIN subjects sub ON sub.class_id=s.class_id WHERE sub.teacher_id=" . (int)$tid],
        'teacher_timetable' => ['My Timetable', "SELECT t.*, c.name class, sub.name subject FROM timetable t LEFT JOIN classes c ON c.id=t.class_id LEFT JOIN subjects sub ON sub.id=t.subject_id WHERE t.teacher_id=" . (int)$tid . " ORDER BY t.day, t.start_time"],
        'teacher_announcements' => ['Announcements', "SELECT * FROM announcements WHERE audience IN ('all','teachers') ORDER BY created_at DESC"],
    ];
    if (isset($teacherPages[$page])) {
        [$title, $sql] = $teacherPages[$page];
        render_layout(function () use ($title, $sql) {
            $rows = db()->query($sql)->fetchAll();
            ?>
            <div class="card p-3">
                <h5><?= e($title) ?></h5>
                <?php if ($rows): ?>
                <table class="table table-sm table-hover">
                    <thead><tr><?php foreach (array_keys($rows[0]) as $h): ?><th><?= e(ucfirst(str_replace('_',' ',$h))) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?><p class="text-muted">No records.</p><?php endif; ?>
            </div>
            <?php
        }, $title);
        exit;
    }
}

// ============================================================
// STUDENT PAGES
// ============================================================
if (str_starts_with($page, 'student_')) {
    require_role('student');
    $u = current_user();
    $sid = db()->query("SELECT id FROM students WHERE user_id={$u['id']}")->fetchColumn();

    $studentPages = [
        'student_subjects' => ['My Subjects', "SELECT s.*, t.first_name, t.last_name FROM subjects s LEFT JOIN teachers t ON t.id=s.teacher_id WHERE s.class_id=(SELECT class_id FROM students WHERE id=" . (int)$sid . ")"],
        'student_marks' => ['My Marks', "SELECT m.*, sub.name subject, e.name exam FROM marks m LEFT JOIN subjects sub ON sub.id=m.subject_id LEFT JOIN exams e ON e.id=m.exam_id WHERE m.student_id=" . (int)$sid . " ORDER BY m.id DESC"],
        'student_attendance' => ['My Attendance', "SELECT a.*, c.name class FROM attendance a LEFT JOIN classes c ON c.id=a.class_id WHERE a.student_id=" . (int)$sid . " ORDER BY a.date DESC"],
        'student_fees' => ['My Fees', "SELECT * FROM fees WHERE student_id=" . (int)$sid . " ORDER BY year DESC, term"],
        'student_timetable' => ['My Timetable', "SELECT t.*, c.name class, sub.name subject, tc.first_name, tc.last_name FROM timetable t LEFT JOIN classes c ON c.id=t.class_id LEFT JOIN subjects sub ON sub.id=t.subject_id LEFT JOIN teachers tc ON tc.id=t.teacher_id WHERE t.class_id=(SELECT class_id FROM students WHERE id=" . (int)$sid . ") ORDER BY t.day, t.start_time"],
        'student_announcements' => ['Announcements', "SELECT * FROM announcements WHERE audience IN ('all','students') ORDER BY created_at DESC"],
    ];

    if ($page === 'student_report_card') {
        render_layout(function () use ($sid) {
            $student = db()->query("SELECT s.*, c.name class_name FROM students s LEFT JOIN classes c ON c.id=s.class_id WHERE s.id=" . (int)$sid)->fetch();
            $marks = db()->query("SELECT m.*, sub.name subject, e.name exam FROM marks m LEFT JOIN subjects sub ON sub.id=m.subject_id LEFT JOIN exams e ON e.id=m.exam_id WHERE m.student_id=" . (int)$sid . " ORDER BY e.id, sub.name")->fetchAll();
            ?>
            <div class="card p-4">
                <div class="text-center mb-4">
                    <h4><?= e(SCHOOL_NAME) ?></h4>
                    <p class="text-muted"><?= e(SCHOOL_MOTTO) ?></p>
                    <h5>Report Card</h5>
                </div>
                <table class="table">
                    <tr><th>Name</th><td><?= e($student['first_name'] . ' ' . $student['last_name']) ?></td><th>Admission No</th><td><?= e($student['admission_no']) ?></td></tr>
                    <tr><th>Class</th><td><?= e($student['class_name']) ?></td><th>Date</th><td><?= date('d M Y') ?></td></tr>
                </table>
                <table class="table table-bordered">
                    <thead><tr><th>Subject</th><th>Exam</th><th>Score</th><th>Max</th><th>Grade</th></tr></thead>
                    <tbody>
                    <?php foreach ($marks as $m): ?>
                        <tr><td><?= e($m['subject']) ?></td><td><?= e($m['exam']) ?></td><td><?= e($m['score']) ?></td><td><?= e($m['max_score']) ?></td><td><span class="badge bg-primary"><?= e($m['grade']) ?></span></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <button onclick="window.print()" class="btn btn-primary no-print">Print</button>
            </div>
            <?php
        }, 'Report Card');
        exit;
    }

    if (isset($studentPages[$page])) {
        [$title, $sql] = $studentPages[$page];
        render_layout(function () use ($title, $sql) {
            $rows = db()->query($sql)->fetchAll();
            ?>
            <div class="card p-3">
                <h5><?= e($title) ?></h5>
                <?php if ($rows): ?>
                <table class="table table-sm table-hover">
                    <thead><tr><?php foreach (array_keys($rows[0]) as $h): ?><th><?= e(ucfirst(str_replace('_',' ',$h))) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?><p class="text-muted">No records.</p><?php endif; ?>
            </div>
            <?php
        }, $title);
        exit;
    }
}

// ============================================================
// PARENT PAGES
// ============================================================
if (str_starts_with($page, 'parent_')) {
    require_role('parent');
    $u = current_user();
    $pid = db()->query("SELECT id FROM parents WHERE user_id={$u['id']}")->fetchColumn();

    if ($page === 'parent_children') {
        render_layout(function () use ($pid) {
            $children = db()->query("SELECT s.*, c.name class_name FROM students s LEFT JOIN classes c ON c.id=s.class_id WHERE s.parent_id=" . (int)$pid)->fetchAll();
            ?>
            <div class="card p-3">
                <h5>My Children</h5>
                <table class="table">
                    <thead><tr><th>Admission No</th><th>Name</th><th>Class</th><th>Gender</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($children as $c): ?>
                        <tr>
                            <td><?= e($c['admission_no']) ?></td>
                            <td><?= e($c['first_name'] . ' ' . $c['last_name']) ?></td>
                            <td><?= e($c['class_name'] ?? '-') ?></td>
                            <td><?= e($c['gender']) ?></td>
                            <td><a href="?page=parent_marks&child_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">View Marks</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }, 'My Children');
        exit;
    }

    $child_id = $_GET['child_id'] ?? db()->query("SELECT id FROM students WHERE parent_id=" . (int)$pid . " LIMIT 1")->fetchColumn();

    $parentPages = [
        'parent_marks' => ['Marks', "SELECT m.*, sub.name subject, e.name exam, s.first_name, s.last_name FROM marks m LEFT JOIN subjects sub ON sub.id=m.subject_id LEFT JOIN exams e ON e.id=m.exam_id LEFT JOIN students s ON s.id=m.student_id WHERE m.student_id=" . (int)$child_id . " ORDER BY m.id DESC"],
        'parent_attendance' => ['Attendance', "SELECT a.*, s.first_name, s.last_name FROM attendance a LEFT JOIN students s ON s.id=a.student_id WHERE a.student_id=" . (int)$child_id . " ORDER BY a.date DESC"],
        'parent_fees' => ['Fees', "SELECT f.*, s.first_name, s.last_name FROM fees f LEFT JOIN students s ON s.id=f.student_id WHERE f.student_id=" . (int)$child_id],
        'parent_payment_history' => ['Payment History', "SELECT p.*, s.first_name, s.last_name FROM payments p LEFT JOIN students s ON s.id=p.student_id WHERE p.student_id=" . (int)$child_id . " ORDER BY p.paid_at DESC"],
        'parent_timetable' => ['Timetable', "SELECT t.*, sub.name subject, tc.first_name, tc.last_name FROM timetable t LEFT JOIN subjects sub ON sub.id=t.subject_id LEFT JOIN teachers tc ON tc.id=t.teacher_id WHERE t.class_id=(SELECT class_id FROM students WHERE id=" . (int)$child_id . ") ORDER BY t.day, t.start_time"],
        'parent_announcements' => ['Announcements', "SELECT * FROM announcements WHERE audience IN ('all','parents') ORDER BY created_at DESC"],
    ];

    if ($page === 'parent_report_card') {
        render_layout(function () use ($child_id) {
            $student = db()->query("SELECT s.*, c.name class_name FROM students s LEFT JOIN classes c ON c.id=s.class_id WHERE s.id=" . (int)$child_id)->fetch();
            $marks = db()->query("SELECT m.*, sub.name subject, e.name exam FROM marks m LEFT JOIN subjects sub ON sub.id=m.subject_id LEFT JOIN exams e ON e.id=m.exam_id WHERE m.student_id=" . (int)$child_id)->fetchAll();
            ?>
            <div class="card p-4">
                <h4>Report Card — <?= e($student['first_name'] . ' ' . $student['last_name']) ?></h4>
                <p class="text-muted"><?= e($student['class_name']) ?> · <?= e($student['admission_no']) ?></p>
                <table class="table table-bordered">
                    <thead><tr><th>Subject</th><th>Exam</th><th>Score</th><th>Grade</th></tr></thead>
                    <tbody>
                    <?php foreach ($marks as $m): ?><tr><td><?= e($m['subject']) ?></td><td><?= e($m['exam']) ?></td><td><?= e($m['score']) ?></td><td><?= e($m['grade']) ?></td></tr><?php endforeach; ?>
                    </tbody>
                </table>
                <button onclick="window.print()" class="btn btn-primary no-print">Print</button>
            </div>
            <?php
        }, 'Report Card');
        exit;
    }

    if (isset($parentPages[$page])) {
        [$title, $sql] = $parentPages[$page];
        render_layout(function () use ($title, $sql) {
            $rows = db()->query($sql)->fetchAll();
            ?>
            <div class="card p-3">
                <h5><?= e($title) ?></h5>
                <?php if ($rows): ?>
                <table class="table table-sm table-hover">
                    <thead><tr><?php foreach (array_keys($rows[0]) as $h): ?><th><?= e(ucfirst(str_replace('_',' ',$h))) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?><p class="text-muted">No records.</p><?php endif; ?>
            </div>
            <?php
        }, $title);
        exit;
    }
}

// ============================================================
// HEADMASTER PAGES
// ============================================================
if (str_starts_with($page, 'headmaster_')) {
    require_role('headmaster', 'admin');
    $hmPages = [
        'headmaster_students' => ['Students', "SELECT s.*, c.name class_name FROM students s LEFT JOIN classes c ON c.id=s.class_id ORDER BY s.first_name"],
        'headmaster_teachers' => ['Teachers', "SELECT t.*, d.name dept FROM teachers t LEFT JOIN departments d ON d.id=t.department_id"],
        'headmaster_classes' => ['Classes', "SELECT c.*, d.name dept FROM classes c LEFT JOIN departments d ON d.id=c.department_id"],
        'headmaster_attendance' => ['Attendance', "SELECT a.*, s.first_name, s.last_name, c.name class FROM attendance a LEFT JOIN students s ON s.id=a.student_id LEFT JOIN classes c ON c.id=a.class_id ORDER BY a.date DESC LIMIT 200"],
        'headmaster_marks' => ['Marks', "SELECT m.*, s.first_name, s.last_name, sub.name subject FROM marks m LEFT JOIN students s ON s.id=m.student_id LEFT JOIN subjects sub ON sub.id=m.subject_id ORDER BY m.id DESC LIMIT 200"],
        'headmaster_fees' => ['Fees', "SELECT f.*, s.first_name, s.last_name FROM fees f LEFT JOIN students s ON s.id=f.student_id ORDER BY f.id DESC"],
        'headmaster_reports' => ['Reports', "SELECT c.name class, COUNT(s.id) students FROM classes c LEFT JOIN students s ON s.class_id=c.id GROUP BY c.id"],
        'headmaster_announcements' => ['Announcements', "SELECT * FROM announcements ORDER BY created_at DESC"],
    ];
    if (isset($hmPages[$page])) {
        [$title, $sql] = $hmPages[$page];
        render_layout(function () use ($title, $sql) {
            $rows = db()->query($sql)->fetchAll();
            ?>
            <div class="card p-3">
                <h5><?= e($title) ?></h5>
                <?php if ($rows): ?>
                <table class="table table-sm table-hover">
                    <thead><tr><?php foreach (array_keys($rows[0]) as $h): ?><th><?= e(ucfirst(str_replace('_',' ',$h))) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?><p class="text-muted">No records.</p><?php endif; ?>
            </div>
            <?php
        }, $title);
        exit;
    }
}

// ============================================================
// API ENDPOINTS
// ============================================================
if (str_starts_with($page, 'api_')) {
    header('Content-Type: application/json');
    require_login();
    $action = $_GET['action'] ?? '';
    $response = ['success' => false, 'message' => 'Unknown endpoint'];

    try {
        switch ($page) {
            case 'api_students':
                if ($action === 'list') {
                    $response = ['success' => true, 'data' => db()->query("SELECT * FROM students")->fetchAll()];
                } elseif ($action === 'delete') {
                    require_role('admin');
                    db()->prepare("DELETE FROM students WHERE id=?")->execute([(int)$_GET['id']]);
                    $response = ['success' => true, 'message' => 'Deleted'];
                }
                break;
            case 'api_attendance':
                if ($action === 'report') {
                    $response = ['success' => true, 'data' => db()->query("SELECT * FROM attendance ORDER BY date DESC LIMIT 100")->fetchAll()];
                }
                break;
            case 'api_marks':
                if ($action === 'report') {
                    $response = ['success' => true, 'data' => db()->query("SELECT * FROM marks ORDER BY id DESC LIMIT 100")->fetchAll()];
                }
                break;
            case 'api_fees':
                if ($action === 'balance') {
                    $response = ['success' => true, 'data' => db()->query("SELECT * FROM fees")->fetchAll()];
                }
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    echo json_encode($response);
    exit;
}

// ============================================================
// FALLBACK
// ============================================================
render_layout(function () {
    ?>
    <div class="card p-5 text-center">
        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
        <h4>Page Not Found</h4>
        <p class="text-muted">The requested page <code><?= e($_GET['page'] ?? '') ?></code> does not exist.</p>
        <a href="?page=dashboard" class="btn btn-primary">Back to Dashboard</a>
    </div>
    <?php
}, 'Not Found');
exit;