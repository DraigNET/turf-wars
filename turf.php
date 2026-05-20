<?php
require_once 'includes/bootstrap.php';
require_login();

$pdo = db();

/**
 * Convert HEX → RGB
 */
function hexToRgb($hex)
{
    $hex = ltrim($hex, '#');

    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    ];
}

$cityId = current_city_id();

// Load city coordinate bounds
$cityRow = $pdo->prepare("SELECT * FROM cities WHERE id = ?");
$cityRow->execute([$cityId]);
$cityRow = $cityRow->fetch() ?: ['map_image'=>'assets/img/map.png','map_min_x'=>-3000,'map_max_x'=>3000,'map_min_y'=>-3000,'map_max_y'=>3000];

/**
 * Load turf + control data for this city
 */
$stmt = $pdo->prepare("
    SELECT
        t.id AS turf_id,
        t.name,
        ta.min_x,
        ta.min_y,
        ta.max_x,
        ta.max_y,
        tc.faction_id,
        tc.control_percent,
        f.name AS faction_name,
        f.color,
        f.icon_path
    FROM turfs t
    JOIN turf_areas ta ON ta.turf_id = t.id
    LEFT JOIN turf_control tc ON tc.turf_id = t.id
    LEFT JOIN factions f ON f.id = tc.faction_id
    WHERE t.city_id = ?
");
$stmt->execute([$cityId]);

$rows = $stmt->fetchAll();

$turfs = [];

foreach ($rows as $row) {
    $tid = $row['turf_id'];

    if (!isset($turfs[$tid])) {
        $turfs[$tid] = [
            'name' => $row['name'],
            'areas' => [],
            'factions' => []
        ];
    }

    // Areas
    $turfs[$tid]['areas'][] = [
        'min_x' => $row['min_x'],
        'min_y' => $row['min_y'],
        'max_x' => $row['max_x'],
        'max_y' => $row['max_y']
    ];

    // Factions
    if ($row['faction_id']) {
        $turfs[$tid]['factions'][$row['faction_id']] = [
            'name' => $row['faction_name'],
            'percent' => $row['control_percent'],
            'color' => $row['color'],
            'icon' => $row['icon_path']
        ];
    }
}

$char = get_character();

if (!$char) {
    redirect('character-create.php');
}

// Build sorted turf list for the zone panel
$turfList = [];
foreach ($turfs as $turfId => $turf) {
    $factions = array_values($turf['factions']);
    usort($factions, fn($a, $b) => $b['percent'] <=> $a['percent']);
    $turfList[] = ['id' => $turfId, 'name' => $turf['name'], 'factions' => $factions];
}
usort($turfList, fn($a, $b) => strcmp($a['name'], $b['name']));

$gameMainClass = 'is-turf';
require_once 'templates/header.php';
?>

<!-- ZONE LIST OVERLAY PANEL -->
<div id="zone-panel" class="turf-zone-panel">
    <div class="turf-zone-panel-header">
        <span class="panel-title" style="margin:0">Zones</span>
        <button id="zone-panel-toggle" class="turf-panel-toggle" title="Collapse">&#x25B2;</button>
    </div>
    <div id="zone-list" class="turf-zone-list">
        <?php foreach ($turfList as $tz):
            $top    = $tz['factions'][0] ?? null;
            $isHere = ($char['turf_id'] == $tz['id']);
            $dotCol = $top ? $top['color'] : '#555';
        ?>
        <div class="turf-zone-item <?= $isHere ? 'map-highlighted' : '' ?>" data-turf-id="<?= $tz['id'] ?>">
            <div class="turf-zone-row">
                <span class="turf-zone-dot" style="background:<?= e($dotCol) ?>"></span>
                <span class="turf-zone-name <?= $isHere ? 'turf-zone-here' : '' ?>">
                    <?= e($tz['name']) ?>
                    <?php if ($isHere): ?><span class="turf-here-badge">HERE</span><?php endif; ?>
                </span>
                <?php if ($top && $top['percent'] > 0): ?>
                    <span class="turf-zone-lead" style="color:<?= e($top['color']) ?>"><?= $top['percent'] ?>%</span>
                <?php endif; ?>
                <span class="turf-zone-chevron">&#x203A;</span>
            </div>
            <div class="turf-zone-detail">
                <?php foreach ($tz['factions'] as $f): ?>
                    <div class="turf-faction-row">
                        <span class="turf-faction-name"><?= e($f['name']) ?></span>
                        <div class="turf-faction-bar-wrap">
                            <div class="turf-faction-bar" style="width:<?= $f['percent'] ?>%;background:<?= e($f['color']) ?>"></div>
                        </div>
                        <span class="turf-faction-pct"><?= $f['percent'] ?>%</span>
                    </div>
                <?php endforeach; ?>
                <?php if ($isHere): ?>
                    <div class="turf-move-btn" style="opacity:0.4;cursor:default;text-align:center;">&#10003; Current Location</div>
                <?php else: ?>
                    <button class="turf-move-btn" onclick="moveToTurf(<?= $tz['id'] ?>)">Move Here</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- FULL-BLEED MAP -->
<div id="turf-map">
    <div id="turf-zoom-controls">
        <button id="zoom-in">+</button>
        <button id="zoom-out">−</button>
    </div>
    <?php
    $svgW    = 6144;
    $svgH    = 6144;
    $rangeX  = $cityRow['map_max_x'] - $cityRow['map_min_x'];
    $rangeY  = $cityRow['map_max_y'] - $cityRow['map_min_y'];
    $scaleX  = $svgW / $rangeX;
    $scaleY  = $svgH / $rangeY;
    $mapImg  = e($cityRow['map_image']);
    ?>
    <div id="turf-stage">
        <img src="<?= $mapImg ?>" draggable="false">
        <svg id="turf-overlay" width="<?= $svgW ?>" height="<?= $svgH ?>" viewBox="0 0 <?= $svgW ?> <?= $svgH ?>">
            <?php
            foreach ($turfs as $turfId => $turf) {
                $dominant = null; $topPct = -1; $isTie = false;
                foreach ($turf['factions'] as $f) {
                    if ($f['percent'] > $topPct)       { $topPct = $f['percent']; $dominant = $f; $isTie = false; }
                    elseif ($f['percent'] == $topPct)  { $isTie = true; }
                }
                if ($isTie || !$dominant) { $r = 120; $g = 120; $b = 120; }
                else { [$r, $g, $b] = hexToRgb($dominant['color']); }

                foreach ($turf['areas'] as $area) {
                    $x = ($area['min_x'] - $cityRow['map_min_x']) * $scaleX;
                    $y = ($cityRow['map_max_y'] - $area['max_y']) * $scaleY;
                    $w = ($area['max_x'] - $area['min_x']) * $scaleX;
                    $h = ($area['max_y'] - $area['min_y']) * $scaleY;
                    echo "<rect
                        x='{$x}' y='{$y}' width='{$w}' height='{$h}'
                        fill='rgb({$r},{$g},{$b})' fill-opacity='0.38'
                        stroke='#ffffff' stroke-opacity='0.35' stroke-width='1'
                        class='turf-rect'
                        data-turf-id='{$turfId}'
                        data-name='" . htmlspecialchars($turf['name'], ENT_QUOTES) . "'
                        data-factions='" . htmlspecialchars(json_encode($turf['factions']), ENT_QUOTES) . "'
                    />";
                }
            }
            ?>
        </svg>
    </div>
</div>

<div id="turf-tooltip"></div>

<script>
(function () {
    const panel      = document.getElementById('zone-panel');
    const toggle     = document.getElementById('zone-panel-toggle');
    const items      = document.querySelectorAll('.turf-zone-item');
    const rects      = document.querySelectorAll('.turf-rect');
    let   activeId   = null;

    // --- Collapse toggle ---
    toggle.addEventListener('click', () => {
        panel.classList.toggle('collapsed');
        toggle.innerHTML = panel.classList.contains('collapsed') ? '&#x25BC;' : '&#x25B2;';
    });

    // --- Highlight map rects for a turf id ---
    function highlightMap(turfId) {
        rects.forEach(r => {
            if (r.dataset.turfId === String(turfId)) {
                r.setAttribute('fill-opacity', '0.75');
                r.setAttribute('stroke-opacity', '0.9');
            } else {
                r.setAttribute('fill-opacity', '0.18');
                r.setAttribute('stroke-opacity', '0.2');
            }
        });
    }

    function resetMap() {
        rects.forEach(r => {
            r.setAttribute('fill-opacity', '0.38');
            r.setAttribute('stroke-opacity', '0.35');
        });
    }

    // --- Open / close a zone item ---
    function openItem(item) {
        const turfId = item.dataset.turfId;

        if (activeId === turfId && item.classList.contains('open')) {
            // closing
            item.classList.remove('open', 'map-highlighted');
            activeId = null;
            resetMap();
            return;
        }

        items.forEach(i => i.classList.remove('open', 'map-highlighted'));
        item.classList.add('open', 'map-highlighted');
        activeId = turfId;
        highlightMap(turfId);
    }

    items.forEach(item => {
        item.querySelector('.turf-zone-row').addEventListener('click', () => openItem(item));
    });

    // --- Map tap → open matching zone ---
    document.querySelectorAll('.turf-rect').forEach(rect => {
        rect.addEventListener('map-tap', () => {
            const turfId = rect.dataset.turfId;
            const item = document.querySelector(`.turf-zone-item[data-turf-id="${turfId}"]`);
            if (!item) return;

            // Expand panel if collapsed
            if (panel.classList.contains('collapsed')) {
                panel.classList.remove('collapsed');
                toggle.innerHTML = '&#x25B2;';
            }

            openItem(item);
            item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });
})();
</script>

<?php require_once 'templates/footer.php'; ?>