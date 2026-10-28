<?php
declare(strict_types=1);

// --- Ошибки показываем только при локальной разработке ------------------
$isLocal = PHP_SAPI === 'cli-server'
    || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);
error_reporting(E_ALL);
ini_set('display_errors', $isLocal ? '1' : '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../src/functions.php';

date_default_timezone_set('Europe/Moscow');

// --- Переменные разных типов -------------------------------------------
$appName      = 'Заявки сотрудников';          // string
$appVersion   = '1.0.0';                       // string
$dataDir      = __DIR__ . '/../data';
$requestsFile = $dataDir . '/requests.json';

$employees     = loadEmployees($dataDir . '/employees.csv');   // array
$departments   = departmentsOf($employees);                     // array
$employeeCount = count($employees);                             // int
$avgExperience = averageExperience($employees);                 // float
$hasData       = $employeeCount > 0;                            // bool

// --- Обработка формы (POST) --------------------------------------------
$input  = normalizeInput([]);   // пустые значения для первого показа формы
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $input  = normalizeInput($_POST);
    $errors = validateInput($input, $departments);

    if ($errors === []) {
        $newId = saveRequest($requestsFile, $input);
        // Post/Redirect/Get: обновление страницы не отправит форму повторно
        header('Location: /?saved=' . $newId . '#requests', true, 303);
        exit;
    }
}

$savedId  = filter_var($_GET['saved'] ?? null, FILTER_VALIDATE_INT) ?: null;
$requests = loadRequests($requestsFile);

// --- Фильтр таблицы (GET) ----------------------------------------------
$filterRaw     = $_GET['department'] ?? '';
$filterRaw     = is_string($filterRaw) ? trim($filterRaw) : '';
$filterUnknown = $filterRaw !== '' && !in_array($filterRaw, $departments, true);
$filter        = $filterUnknown ? '' : $filterRaw;   // неизвестный отдел → все записи
$visibleEmployees = filterByDepartment($employees, $filter);

// --- Самопроверка по form_cases.json -----------------------------------
$caseResults = runFormCases(loadFormCases($dataDir . '/form_cases.json'), $departments);
$casesPassed = count(array_filter($caseResults, static fn(array $r): bool => $r['passed']));

$navItems = [                                    // array для меню
    'employees'    => 'Сотрудники',
    'request-form' => 'Новая заявка',
    'requests'     => 'Заявки',
    'checks'       => 'Проверка',
];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="brand">Практическая работа 4</div>
    <nav>
        <?php foreach ($navItems as $anchor => $title): ?>
            <a href="#<?= e($anchor) ?>"><?= e($title) ?></a>
        <?php endforeach; ?>
    </nav>
</header>

