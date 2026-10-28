<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/functions.php';

date_default_timezone_set('Europe/Moscow');

// --- Переменные разных типов -------------------------------------------
$appName    = 'Заявки сотрудников';            // string
$appVersion = '0.2.0';                         // string
$dataDir    = __DIR__ . '/../data';

$employees     = loadEmployees($dataDir . '/employees.csv');   // array
$departments   = departmentsOf($employees);                     // array
$employeeCount = count($employees);                             // int
$avgExperience = averageExperience($employees);                 // float
$hasData       = $employeeCount > 0;                            // bool
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
        <a href="#employees">Сотрудники</a>
    </nav>
</header>

<main>
    <section class="hero">
        <span class="pill">ТРПО · PHP без MVC</span>
        <h1><?= e($appName) ?></h1>
        <p class="muted">Версия <?= e($appVersion) ?> · страница сформирована PHP <?= e(date('d.m.Y H:i:s')) ?></p>
    </section>

    <section id="employees">
        <h2>Сотрудники</h2>
        <?php if (!$hasData): ?>
            <p class="muted">Файл employees.csv не найден или пуст.</p>
        <?php else: ?>
            <p class="muted">Всего: <?= $employeeCount ?>, средний стаж <?= number_format($avgExperience, 1, ',', '') ?> г.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>ID</th><th>ФИО</th><th>Отдел</th><th>Стаж, лет</th><th>Уровень</th><th>Email</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($employees as $employee): ?>
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
</main>
</body>
</html>
