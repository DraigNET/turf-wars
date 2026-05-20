<?php
require_once 'includes/bootstrap.php';
require_login();

$pdo = db();

$cityNames = [1 => 'Los Santos', 2 => 'Vice City', 3 => 'Liberty City'];

/**
 * Filters
 */
$city   = max(1, min(3, (int)($_GET['city'] ?? 1)));
$action = $_GET['action'] ?? '';
$result = $_GET['result'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

$allowedActions = ['move', 'crime', 'run_drugs', 'train', 'eat', 'capture', 'patrol'];
$allowedResults = ['success', 'fail', 'blocked'];

if (!in_array($action, $allowedActions, true)) { $action = ''; }
if (!in_array($result, $allowedResults, true)) { $result = ''; }

/**
 * Build WHERE — always filter by city via the characters join
 */
$where  = ['(c.city_id = ? OR gl.character_id IS NULL)'];
$params = [$city];

if ($action !== '') {
    $where[] = 'gl.action_type = ?';
    $params[] = $action;
}

if ($result !== '') {
    $where[] = 'gl.status = ?';
    $params[] = $result;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

/**
 * Count total
 */
$stmtCount = $pdo->prepare("
    SELECT COUNT(*)
    FROM game_logs gl
    LEFT JOIN characters c ON c.id = gl.character_id
    $whereSQL
");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();

/**
 * Fetch logs
 */
$stmt = $pdo->prepare("
    SELECT gl.*, c.name AS character_name
    FROM game_logs gl
    LEFT JOIN characters c ON c.id = gl.character_id
    $whereSQL
    ORDER BY gl.created_at DESC
    LIMIT ? OFFSET ?
");
foreach ($params as $index => $value) {
    $stmt->bindValue($index + 1, $value);
}
$stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

/**
 * Pagination
 */
$totalPages = max(1, ceil($total / $limit));

if (empty($_SESSION['is_admin'])) {
    redirect('dashboard.php');
}

$char = get_character();

if (!$char) {
    redirect('character-create.php');
}

require_once 'templates/header.php';
?>

<div class="max-w-7xl mx-auto p-6">

    <h1 class="text-3xl font-bold mb-4">📜 Game Logs</h1>

    <!-- CITY TABS -->
    <div class="flex gap-1.5 mb-5">
        <?php foreach ($cityNames as $cid => $cname): ?>
        <a href="?city=<?= $cid ?>&action=<?= e($action) ?>&result=<?= e($result) ?>"
           class="px-3 py-1 rounded text-xs font-semibold transition-colors
               <?= $city === $cid ? 'bg-zinc-600 text-white' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-400' ?>">
            <?= $cname ?>
        </a>
        <?php endforeach; ?>
        <span class="ml-auto text-xs text-zinc-500 self-center"><?= number_format($total) ?> entries</span>
    </div>

    <!-- FILTERS -->
    <div class="panel p-4 rounded-xl mb-6">
        <form method="get" class="grid md:grid-cols-3 gap-3">
            <input type="hidden" name="city" value="<?= $city ?>">

            <select name="action" class="p-2 bg-zinc-800 border border-zinc-700 rounded">
                <option value="">All Actions</option>
                <option value="move" <?= $action === 'move' ? 'selected' : '' ?>>Move</option>
                <option value="crime" <?= $action === 'crime' ? 'selected' : '' ?>>Crime</option>
                <option value="run_drugs" <?= $action === 'run_drugs' ? 'selected' : '' ?>>Run Drugs</option>
                <option value="train" <?= $action === 'train' ? 'selected' : '' ?>>Train</option>
                <option value="eat" <?= $action === 'eat' ? 'selected' : '' ?>>Eat</option>
                <option value="capture" <?= $action === 'capture' ? 'selected' : '' ?>>Capture</option>
                <option value="patrol" <?= $action === 'patrol' ? 'selected' : '' ?>>Patrol</option>
            </select>

            <select name="result" class="p-2 bg-zinc-800 border border-zinc-700 rounded">
                <option value="">All Results</option>
                <option value="success" <?= $result === 'success' ? 'selected' : '' ?>>Success</option>
                <option value="fail" <?= $result === 'fail' ? 'selected' : '' ?>>Fail</option>
                <option value="blocked" <?= $result === 'blocked' ? 'selected' : '' ?>>Blocked</option>
            </select>

            <button class="bg-orange-600 hover:bg-orange-500 rounded px-4">
                Filter
            </button>

        </form>
    </div>

    <!-- LOG TABLE -->
    <div class="panel p-4 rounded-xl overflow-x-auto">

        <?php if (empty($logs)): ?>
            <div class="text-zinc-400 text-sm">
                No logs found.
            </div>
        <?php endif; ?>

        <table class="w-full text-sm">
            <thead class="text-zinc-400 border-b border-zinc-700">
                <tr>
                    <th class="text-left py-2">Time</th>
                    <th class="text-left">Character</th>
                    <th class="text-left">Action</th>
                    <th class="text-left">Result</th>
                    <th class="text-left">Message</th>
                    <th class="text-left">Data</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($logs as $log): ?>

                    <tr class="border-b border-zinc-800">
                        <td class="py-2"><?= e($log['created_at']) ?></td>

                        <td>
                            <div class="text-purple-400 font-semibold">
                                <?= e($log['character_name'] ?? 'Unknown') ?>
                            </div>
                            <div class="text-xs text-zinc-500">
                                UID: <?= (int)$log['user_id'] ?> | CID: <?= (int)$log['character_id'] ?>
                            </div>
                        </td>

                        <td>
                            <span class="text-blue-400"><?= e($log['action_type']) ?></span>
                        </td>

                        <td>
                            <?php
                            $color = 'text-zinc-400';
                            if ($log['status'] === 'success') $color = 'text-green-400';
                            elseif ($log['status'] === 'fail') $color = 'text-red-400';
                            elseif ($log['status'] === 'blocked') $color = 'text-yellow-400';
                            ?>
                            <span class="<?= $color ?>">
                                <?= e($log['status']) ?>
                            </span>
                        </td>

                        <td><?= e($log['message']) ?></td>

                        <td class="text-xs text-zinc-400">
                            <?php
                            $data = json_decode($log['context'], true);
                            if ($data) {
                                foreach ($data as $k => $v) {
                                    echo "<div><strong>$k:</strong> " . e($v) . "</div>";
                                }
                            }
                            ?>
                        </td>
                    </tr>

                <?php endforeach; ?>
            </tbody>
        </table>

    </div>

    <!-- PAGINATION -->
    <div class="flex justify-between mt-6 text-sm">

        <div>
            Page <?= $page ?> / <?= $totalPages ?>
        </div>

        <div class="flex gap-2">

            <?php if ($page > 1): ?>
                <a href="?city=<?= $city ?>&page=<?= $page - 1 ?>&action=<?= e($action) ?>&result=<?= e($result) ?>"
                   class="px-3 py-1 bg-zinc-800 rounded hover:bg-zinc-700">
                    Prev
                </a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?city=<?= $city ?>&page=<?= $page + 1 ?>&action=<?= e($action) ?>&result=<?= e($result) ?>"
                   class="px-3 py-1 bg-zinc-800 rounded hover:bg-zinc-700">
                    Next
                </a>
            <?php endif; ?>

        </div>

    </div>

</div>

<?php require_once 'templates/footer.php'; ?>