<main>
    <section class="hero">
        <span class="pill">ТРПО · PHP без MVC</span>
        <h1><?= e($appName) ?></h1>
        <p class="muted">Версия <?= e($appVersion) ?> · страница сформирована PHP <?= e(date('d.m.Y H:i:s')) ?></p>
    </section>

    <div class="stats">
        <div class="stat"><b>Сотрудников</b><span><?= $employeeCount ?></span></div>
        <div class="stat"><b>Отделов</b><span><?= count($departments) ?></span></div>
        <div class="stat"><b>Средний стаж</b><span><?= number_format($avgExperience, 1, ',', '') ?> г.</span></div>
        <div class="stat"><b>Проверка кейсов</b><span><?= $casesPassed ?> / <?= count($caseResults) ?></span></div>
    </div>

    <section id="employees">
        <h2>Сотрудники</h2>
        <?php if (!$hasData): ?>
            <p class="muted">Файл employees.csv не найден или пуст.</p>
        <?php else: ?>
            <form class="filter" method="get" action="/#employees">
                <label for="filter-department" class="muted">Отдел:</label>
                <select id="filter-department" name="department">
                    <option value="">Все отделы</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= e($department) ?>"<?= $filter === $department ? ' selected' : '' ?>><?= e($department) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Показать</button>
                <?php if ($filterRaw !== ''): ?><a class="btn secondary" href="/#employees">Сбросить</a><?php endif; ?>
            </form>

            <?php if ($filterUnknown): ?>
                <div class="alert error">Отдел «<?= e($filterRaw) ?>» не найден — показаны все сотрудники.</div>
            <?php endif; ?>

            <p class="muted">Показано: <?= count($visibleEmployees) ?> из <?= $employeeCount ?></p>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>ID</th><th>ФИО</th><th>Отдел</th><th>Стаж, лет</th><th>Уровень</th><th>Email</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($visibleEmployees as $employee): ?>
                        <tr>
                            <td><?= $employee['id'] ?></td>
                            <td><?= e($employee['name']) ?></td>
                            <td><?= e($employee['department']) ?></td>
                            <td><?= $employee['experience'] ?></td>
                            <td><span class="pill"><?= e($employee['level']) ?></span></td>
                            <td><?= e($employee['email']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section id="request-form">
        <h2>Новая заявка сотрудника</h2>

        <?php if ($errors !== []): ?>
            <div class="alert error" role="alert">
                <strong>Заявка не сохранена. Исправьте ошибки:</strong>
                <ul>
                    <?php foreach ($errors as $message): ?>
                        <li><?= e($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- novalidate: браузерную проверку легко обойти, поэтому основная — серверная -->
        <form method="post" action="/#request-form" novalidate>
            <div class="form-grid">
                <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                    <label for="name">ФИО</label>
                    <input id="name" name="name" type="text" maxlength="<?= NAME_MAX_LENGTH ?>" required
                           value="<?= e($input['name']) ?>">
                    <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
                </div>

                <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required value="<?= e($input['email']) ?>">
                    <?php if (isset($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
                </div>

                <div class="field<?= isset($errors['department']) ? ' has-error' : '' ?>">
                    <label for="department">Отдел</label>
                    <select id="department" name="department" required>
                        <option value="">— выберите —</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= e($department) ?>"<?= $input['department'] === $department ? ' selected' : '' ?>><?= e($department) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['department'])): ?><div class="field-error"><?= e($errors['department']) ?></div><?php endif; ?>
                </div>

                <div class="field<?= isset($errors['experience']) ? ' has-error' : '' ?>">
                    <label for="experience">Стаж, лет (<?= EXPERIENCE_MIN ?>–<?= EXPERIENCE_MAX ?>)</label>
                    <input id="experience" name="experience" type="number" min="<?= EXPERIENCE_MIN ?>" max="<?= EXPERIENCE_MAX ?>" required
                           value="<?= e($input['experience']) ?>">
                    <?php if (isset($errors['experience'])): ?><div class="field-error"><?= e($errors['experience']) ?></div><?php endif; ?>
                </div>

                <div class="field wide<?= isset($errors['type']) ? ' has-error' : '' ?>">
                    <label for="type">Тип заявки</label>
                    <select id="type" name="type" required>
                        <option value="">— выберите —</option>
                        <?php foreach (REQUEST_TYPES as $code => $title): ?>
                            <option value="<?= e($code) ?>"<?= $input['type'] === $code ? ' selected' : '' ?>><?= e($title) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['type'])): ?><div class="field-error"><?= e($errors['type']) ?></div><?php endif; ?>
                </div>

                <div class="field wide<?= isset($errors['comment']) ? ' has-error' : '' ?>">
                    <label for="comment">Комментарий (необязательно, до <?= COMMENT_MAX_LENGTH ?> символов)</label>
                    <textarea id="comment" name="comment" rows="3"><?= e($input['comment']) ?></textarea>
                    <?php if (isset($errors['comment'])): ?><div class="field-error"><?= e($errors['comment']) ?></div><?php endif; ?>
                </div>
            </div>
            <button class="btn" type="submit">Отправить заявку</button>
        </form>
    </section>

    <section id="requests">
        <h2>Принятые заявки</h2>
        <?php if ($savedId !== null): ?>
            <div class="alert success" role="status">Заявка №<?= $savedId ?> принята и сохранена.</div>
        <?php endif; ?>

        <?php if ($requests === []): ?>
            <p class="muted">Заявок пока нет — заполните форму выше.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>№</th><th>ФИО</th><th>Отдел</th><th>Стаж</th><th>Уровень</th><th>Тип</th><th>Комментарий</th><th>Создана</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?= (int) $request['id'] ?></td>
                            <td><?= e($request['name']) ?><br><span class="muted"><?= e($request['email']) ?></span></td>
                            <td><?= e($request['department']) ?></td>
                            <td><?= (int) $request['experience'] ?></td>
                            <td><span class="pill"><?= e($request['level']) ?></span></td>
                            <td><?= e(REQUEST_TYPES[$request['type']] ?? $request['type']) ?></td>
                            <td><?= $request['comment'] === '' ? '<span class="muted">—</span>' : e($request['comment']) ?></td>
                            <td><?= e($request['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section id="checks">
        <h2>Проверка сценариев из form_cases.json</h2>
        <p class="muted">Каждый кейс проходит через те же функции normalizeInput и validateInput, что и форма.</p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Кейс</th><th>name</th><th>experience</th><th>Ожидается</th><th>Получено</th><th>Ошибки</th><th>Итог</th></tr>
                </thead>
                <tbody>
                <?php foreach ($caseResults as $result): ?>
                    <tr>
                        <td><?= e($result['case']) ?></td>
                        <td><?= $result['name'] === '' ? '<span class="muted">(пусто)</span>' : e($result['name']) ?></td>
                        <td><?= e($result['experience']) ?></td>
                        <td><?= $result['expected'] ? 'valid' : 'invalid' ?></td>
                        <td><?= $result['actual'] ? 'valid' : 'invalid' ?></td>
                        <td><?= $result['errors'] === [] ? '—' : e(implode(' ', $result['errors'])) ?></td>
                        <td><span class="pill <?= $result['passed'] ? 'ok' : 'fail' ?>"><?= $result['passed'] ? 'пройден' : 'не пройден' ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
