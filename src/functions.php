<?php
declare(strict_types=1);

/**
 * Функции приложения «Заявки сотрудников».
 * Здесь только обработка данных — HTML-разметка находится в public/index.php.
 */

/** Безопасный вывод любого значения в HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Уровень сотрудника по стажу (вычисляемое поле для таблицы). */
function experienceLevel(int $years): string
{
    return match (true) {
        $years < 3 => 'Junior',
        $years < 6 => 'Middle',
        default    => 'Senior',
    };
}

/**
 * Преобразует одну строку CSV в удобный ассоциативный массив.
 * Возвращает null, если число колонок не совпадает с заголовком.
 */
function mapEmployeeRow(array $header, array $row): ?array
{
    if (count($row) !== count($header)) {
        return null;
    }

    $record = array_combine($header, array_map('trim', $row));
    $experience = (int) $record['experience'];

    return [
        'id'         => (int) $record['id'],
        'name'       => $record['name'],
        'department' => $record['department'],
        'experience' => $experience,
        'email'      => $record['email'],
        'level'      => experienceLevel($experience),
    ];
}

/** Читает employees.csv (разделитель «;») и возвращает массив сотрудников. */
function loadEmployees(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return [];
    }

    $header = fgetcsv($handle, null, ';', '"', '');
    if ($header === false) {
        fclose($handle);
        return [];
    }
    // Убираем возможный BOM у первого заголовка
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
    $header = array_map('trim', $header);

    $employees = [];
    while (($row = fgetcsv($handle, null, ';', '"', '')) !== false) {
        if ($row === [null]) {          // пустая строка в файле
            continue;
        }
        $employee = mapEmployeeRow($header, $row);
        if ($employee !== null) {
            $employees[] = $employee;
        }
    }
    fclose($handle);

    return $employees;
}

/** Уникальный отсортированный список отделов. */
function departmentsOf(array $employees): array
{
    $departments = array_unique(array_column($employees, 'department'));
    sort($departments);
    return $departments;
}

/** Средний стаж (float), 0.0 для пустого списка. */
function averageExperience(array $employees): float
{
    if ($employees === []) {
        return 0.0;
    }
    return array_sum(array_column($employees, 'experience')) / count($employees);
}

// --- Форма заявки ---------------------------------------------------------

const NAME_MIN_LENGTH    = 2;
const NAME_MAX_LENGTH    = 60;
const COMMENT_MAX_LENGTH = 300;
const EXPERIENCE_MIN     = 0;
const EXPERIENCE_MAX     = 50;
const REQUEST_TYPES      = [
    'vacation'  => 'Отпуск',
    'equipment' => 'Оборудование',
    'training'  => 'Обучение',
    'transfer'  => 'Перевод в другой отдел',
];

/**
 * Берёт из источника ($_POST или тестовый кейс) только нужные поля
 * и нормализует их через trim. Массив вместо строки превращается в ''.
 */
function normalizeInput(array $source): array
{
    $fields = ['name', 'email', 'department', 'experience', 'type', 'comment'];
    $result = [];
    foreach ($fields as $field) {
        $value = $source[$field] ?? '';
        $result[$field] = is_scalar($value) ? trim((string) $value) : '';
    }
    return $result;
}

/**
 * Серверная валидация. Возвращает массив ошибок вида ['поле' => 'сообщение'].
 * Пустой массив — данные корректны.
 */
function validateInput(array $input, array $departments): array
{
    $errors = [];

    $nameLength = mb_strlen($input['name']);
    if ($input['name'] === '') {
        $errors['name'] = 'Укажите ФИО сотрудника.';
    } elseif ($nameLength < NAME_MIN_LENGTH || $nameLength > NAME_MAX_LENGTH) {
        $errors['name'] = sprintf('ФИО должно содержать от %d до %d символов.', NAME_MIN_LENGTH, NAME_MAX_LENGTH);
    }

    if ($input['email'] === '') {
        $errors['email'] = 'Укажите email.';
    } elseif (filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Некорректный email.';
    }

    if ($input['department'] === '') {
        $errors['department'] = 'Выберите отдел.';
    } elseif (!in_array($input['department'], $departments, true)) {
        $errors['department'] = 'Такого отдела нет в списке.';
    }

    $experience = filter_var($input['experience'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => EXPERIENCE_MIN, 'max_range' => EXPERIENCE_MAX],
    ]);
    if ($input['experience'] === '') {
        $errors['experience'] = 'Укажите стаж.';
    } elseif ($experience === false) {
        $errors['experience'] = sprintf('Стаж — целое число от %d до %d.', EXPERIENCE_MIN, EXPERIENCE_MAX);
    }

    if (!array_key_exists($input['type'], REQUEST_TYPES)) {
        $errors['type'] = 'Выберите тип заявки.';
    }

    if (mb_strlen($input['comment']) > COMMENT_MAX_LENGTH) {
        $errors['comment'] = sprintf('Комментарий не длиннее %d символов.', COMMENT_MAX_LENGTH);
    }

    return $errors;
}

/** Превращает проверенный ввод в запись заявки с вычисляемыми полями. */
function buildRequest(array $input, int $id): array
{
    $experience = (int) $input['experience'];
    return [
        'id'         => $id,
        'name'       => preg_replace('/\s+/u', ' ', $input['name']),
        'email'      => mb_strtolower($input['email']),
        'department' => $input['department'],
        'experience' => $experience,
        'level'      => experienceLevel($experience),
        'type'       => $input['type'],
        'comment'    => $input['comment'],
        'created_at' => date('Y-m-d H:i'),
    ];
}

/** Читает сохранённые заявки из JSON-файла. */
function loadRequests(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

/** Добавляет заявку в JSON-файл и возвращает её id. */
function saveRequest(string $path, array $input): int
{
    $requests = loadRequests($path);
    $ids = array_column($requests, 'id');
    $id = $ids === [] ? 1 : max($ids) + 1;
    $requests[] = buildRequest($input, $id);
    file_put_contents(
        $path,
        json_encode($requests, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
    return $id;
}
