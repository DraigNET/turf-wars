<?php
require_once 'includes/bootstrap.php';
require_login();

$pdo = db();

// Load character for current city
$stmt = $pdo->prepare("
    SELECT c.*, f.name AS faction_name, f.type AS faction_type, f.color, f.icon_path,
           w.name AS weapon_name, w.war_points_bonus, w.kill_chance_bonus
    FROM characters c
    JOIN factions f ON f.id = c.faction_id
    LEFT JOIN weapons w ON w.id = c.weapon_id
    WHERE c.user_id = ? AND c.city_id = ?
    LIMIT 1
");
$stmt->execute([user_id(), current_city_id()]);
$char = $stmt->fetch();

if (!$char) {
    redirect('character-create.php');
}

$turf = null;
$turfControl = [];
$features = [];

if ($char['turf_id']) {

    // Get turf name
    $stmtTurf = $pdo->prepare("SELECT * FROM turfs WHERE id = ?");
    $stmtTurf->execute([$char['turf_id']]);
    $turf = $stmtTurf->fetch();

    decay_turf_heat($pdo, $char['turf_id']);

    // Get control percentages + faction info
    $stmtControl = $pdo->prepare("
        SELECT tc.control_percent, f.id AS faction_id, f.name, f.color
        FROM turf_control tc
        JOIN factions f ON f.id = tc.faction_id
        WHERE tc.turf_id = ?
        ORDER BY tc.control_percent DESC
    ");
    $stmtControl->execute([$char['turf_id']]);
    $turfControl = $stmtControl->fetchAll();
    // Load turf features
    $stmtFeatures = $pdo->prepare("
        SELECT feature_type 
        FROM turf_features 
        WHERE turf_id = ?
    ");
    $stmtFeatures->execute([$char['turf_id']]);
    $features = $stmtFeatures->fetchAll(PDO::FETCH_COLUMN);
}

// =========================
// TURF OWNERSHIP + DOMINANCE
// =========================
$ownerFactionId = $turf ? $turf['faction_id'] : null;
$topFactionId   = !empty($turfControl) ? $turfControl[0]['faction_id'] : null;
$ownsThisTurf   = ($ownerFactionId !== null && $char['faction_id'] == $ownerFactionId);
$ownerDiscount  = 0.20; // 20% off for owning the turf

// =========================
// ACTIVE WAR CHECK
// =========================
$activeWar = null;

if ($char['turf_id']) {
    $stmtWar = $pdo->prepare("
        SELECT id, turf_id
        FROM turf_wars
        WHERE status = 'active'
        AND (
            turf_id = ?
            OR attacker_faction_id = ?
            OR defender_faction_id = ?
        )
        LIMIT 1
    ");
    $stmtWar->execute([
        $char['turf_id'],
        $char['faction_id'],
        $char['faction_id']
    ]);

    $activeWar = $stmtWar->fetch();
}

// =========================
// WAR COOLDOWN CHECK
// =========================
$warCooldownActive = false;

if ($turf && !empty($turf['war_cooldown_until'])) {
    if (strtotime($turf['war_cooldown_until']) > time()) {
        $warCooldownActive = true;
    }
}

// =========================
// CAN START WAR
// =========================
$canStartWar = false;

if (
    $char['turf_id'] &&
    $char['faction_type'] === 'gang' &&
    !$activeWar &&
    !$warCooldownActive &&
    $topFactionId &&
    $ownerFactionId !== null &&
    $char['faction_id'] == $topFactionId &&
    $char['faction_id'] != $ownerFactionId
) {
    $canStartWar = true;
}

$message = null;

/**
 * ENERGY CHECK FUNCTION
 */
function has_energy($char, $cost) {
    return $char['energy'] >= $cost;
}

/**
 * EXP CHECK FUNCTION
 */
function xp_required($level) {
    return 100 + (($level - 1) * 40);
}
$xpNeeded = xp_required($char['level']);

$oldLevel = $char['level'];

/**
 * NORMALISE TURF
 */
function normalise_turf($pdo, $turfId) {

    $stmt = $pdo->prepare("
        SELECT faction_id, control_percent
        FROM turf_control
        WHERE turf_id = ?
    ");
    $stmt->execute([$turfId]);
    $rows = $stmt->fetchAll();

    $total = array_sum(array_column($rows, 'control_percent'));

    if ($total <= 0) {
        return;
    }

    $newValues = [];
    $runningTotal = 0;

    foreach ($rows as $row) {
        $exact = ($row['control_percent'] / $total) * 100;
        $percent = (int)floor($exact);
        $runningTotal += $percent;

        $newValues[] = [
            'faction_id' => $row['faction_id'],
            'percent' => $percent,
            'remainder' => $exact - $percent
        ];
    }

    $leftover = 100 - $runningTotal;

    usort($newValues, function ($a, $b) {
        if ($a['remainder'] === $b['remainder']) {
            return $a['faction_id'] <=> $b['faction_id'];
        }

        return $b['remainder'] <=> $a['remainder'];
    });

    for ($i = 0; $i < $leftover; $i++) {
        $newValues[$i]['percent']++;
    }

    // Apply updates
    foreach ($newValues as $row) {
        $pdo->prepare("
            UPDATE turf_control
            SET control_percent = ?
            WHERE turf_id = ? AND faction_id = ?
        ")->execute([$row['percent'], $turfId, $row['faction_id']]);
    }
}

function transfer_turf_control($pdo, $turfId, $toFactionId, $points) {
    $points = (int)$points;

    if ($points <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT faction_id, control_percent
        FROM turf_control
        WHERE turf_id = ? AND faction_id != ? AND control_percent > 0
        ORDER BY control_percent DESC
    ");
    $stmt->execute([$turfId, $toFactionId]);
    $donors = $stmt->fetchAll();

    $remaining = $points;
    $transferred = 0;

    $stmtRecipient = $pdo->prepare("
        SELECT control_percent
        FROM turf_control
        WHERE turf_id = ? AND faction_id = ?
        LIMIT 1
    ");
    $stmtRecipient->execute([$turfId, $toFactionId]);

    if ($stmtRecipient->fetchColumn() === false) {
        throw new RuntimeException('No turf control row exists for the acting faction.');
    }

    foreach ($donors as $donor) {
        if ($remaining <= 0) {
            break;
        }

        $taken = min($remaining, (int)$donor['control_percent']);

        if ($taken <= 0) {
            continue;
        }

        $pdo->prepare("
            UPDATE turf_control
            SET control_percent = GREATEST(control_percent - ?, 0)
            WHERE turf_id = ? AND faction_id = ?
        ")->execute([$taken, $turfId, $donor['faction_id']]);

        $remaining -= $taken;
        $transferred += $taken;
    }

    if ($transferred > 0) {
        $stmtAdd = $pdo->prepare("
            UPDATE turf_control
            SET control_percent = LEAST(control_percent + ?, 100)
            WHERE turf_id = ? AND faction_id = ?
        ");
        $stmtAdd->execute([$transferred, $turfId, $toFactionId]);

        if ($stmtAdd->rowCount() !== 1) {
            throw new RuntimeException('Failed to apply turf control to the acting faction.');
        }
    }

    return $transferred;
}

/**
 * PROCESS ACTIONS
 */
if (is_post()) {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    // EAT FOOD
    if ($action === 'eat') {

        $energyCost = 2;

        // Detect food source
        $foodType = null;

        foreach ($features as $f) {
            if (in_array($f, ['cluckin_bell', 'burger_shot', 'pizza'], true)) {
                $foodType = $f;
                break;
            }
        }

        // Pricing + gain
        $price = 0;
        $foodGain = 0;

        switch ($foodType) {
            case 'burger_shot':
                $price = 150;
                $foodGain = rand(30, 45);
                break;

            case 'cluckin_bell':
                $price = 100;
                $foodGain = rand(20, 35);
                break;

            case 'pizza':
                $price = 80;
                $foodGain = rand(15, 25);
                break;
        }

        if ($ownsThisTurf) {
            $price = (int)round($price * (1 - $ownerDiscount));
        }

        if (!$foodType) {
            $message = "There is no food available in this area.";
        } elseif (!has_energy($char, $energyCost)) {
            $message = "Not enough energy.";
        } elseif ($char['food'] >= 100) {
            $message = "You are already full.";
        } elseif ($char['money'] < $price) {
            $message = "You cannot afford food (${$price}).";
        } else {

            $hpHeal = (int)ceil($foodGain / 2);

            $pdo->prepare("
                UPDATE characters
                SET food   = LEAST(food + ?, 100),
                    health = LEAST(health + ?, health_max),
                    energy = energy - ?,
                    money  = money - ?
                WHERE id = ?
            ")->execute([$foodGain, $hpHeal, $energyCost, $price, $char['id']]);

            $message = "You bought food for \${$price} (+{$foodGain} food, +{$hpHeal} HP).";

            game_log('eat', 'success', 'Food purchased', [
                'price' => $price,
                'food_gain' => $foodGain,
                'turf_id' => $char['turf_id']
            ]);
        }
    }

    // TRAIN
    if ($action === 'train') {

        $trainEnergyCost = max(6, 10 - (int)floor($char['endurance'] / 12));

        if (!has_energy($char, $trainEnergyCost)) {
            $message = "Not enough energy.";
        }
        elseif ($char['xp'] >= $xpNeeded) {
            $message = "You need to level up before gaining more XP.";
        }  else {

            $stat = $_POST['stat'] ?? '';

            $validStats = ['strength', 'intelligence', 'endurance', 'charisma'];

            if (!in_array($stat, $validStats, true)) {
                $message = "Invalid stat.";
            } else {

                $pdo->beginTransaction();

                $xpGain = 8;

                // Food penalty
                if ($char['food'] <= 0) {
                    $xpGain = 4;
                } elseif ($char['food'] < 25) {
                    $xpGain = 5;
                } elseif ($char['food'] < 50) {
                    $xpGain = 6;
                }

                $pdo->prepare("
                    UPDATE characters
                    SET $stat = $stat + 1,
                        energy = energy - ?,
                        xp = LEAST(xp + ?, ?),
                        food = GREATEST(food - 2, 0)
                    WHERE id = ?
                ")->execute([$trainEnergyCost, $xpGain, $xpNeeded, $char['id']]);

                $pdo->commit();

                $message = "You trained your $stat (+1).";

                game_log('train', 'success', 'Stat trained', [
                    'stat' => $stat,
                    'xp' => $xpGain,
                    'turf_id' => $char['turf_id']
                ]);
            }
        }
    }

    // CRIME (GANG)
    if ($action === 'crime' && $char['faction_type'] === 'gang') {

        $crimeCooldown = 30;
        $now = time();

        if ($activeWar) {
            $message = "This turf is currently at war. You cannot commit crimes here.";
        }
        elseif ($turf && $turf['heat'] >= 90) {
            $message = "This area is under lockdown. Criminal activity is not possible right now.";

            game_log('crime', 'blocked', 'Lockdown active', [
                'turf_id' => $char['turf_id']
            ]);
        }
        elseif ((int)$char['health'] <= 0) {
            $message = "You are too injured to act. Eat food to recover some health.";
        }
        elseif ($char['last_crime'] > 0 && ($now - $char['last_crime']) < $crimeCooldown) {
            $remaining = $crimeCooldown - ($now - $char['last_crime']);
            $message = "You need to wait {$remaining}s before committing another crime.";
        }
        elseif (!has_energy($char, max(6, 10 - (int)floor($char['endurance'] / 12)))) {
            $message = "Not enough energy.";
        }
        elseif ($char['xp'] >= $xpNeeded) {
            $message = "You need to level up before gaining more XP.";
        } else {

            $crimeEnergyCost = max(6, 10 - (int)floor($char['endurance'] / 12));
            $chance = 55 + ($char['intelligence'] * 1.25) + ($char['charisma'] * 0.35);

            // Food penalty
            if ($char['food'] <= 0) {
                $chance -= 20;
            } elseif ($char['food'] < 25) {
                $chance -= 10;
            } elseif ($char['food'] < 50) {
                $chance -= 5;
            }

            // Heat penalty
            if ($turf && $turf['heat'] > 0) {
                $chance -= ($turf['heat'] * 0.2);
            }

            // Low HP penalty
            $hpRatio = ($char['health_max'] > 0) ? ($char['health'] / $char['health_max']) : 1;
            if ($hpRatio < 0.25) {
                $chance -= 15;
            }

            // Clamp chance (important)
            $chance = max(5, min(95, $chance));
            $roll = rand(1, 100);

            $pdo->beginTransaction();

            if ($roll <= $chance) {
                $money = rand(350, 650) + ($char['charisma'] * 3);

                $pdo->prepare("
                    UPDATE characters
                    SET money = money + ?,
                        xp = LEAST(xp + 12, ?),
                        energy = energy - ?,
                        food = GREATEST(food - 3, 0),
                        last_crime = ?
                    WHERE id = ?
                ")->execute([$money, $xpNeeded, $crimeEnergyCost, $now, $char['id']]);

                $pdo->prepare("
                    UPDATE turfs
                    SET heat = LEAST(heat + 5, 100), heat_updated_at = NOW()
                    WHERE id = ?
                ")->execute([$char['turf_id']]);

                $message = "Crime successful! +$" . $money;
                game_log('crime', 'success', 'Crime success', [
                    'money' => $money,
                    'chance' => $chance,
                    'turf_id' => $char['turf_id']
                ]);
            } else {

                $injuryDmg = rand(5, 15);

                $pdo->prepare("
                    UPDATE characters
                    SET xp = LEAST(xp + 4, ?),
                        energy = energy - ?,
                        food = GREATEST(food - 3, 0),
                        health = GREATEST(health - ?, 0),
                        last_crime = ?
                    WHERE id = ?
                ")->execute([$xpNeeded, $crimeEnergyCost, $injuryDmg, $now, $char['id']]);

                $pdo->prepare("
                    UPDATE turfs
                    SET heat = LEAST(heat + 5, 100), heat_updated_at = NOW()
                    WHERE id = ?
                ")->execute([$char['turf_id']]);

                $message = "Crime failed. You took {$injuryDmg} damage.";

                game_log('crime', 'fail', 'Crime failed', [
                    'chance' => $chance,
                    'turf_id' => $char['turf_id']
                ]);
            }

            // Apply small influence regardless of success.
            transfer_turf_control($pdo, $char['turf_id'], $char['faction_id'], 1);

            $pdo->commit();
        }
    }

    // RUN DRUGS (GANG)
    if ($action === 'run_drugs' && $char['faction_type'] === 'gang') {

        if ($activeWar) {
            $message = "This turf is currently at war. Drug running is disabled.";
        }
        else {
            $cooldown = 30;
            $now = time();

            if ($turf && $turf['heat'] >= 90) {
                $message = "This area is under lockdown. Drug running is not possible right now.";

                game_log('run_drugs', 'blocked', 'Lockdown active', [
                    'turf_id' => $char['turf_id']
                ]);
            }
            elseif (!$char['turf_id']) {
                $message = "You are not in a turf.";
            } elseif ((int)$char['health'] <= 0) {
                $message = "You are too injured to act. Eat food to recover some health.";
            } elseif (!has_energy($char, max(6, 10 - (int)floor($char['endurance'] / 12)))) {
                $message = "Not enough energy.";
            }
            elseif ($char['xp'] >= $xpNeeded) {
                $message = "You need to level up before gaining more XP.";
            } elseif ($char['last_drug_run'] > 0 && ($now - $char['last_drug_run']) < $cooldown) {

                $remaining = $cooldown - ($now - $char['last_drug_run']);
                $message = "You need to wait {$remaining}s before running drugs again.";

            } else {

                // SUCCESS CHANCE
                $chance = 55 
                    + ($char['intelligence'] * 1.2) 
                    + ($char['charisma'] * 0.5);

                // Food penalty
                if ($char['food'] <= 0) {
                    $chance -= 20;
                } elseif ($char['food'] < 25) {
                    $chance -= 10;
                } elseif ($char['food'] < 50) {
                    $chance -= 5;
                }

                // Heat penalty
                if ($turf && $turf['heat'] > 0) {
                    $chance -= ($turf['heat'] * 0.25);
                }

                // Low HP penalty
                $hpRatio = ($char['health_max'] > 0) ? ($char['health'] / $char['health_max']) : 1;
                if ($hpRatio < 0.25) {
                    $chance -= 15;
                }

                // Clamp
                $chance = max(5, min(95, $chance));

                $roll = rand(1, 100);
                $drugEnergyCost = max(6, 10 - (int)floor($char['endurance'] / 12));

                $pdo->beginTransaction();

                if ($roll <= $chance) {

                    // SUCCESS
                    $money = rand(450, 800) + ($char['charisma'] * 5);

                    $pdo->prepare("
                        UPDATE characters
                        SET money = money + ?,
                            xp = LEAST(xp + 12, ?),
                            energy = energy - ?,
                            food = GREATEST(food - 4, 0),
                            last_drug_run = ?
                        WHERE id = ?
                    ")->execute([$money, $xpNeeded, $drugEnergyCost, $now, $char['id']]);

                    $pdo->prepare("
                        UPDATE turfs
                        SET heat = LEAST(heat + 7, 100), heat_updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$char['turf_id']]);

                    $message = "Successful drug run! +$" . $money;

                    game_log('run_drugs', 'success', 'Drug run success', [
                        'money' => $money,
                        'chance' => $chance,
                        'turf_id' => $char['turf_id']
                    ]);

                } else {

                    // FAILURE
                    $injuryDmg = rand(8, 20);

                    $pdo->prepare("
                        UPDATE characters
                        SET xp = LEAST(xp + 5, ?),
                            energy = energy - ?,
                            food = GREATEST(food - 4, 0),
                            health = GREATEST(health - ?, 0),
                            last_drug_run = ?
                        WHERE id = ?
                    ")->execute([$xpNeeded, $drugEnergyCost, $injuryDmg, $now, $char['id']]);

                    $pdo->prepare("
                        UPDATE turfs
                        SET heat = LEAST(heat + 7, 100), heat_updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$char['turf_id']]);

                    $message = "Drug run failed. You took {$injuryDmg} damage escaping the cops.";

                    game_log('run_drugs', 'fail', 'Drug run failed', [
                        'chance' => $chance,
                        'turf_id' => $char['turf_id']
                    ]);
                }

                $pdo->commit();
            }
        }
    }

    // START WAR
    if ($action === 'start_war') {

        // =========================
        // EXTRA CHECK: DEFENDER BUSY
        // =========================
        $targetBusy = false;

        if ($ownerFactionId) {
            $stmtCheck = $pdo->prepare("
                SELECT id 
                FROM turf_wars
                WHERE status = 'active'
                AND (
                    attacker_faction_id = ?
                    OR defender_faction_id = ?
                )
                LIMIT 1
            ");
            $stmtCheck->execute([$ownerFactionId, $ownerFactionId]);
            $targetBusy = $stmtCheck->fetch();
        }

        if (!$canStartWar || $activeWar || $warCooldownActive || $targetBusy) {

            if ($targetBusy) {
                $message = "The defending faction is already engaged in another war.";
            }
            elseif ($activeWar && $activeWar['turf_id'] != $char['turf_id']) {
                $message = "Your faction is already engaged in another turf war.";
            }
            elseif ($activeWar) {
                $message = "This turf already has an active war.";
            }
            elseif ($warCooldownActive) {
                $message = "This turf is on cooldown.";
            }
            else {
                $message = "You cannot start a war on this turf.";
            }

        } else {

            $pdo->beginTransaction();

            // =========================
            // CREATE WAR
            // =========================
            $stmt = $pdo->prepare("
                INSERT INTO turf_wars 
                (turf_id, attacker_faction_id, defender_faction_id, started_by_char_id)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $char['turf_id'],
                $char['faction_id'],
                $ownerFactionId,
                $char['id']
            ]);

            $warId = $pdo->lastInsertId();

            // =========================
            // ADD PARTICIPANTS (FULL FACTIONS)
            // =========================
            $stmtChars = $pdo->prepare("
                SELECT id, faction_id, COALESCE(health_max, 100) AS health_max
                FROM characters
                WHERE faction_id IN (?, ?) AND city_id = ?
            ");
            $stmtChars->execute([$char['faction_id'], $ownerFactionId, current_city_id()]);

            $participants = $stmtChars->fetchAll();

            foreach ($participants as $p) {
                $hp = (int)($p['health_max'] ?? 100);
                $pdo->prepare("
                    INSERT INTO turf_war_participants
                    (war_id, char_id, faction_id, current_hp, max_hp)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$warId, $p['id'], $p['faction_id'], $hp, $hp]);
            }

            $pdo->commit();

            // Redirect to war page
            redirect('war.php');
        }
    }

    // CAPTURE TURF
    if ($action === 'capture') {

        if ($activeWar) {
            $message = "This turf is currently at war. You cannot influence it right now.";
        }
        else {
            $now = time();

            // ----------------------------
            // SCALING ENERGY COST
            // ----------------------------
            $energyCost = max(10, (15 - (int)floor($char['endurance'] / 10)) + ($char['capture_streak'] * 5));

            // ----------------------------
            // VALIDATION (THIS IS THE FIX)
            // ----------------------------
            $cooldown = 45;

            if ($char['last_capture_time'] > 0 && ($now - $char['last_capture_time']) < $cooldown) {

                $remaining = $cooldown - ($now - $char['last_capture_time']);
                $message = "You must wait {$remaining}s before influencing this turf again.";

            }
            elseif (!has_energy($char, $energyCost)) {

                $message = "Not enough energy.";

            }
            elseif (!$char['faction_id']) {

                $message = "You must be in a faction.";

            }
            elseif (!$char['turf_id']) {

                $message = "You are not in a turf.";

            }
            elseif ($char['xp'] >= $xpNeeded) {

                $message = "You need to level up before gaining more XP.";

            }
            else {

                $pdo->beginTransaction();

                // ----------------------------
                // ACTIVITY TRACKING
                // ----------------------------
                $pdo->prepare("
                    INSERT INTO turf_activity (turf_id, faction_id, action_time)
                    VALUES (?, ?, ?)
                ")->execute([$char['turf_id'], $char['faction_id'], $now]);

                $stmtActivity = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM turf_activity 
                    WHERE turf_id = ? 
                    AND action_time > ?
                ");
                $stmtActivity->execute([$char['turf_id'], $now - 60]);
                $recentActions = (int)$stmtActivity->fetchColumn();

                // ----------------------------
                // DIMINISHING RETURNS
                // ----------------------------
                $baseGain = 5 + (int)floor($char['strength'] / 10);
                $activityPenalty = floor($recentActions / 5);
                $gain = max(1, $baseGain - $activityPenalty);

                // ----------------------------
                // DEFENSIVE RESISTANCE
                // ----------------------------
                $stmtTop = $pdo->prepare("
                    SELECT control_percent 
                    FROM turf_control 
                    WHERE turf_id = ?
                    ORDER BY control_percent DESC
                    LIMIT 1
                ");
                $stmtTop->execute([$char['turf_id']]);
                $currentControl = (int)$stmtTop->fetchColumn();

                $resistance = $currentControl / 100;
                $gain = $gain * (1 - ($resistance * 0.7));

                $gain = max(1, round($gain));

                // Charisma adds influence efficiency
                $gain += (int)floor($char['charisma'] / 15);
                $gain = max(1, $gain);

                // ----------------------------
                // COMEBACK BONUS
                // ----------------------------
                $stmtOwned = $pdo->prepare("SELECT COUNT(*) FROM turfs WHERE faction_id = ? AND city_id = ?");
                $stmtOwned->execute([$char['faction_id'], current_city_id()]);
                if ((int)$stmtOwned->fetchColumn() === 0) {
                    $gain = max(1, (int)round($gain * 1.5));
                }

                // ----------------------------
                // APPLY CONTROL
                // ----------------------------
                $actualGain = transfer_turf_control($pdo, $char['turf_id'], $char['faction_id'], $gain);

                // ----------------------------
                // STREAK LOGIC (FIXED)
                // ----------------------------
                $newStreak = $char['capture_streak'];

                if ($char['last_capture_time'] > 0 && ($now - $char['last_capture_time']) > 120) {
                    $newStreak = 0;
                }

                $newStreak++;

                // ----------------------------
                // CHARACTER UPDATE
                // ----------------------------
                $xpGain = 15;

                if ($char['food'] <= 0) {
                    $xpGain = 8;
                } elseif ($char['food'] < 25) {
                    $xpGain = 10;
                } elseif ($char['food'] < 50) {
                    $xpGain = 12;
                }

                $pdo->prepare("
                    UPDATE characters
                    SET energy = energy - ?,
                        last_capture_time = ?,
                        capture_streak = ?,
                        xp = LEAST(xp + ?, ?),
                        food = GREATEST(food - 5, 0)
                    WHERE id = ?
                ")->execute([$energyCost, $now, $newStreak, $xpGain, $xpNeeded, $char['id']]);

                $pdo->commit();

                if ($actualGain <= 2) {
                    $message = "You applied pressure (+{$actualGain}%) — Heavy resistance.";
                }
                elseif ($actualGain <= 3) {
                    $message = "You applied pressure (+{$actualGain}%) — Resistance building.";
                }
                else {
                    $message = "You applied strong pressure (+{$actualGain}%).";
                }

                game_log('capture', 'success', 'Turf capture', [
                    'xp' => $xpGain,
                    'gain' => $actualGain,
                    'turf_id' => $char['turf_id']
                ]);
            }
        }
    }

    // BUY WEAPON
    if ($action === 'buy_weapon') {

        $weaponId = (int)($_POST['weapon_id'] ?? 0);

        $stmtWeapon = $pdo->prepare("SELECT * FROM weapons WHERE id = ? AND city_id = ?");
        $stmtWeapon->execute([$weaponId, current_city_id()]);
        $weapon = $stmtWeapon->fetch();

        if ($char['faction_type'] === 'law') {
            $message = "LSPD officers do not purchase weapons here.";
        } elseif (!in_array('ammu_nation', $features)) {
            $message = "You need to be at an Ammu-Nation store to buy weapons.";
        } elseif (!$weapon) {
            $message = "Invalid weapon.";
        } elseif ($weapon['id'] === (int)$char['weapon_id']) {
            $message = "You already have this weapon equipped.";
        } else {
            $weaponPrice = $ownsThisTurf
                ? (int)round($weapon['price'] * (1 - $ownerDiscount))
                : (int)$weapon['price'];

            if ($char['money'] < $weaponPrice) {
                $message = "You cannot afford the {$weapon['name']} (\${$weaponPrice}).";
            } else {
                $pdo->prepare("
                    UPDATE characters
                    SET weapon_id = ?, money = money - ?
                    WHERE id = ?
                ")->execute([$weapon['id'], $weaponPrice, $char['id']]);

                $message = "You equipped the {$weapon['name']}!";

                game_log('buy_weapon', 'success', 'Weapon purchased', [
                    'weapon_id' => $weapon['id'],
                    'price'     => $weapon['price']
                ]);
            }
        }
    }

    // HOSPITAL
    if ($action === 'hospital_heal') {

        $healCost = $ownsThisTurf ? (int)round(500 * (1 - $ownerDiscount)) : 500;

        if (!in_array('hospital', $features)) {
            $message = "There is no hospital in this area.";
        } elseif ($char['health'] >= $char['health_max']) {
            $message = "You are already at full health.";
        } elseif ($char['money'] < $healCost) {
            $message = "You cannot afford treatment (\${$healCost}).";
        } else {
            $healed = $char['health_max'] - $char['health'];

            $pdo->prepare("
                UPDATE characters
                SET health = health_max,
                    money  = money - ?
                WHERE id = ?
            ")->execute([$healCost, $char['id']]);

            $message = "Treatment complete. Fully healed (+{$healed} HP) for \${$healCost}.";

            game_log('hospital_heal', 'success', 'Hospital treatment', [
                'healed'    => $healed,
                'turf_id'   => $char['turf_id']
            ]);
        }
    }

    // =====================================================
    // LSPD ACTIONS
    // =====================================================

    if ($char['faction_type'] === 'law') {

        $now        = time();
        $pdCooldown = 30;
        $onCooldown = $char['last_action'] > 0 && ($now - $char['last_action']) < $pdCooldown;
        $pdEnergy   = max(6, 10 - (int)floor($char['endurance'] / 12));

        // --------------------------------------------------
        // SUPPRESS TURF — drain all gangs' influence
        // --------------------------------------------------
        if ($action === 'suppress_turf') {

            if ($onCooldown) {
                $remaining = $pdCooldown - ($now - $char['last_action']);
                $message = "Wait {$remaining}s before acting again.";
            } elseif (!has_energy($char, $pdEnergy)) {
                $message = "Not enough energy.";
            } elseif ($char['xp'] >= $xpNeeded) {
                $message = "You need to level up before gaining more XP.";
            } else {

                $drain      = 2 + (int)floor($char['intelligence'] / 10);
                $heatDrop   = 4 + (int)floor($char['intelligence'] / 10);
                $money      = rand(200, 350) + ($char['intelligence'] * 2) + ($char['charisma'] * 2);

                if ($char['food'] <= 0)       $money = (int)($money * 0.6);
                elseif ($char['food'] < 25)   $money = (int)($money * 0.75);
                elseif ($char['food'] < 50)   $money = (int)($money * 0.9);

                $pdo->beginTransaction();

                // Read current gang control before draining
                $stmtGangs = $pdo->prepare("
                    SELECT tc.faction_id, tc.control_percent
                    FROM turf_control tc
                    JOIN factions f ON f.id = tc.faction_id
                    WHERE tc.turf_id = ? AND f.type = 'gang'
                ");
                $stmtGangs->execute([$char['turf_id']]);
                $gangRows = $stmtGangs->fetchAll();

                // Drain each gang and track what was actually taken
                $totalDrained = 0;
                foreach ($gangRows as $gang) {
                    $actualDrain = min($drain, (int)$gang['control_percent']);
                    $totalDrained += $actualDrain;
                    $pdo->prepare("
                        UPDATE turf_control
                        SET control_percent = GREATEST(control_percent - ?, 0)
                        WHERE turf_id = ? AND faction_id = ?
                    ")->execute([$drain, $char['turf_id'], $gang['faction_id']]);
                }

                // LSPD gains exactly what was drained — total stays at 100%
                if ($totalDrained > 0) {
                    $pdo->prepare("
                        UPDATE turf_control SET control_percent = control_percent + ?
                        WHERE turf_id = ? AND faction_id = ?
                    ")->execute([$totalDrained, $char['turf_id'], $char['faction_id']]);
                }

                normalise_turf($pdo, $char['turf_id']);

                $pdo->prepare("
                    UPDATE turfs SET heat = GREATEST(heat - ?, 0), heat_updated_at = NOW() WHERE id = ?
                ")->execute([$heatDrop, $char['turf_id']]);

                $pdo->prepare("
                    UPDATE characters
                    SET money = money + ?, xp = LEAST(xp + 10, ?),
                        energy = energy - ?, food = GREATEST(food - 2, 0),
                        last_action = ?
                    WHERE id = ?
                ")->execute([$money, $xpNeeded, $pdEnergy, $now, $char['id']]);

                $pdo->commit();

                $message = "Suppression complete. All gang influence reduced by {$drain}%. +\${$money}";

                game_log('suppress_turf', 'success', 'Turf suppressed', [
                    'drain' => $drain, 'turf_id' => $char['turf_id']
                ]);
            }
        }

        // --------------------------------------------------
        // TARGET FACTION — heavy drain on one specific gang
        // --------------------------------------------------
        if ($action === 'target_faction') {

            $targetFactionId = (int)($_POST['target_faction_id'] ?? 0);

            $stmtFac = $pdo->prepare("SELECT * FROM factions WHERE id = ? AND type = 'gang' AND city_id = ?");
            $stmtFac->execute([$targetFactionId, current_city_id()]);
            $targetFaction = $stmtFac->fetch();

            if ($onCooldown) {
                $remaining = $pdCooldown - ($now - $char['last_action']);
                $message = "Wait {$remaining}s before acting again.";
            } elseif (!$targetFaction) {
                $message = "Invalid target faction.";
            } elseif (!has_energy($char, 20)) {
                $message = "Not enough energy.";
            } elseif ($char['xp'] >= $xpNeeded) {
                $message = "You need to level up before gaining more XP.";
            } else {

                $drain  = 5 + (int)floor($char['intelligence'] / 8);
                $money  = rand(250, 400) + ($char['intelligence'] * 2);

                if ($char['food'] <= 0)       $money = (int)($money * 0.6);
                elseif ($char['food'] < 25)   $money = (int)($money * 0.75);
                elseif ($char['food'] < 50)   $money = (int)($money * 0.9);

                $pdo->beginTransaction();

                // Read target's actual control before draining
                $stmtTarget = $pdo->prepare("
                    SELECT control_percent FROM turf_control
                    WHERE turf_id = ? AND faction_id = ?
                ");
                $stmtTarget->execute([$char['turf_id'], $targetFactionId]);
                $targetCurrent = (int)($stmtTarget->fetchColumn() ?? 0);
                $actualDrain = min($drain, $targetCurrent);

                $pdo->prepare("
                    UPDATE turf_control
                    SET control_percent = GREATEST(control_percent - ?, 0)
                    WHERE turf_id = ? AND faction_id = ?
                ")->execute([$drain, $char['turf_id'], $targetFactionId]);

                // LSPD gains exactly what was drained
                if ($actualDrain > 0) {
                    $pdo->prepare("
                        UPDATE turf_control SET control_percent = control_percent + ?
                        WHERE turf_id = ? AND faction_id = ?
                    ")->execute([$actualDrain, $char['turf_id'], $char['faction_id']]);
                }

                normalise_turf($pdo, $char['turf_id']);

                $pdo->prepare("
                    UPDATE turfs SET heat = GREATEST(heat - 2, 0), heat_updated_at = NOW() WHERE id = ?
                ")->execute([$char['turf_id']]);

                $pdo->prepare("
                    UPDATE characters
                    SET money = money + ?, xp = LEAST(xp + 12, ?),
                        energy = energy - 20, food = GREATEST(food - 3, 0),
                        last_action = ?
                    WHERE id = ?
                ")->execute([$money, $xpNeeded, $now, $char['id']]);

                $pdo->commit();

                $message = "Targeted {$targetFaction['name']} — influence reduced by {$drain}%. +\${$money}";

                game_log('target_faction', 'success', 'Faction targeted', [
                    'target' => $targetFactionId, 'drain' => $drain, 'turf_id' => $char['turf_id']
                ]);
            }
        }

        // --------------------------------------------------
        // LOCKDOWN — spike heat to disable criminal actions
        // --------------------------------------------------
        if ($action === 'lockdown') {

            if ($onCooldown) {
                $remaining = $pdCooldown - ($now - $char['last_action']);
                $message = "Wait {$remaining}s before acting again.";
            } elseif ($turf && $turf['heat'] >= 85) {
                $message = "This area is already near or under lockdown.";
            } elseif (!has_energy($char, 30)) {
                $message = "Not enough energy.";
            } elseif ($char['xp'] >= $xpNeeded) {
                $message = "You need to level up before gaining more XP.";
            } else {

                $heatSpike = 25 + (int)floor($char['intelligence'] / 8);
                $money     = rand(150, 250);

                if ($char['food'] <= 0)       $money = (int)($money * 0.6);
                elseif ($char['food'] < 25)   $money = (int)($money * 0.75);
                elseif ($char['food'] < 50)   $money = (int)($money * 0.9);

                $pdo->prepare("
                    UPDATE turfs SET heat = LEAST(heat + ?, 100), heat_updated_at = NOW() WHERE id = ?
                ")->execute([$heatSpike, $char['turf_id']]);

                $pdo->prepare("
                    UPDATE characters
                    SET money = money + ?, xp = LEAST(xp + 15, ?),
                        energy = energy - 30, food = GREATEST(food - 3, 0),
                        last_action = ?
                    WHERE id = ?
                ")->execute([$money, $xpNeeded, $now, $char['id']]);

                $message = "Lockdown initiated. Heat spiked by +{$heatSpike}. +\${$money}";

                game_log('lockdown', 'success', 'Lockdown initiated', [
                    'heat_spike' => $heatSpike, 'turf_id' => $char['turf_id']
                ]);
            }
        }

        // --------------------------------------------------
        // INTEL SWEEP — reveal activity + minor pressure
        // --------------------------------------------------
        if ($action === 'intel_sweep') {

            if ($onCooldown) {
                $remaining = $pdCooldown - ($now - $char['last_action']);
                $message = "Wait {$remaining}s before acting again.";
            } elseif (!has_energy($char, $pdEnergy)) {
                $message = "Not enough energy.";
            } elseif ($char['xp'] >= $xpNeeded) {
                $message = "You need to level up before gaining more XP.";
            } else {

                $money = rand(100, 200) + ($char['intelligence'] * 3);

                if ($char['food'] <= 0)       $money = (int)($money * 0.6);
                elseif ($char['food'] < 25)   $money = (int)($money * 0.75);
                elseif ($char['food'] < 50)   $money = (int)($money * 0.9);

                // Reveal recent faction activity
                $stmtIntel = $pdo->prepare("
                    SELECT f.name, f.color, COUNT(*) as actions
                    FROM turf_activity ta
                    JOIN factions f ON f.id = ta.faction_id
                    WHERE ta.turf_id = ? AND ta.action_time > ? AND f.type = 'gang'
                    GROUP BY ta.faction_id
                    ORDER BY actions DESC
                ");
                $stmtIntel->execute([$char['turf_id'], $now - 300]);
                $intelRows = $stmtIntel->fetchAll();

                // Minor drain on all gangs
                $pdo->beginTransaction();

                $pdo->prepare("
                    UPDATE turf_control tc
                    JOIN factions f ON f.id = tc.faction_id
                    SET tc.control_percent = GREATEST(tc.control_percent - 1, 0)
                    WHERE tc.turf_id = ? AND f.type = 'gang'
                ")->execute([$char['turf_id']]);

                $pdo->prepare("
                    UPDATE turfs SET heat = GREATEST(heat - 1, 0), heat_updated_at = NOW() WHERE id = ?
                ")->execute([$char['turf_id']]);

                $pdo->prepare("
                    UPDATE characters
                    SET money = money + ?, xp = LEAST(xp + 8, ?),
                        energy = energy - ?, food = GREATEST(food - 1, 0),
                        last_action = ?
                    WHERE id = ?
                ")->execute([$money, $xpNeeded, $pdEnergy, $now, $char['id']]);

                normalise_turf($pdo, $char['turf_id']);
                $pdo->commit();

                if (empty($intelRows)) {
                    $intel = "No gang activity detected in the last 5 minutes.";
                } else {
                    $parts = [];
                    foreach ($intelRows as $row) {
                        $parts[] = "{$row['name']} ({$row['actions']} actions)";
                    }
                    $intel = "Intel: " . implode(', ', $parts) . ".";
                }

                $message = "Intel sweep complete. +\${$money} — {$intel}";

                game_log('intel_sweep', 'success', 'Intel sweep', [
                    'turf_id' => $char['turf_id']
                ]);
            }
        }
    }

    // Reload character after action
    $stmt->execute([user_id(), current_city_id()]);
    $char = $stmt->fetch();

    $xpNeeded = xp_required($char['level']);

    while ($char['xp'] >= $xpNeeded) {

        $char['xp'] -= $xpNeeded;
        $char['level']++;

        $xpNeeded = xp_required($char['level']);
    }

    if ($char['level'] > $oldLevel) {
        $_SESSION['flash'] = "🎉 You leveled up!";
    }

    $pdo->prepare("
        UPDATE characters
        SET level = ?, xp = ?
        WHERE id = ?
    ")->execute([$char['level'], $char['xp'], $char['id']]);

    // Reload turf data after action
    if ($char['turf_id']) {

        $stmtTurf->execute([$char['turf_id']]);
        $turf = $stmtTurf->fetch();

        $stmtControl->execute([$char['turf_id']]);
        $turfControl = $stmtControl->fetchAll();
    }
    
    // =========================
    // RE-CALCULATE OWNERSHIP + WAR STATE
    // =========================
    $ownerFactionId = $turf ? $turf['faction_id'] : null;
    $topFactionId = !empty($turfControl) ? $turfControl[0]['faction_id'] : null;

    $canStartWar = false;

    if (
        $char['turf_id'] &&
        $char['faction_type'] === 'gang' &&
        !$activeWar &&
        !$warCooldownActive &&
        $topFactionId &&
        $ownerFactionId !== null &&
        $char['faction_id'] == $topFactionId &&
        $char['faction_id'] != $ownerFactionId
    ) {
        $canStartWar = true;
    }

    // AJAX response — return JSON and exit before rendering HTML
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

        $levelUp = isset($oldLevel) && $char['level'] > $oldLevel;

        $jsonTurfControl = [];
        foreach ($turfControl as $r) {
            $jsonTurfControl[] = [
                'faction_id'      => (int)$r['faction_id'],
                'control_percent' => (int)$r['control_percent'],
            ];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'message'      => $message ?? '',
            'level_up'     => $levelUp,
            'redirect'     => ($action === 'start_war') ? 'war.php' : null,
            'char'         => [
                'level'      => $char['level'],
                'xp'         => $char['xp'],
                'xp_needed'  => xp_required($char['level']),
                'money'      => $char['money'],
                'energy'     => $char['energy'],
                'energy_max' => $char['energy_max'],
                'health'     => $char['health'],
                'health_max' => $char['health_max'],
            ],
            'turf_control' => $jsonTurfControl,
            'heat'   => $turf ? (int)$turf['heat'] : null,
            'weapon' => [
                'id'               => (int)($char['weapon_id'] ?? 0),
                'name'             => $char['weapon_name'] ?? 'Fists',
                'war_points_bonus' => (int)($char['war_points_bonus'] ?? 0),
                'kill_chance_bonus'=> (int)($char['kill_chance_bonus'] ?? 0),
            ],
        ]);
        exit;
    }
}

$gameMainClass = 'is-actions';
require_once 'templates/header.php';
?>
<?php
$actionEnergyCost = max(6, 10 - (int)floor($char['endurance'] / 12));
$hasFood = false;
foreach ($features as $f) {
    if (in_array($f, ['cluckin_bell', 'burger_shot', 'pizza'], true)) { $hasFood = true; break; }
}
$hasAmmuNation = in_array('ammu_nation', $features);
$hasHospital   = in_array('hospital', $features);
?>

<?php if (!empty($_SESSION['flash'])): ?>
<div class="actions-alerts">
    <div class="p-2.5 rounded-lg text-xs font-semibold bg-green-900/30 border border-green-700 text-green-300">
        <?= e($_SESSION['flash']) ?>
    </div>
</div>
<?php unset($_SESSION['flash']); endif; ?>

<div id="action-message" class="actions-alerts hidden">
    <div id="action-message-bar" class="p-2.5 rounded-lg text-xs font-semibold"></div>
</div>

<?php if ($turf && $turf['heat'] >= 90): ?>
<div class="actions-alerts">
    <div class="p-2.5 rounded-lg text-xs font-semibold bg-red-900/40 border border-red-600 text-red-200">
        🚫 LOCKDOWN ACTIVE — Criminal actions are disabled.
    </div>
</div>
<?php elseif ($turf && $turf['heat'] >= 70): ?>
<div class="actions-alerts">
    <div class="p-2.5 rounded-lg text-xs font-semibold bg-red-900/30 border border-red-700 text-red-300">
        ⚠️ Heavy police attention — criminal actions are significantly harder.
    </div>
</div>
<?php endif; ?>

<?php if ($activeWar): ?>
<div class="actions-alerts">
    <div class="p-2.5 rounded-lg text-xs font-semibold bg-red-800/40 border border-red-600 text-red-300">
        ⚔️ TURF WAR ACTIVE — Influence actions disabled.
        <a href="war.php" class="underline ml-1">View War →</a>
    </div>
</div>
<?php endif; ?>

    <div class="actions-grid">

    <!-- LEFT: Train + Crime/LSPD -->
    <div class="actions-left">

        <!-- TRAIN -->
        <div class="panel p-4 rounded-xl">
            <h2 class="panel-title">🏋️ Train</h2>
            <p class="text-zinc-400 text-xs mb-3">Improve your stats. Intelligence helps crime; Strength helps combat.</p>
            <form method="post" data-ajax="true">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="train">
                <select name="stat" class="input w-full mb-2 text-sm">
                    <option value="strength">Strength</option>
                    <option value="intelligence">Intelligence</option>
                    <option value="endurance">Endurance</option>
                    <option value="charisma">Charisma</option>
                </select>
                <button class="action-btn w-full py-2 rounded font-semibold text-sm
                    <?= $char['xp'] >= $xpNeeded ? 'bg-zinc-700 cursor-not-allowed' : 'bg-orange-600 hover:bg-orange-500' ?>"
                    <?= $char['xp'] >= $xpNeeded ? 'disabled' : '' ?>>
                    Train (<?= $actionEnergyCost ?> Energy)
                </button>
            </form>
        </div>

        <?php if ($char['faction_type'] === 'gang'): ?>
        <!-- CRIME -->
        <div class="panel p-4 rounded-xl">
            <h2 class="panel-title">🔫 Crime</h2>
            <div class="space-y-3">
                <form method="post" data-ajax="true">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="run_drugs">
                    <button class="action-btn w-full py-2 rounded font-semibold text-sm
                        <?= ($turf && $turf['heat'] >= 90) || $char['xp'] >= $xpNeeded || $activeWar ? 'bg-zinc-700 cursor-not-allowed' : 'bg-green-600 hover:bg-green-500' ?>"
                        <?= ($turf && $turf['heat'] >= 90) || $char['xp'] >= $xpNeeded || $activeWar ? 'disabled' : '' ?>>
                        <?= $activeWar ? 'LOCKED (War)' : (($turf && $turf['heat'] >= 90) ? 'LOCKED (Lockdown)' : "Run Drugs ({$actionEnergyCost} Energy)") ?>
                    </button>
                    <p class="text-zinc-500 text-xs mt-1">Earn cash + XP. Intelligence improves success.</p>
                </form>
                <form method="post" data-ajax="true">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="crime">
                    <button class="action-btn w-full py-2 rounded font-semibold text-sm
                        <?= ($turf && $turf['heat'] >= 90) || $char['xp'] >= $xpNeeded || $activeWar ? 'bg-zinc-700 cursor-not-allowed' : 'bg-red-600 hover:bg-red-500' ?>"
                        <?= ($turf && $turf['heat'] >= 90) || $char['xp'] >= $xpNeeded || $activeWar ? 'disabled' : '' ?>>
                        <?= $activeWar ? 'LOCKED (War)' : "Commit Crime ({$actionEnergyCost} Energy)" ?>
                    </button>
                    <p class="text-zinc-500 text-xs mt-1">Higher risk, higher reward.</p>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- LSPD OPS -->
        <div class="panel p-4 rounded-xl">
            <h2 class="panel-title">🚓 LSPD Ops</h2>
            <?php
                $now2 = time();
                $pdOnCooldown = $char['last_action'] > 0 && ($now2 - $char['last_action']) < 30;
                $pdCooldownRemaining = $pdOnCooldown ? (30 - ($now2 - $char['last_action'])) : 0;
                $pdEnergyCost = max(6, 10 - (int)floor($char['endurance'] / 12));
                $locked = $char['xp'] >= $xpNeeded || $activeWar;
            ?>
            <?php if ($pdOnCooldown): ?>
                <p class="text-xs text-zinc-400 mb-2">⏱ Cooldown: <?= $pdCooldownRemaining ?>s remaining</p>
            <?php endif; ?>
            <div class="space-y-2">
                <form method="post" data-ajax="true">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="suppress_turf">
                    <button class="action-btn w-full py-1.5 rounded font-semibold text-xs
                        <?= ($locked || $pdOnCooldown) ? 'bg-zinc-700 cursor-not-allowed' : 'bg-blue-700 hover:bg-blue-600' ?>"
                        <?= ($locked || $pdOnCooldown) ? 'disabled' : '' ?>>
                        Suppress Turf (<?= $pdEnergyCost ?> Energy)
                    </button>
                    <p class="text-zinc-500 text-xs mt-0.5 mb-1.5">Drain all gang influence. Police gain foothold.</p>
                </form>
                <form method="post" data-ajax="true">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="target_faction">
                    <select name="target_faction_id" class="input w-full mb-1 text-xs"
                        <?= ($locked || $pdOnCooldown) ? 'disabled' : '' ?>>
                        <?php foreach ($turfControl as $tc): ?>
                            <?php if ($tc['faction_id'] != $char['faction_id'] && $tc['control_percent'] > 0): ?>
                                <option value="<?= $tc['faction_id'] ?>"><?= e($tc['name']) ?> (<?= $tc['control_percent'] ?>%)</option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <button class="action-btn w-full py-1.5 rounded font-semibold text-xs
                        <?= ($locked || $pdOnCooldown) ? 'bg-zinc-700 cursor-not-allowed' : 'bg-indigo-700 hover:bg-indigo-600' ?>"
                        <?= ($locked || $pdOnCooldown) ? 'disabled' : '' ?>>
                        Target Faction (20 Energy)
                    </button>
                    <p class="text-zinc-500 text-xs mt-0.5 mb-1.5">Heavy influence drain on one gang.</p>
                </form>
                <?php $lockdownBlocked = $locked || $pdOnCooldown || ($turf && $turf['heat'] >= 85); ?>
                <form method="post" data-ajax="true">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="lockdown">
                    <button class="action-btn w-full py-1.5 rounded font-semibold text-xs
                        <?= $lockdownBlocked ? 'bg-zinc-700 cursor-not-allowed' : 'bg-red-800 hover:bg-red-700' ?>"
                        <?= $lockdownBlocked ? 'disabled' : '' ?>>
                        <?= ($turf && $turf['heat'] >= 85) ? 'Lockdown (Already Active)' : 'Lockdown (30 Energy)' ?>
                    </button>
                    <p class="text-zinc-500 text-xs mt-0.5 mb-1.5">Spike heat to disable criminal activity.</p>
                </form>
                <form method="post" data-ajax="true">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="intel_sweep">
                    <button class="action-btn w-full py-1.5 rounded font-semibold text-xs
                        <?= ($locked || $pdOnCooldown) ? 'bg-zinc-700 cursor-not-allowed' : 'bg-teal-700 hover:bg-teal-600' ?>"
                        <?= ($locked || $pdOnCooldown) ? 'disabled' : '' ?>>
                        Intel Sweep (<?= $pdEnergyCost ?> Energy)
                    </button>
                    <p class="text-zinc-500 text-xs mt-0.5">Reveal gang activity. Minor influence drain.</p>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- RIGHT: Utility + Turf -->
    <div class="actions-right">

        <?php if ($hasFood): ?>
        <?php
            $displayFoodPrice = 0;
            if (in_array('burger_shot', $features))      $displayFoodPrice = 150;
            elseif (in_array('cluckin_bell', $features)) $displayFoodPrice = 100;
            elseif (in_array('pizza', $features))        $displayFoodPrice = 80;
            if ($ownsThisTurf) $displayFoodPrice = (int)round($displayFoodPrice * (1 - $ownerDiscount));
        ?>
        <div class="panel p-4 rounded-xl">
            <div class="flex items-center justify-between mb-2">
                <h2 class="panel-title" style="margin:0">🍔 Eat Food</h2>
                <?php if ($ownsThisTurf): ?><span class="text-green-400 text-xs font-semibold">20% discount</span><?php endif; ?>
            </div>
            <form method="post" data-ajax="true">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="eat">
                <button class="action-btn w-full py-2 rounded font-semibold text-sm
                    <?= $char['food'] >= 100 ? 'bg-zinc-700 cursor-not-allowed' : 'bg-orange-600 hover:bg-orange-500' ?>"
                    <?= $char['food'] >= 100 ? 'disabled' : '' ?>
                >
                    <?= $char['food'] >= 100
                        ? 'Full (100/100)'
                        : "Eat (\${$displayFoodPrice}, 2 Energy)" ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

        <?php if ($hasHospital): ?>
        <?php
            $displayHealCost = $ownsThisTurf ? (int)round(500 * (1 - $ownerDiscount)) : 500;
            $hpFull      = $char['health'] >= $char['health_max'];
            $cantAfford  = $char['money'] < $displayHealCost;
            $btnDisabled = $hpFull || $cantAfford;
            $btnLabel    = $hpFull ? 'Already at full health'
                : ($cantAfford ? "Cannot afford (\${$displayHealCost})" : "Treat (\${$displayHealCost})");
        ?>
        <div class="panel p-4 rounded-xl">
            <div class="flex items-center justify-between mb-2">
                <h2 class="panel-title" style="margin:0">🏥 Hospital</h2>
                <?php if ($ownsThisTurf): ?><span class="text-green-400 text-xs font-semibold">20% discount</span><?php endif; ?>
            </div>
            <p class="text-zinc-400 text-xs mb-2">HP: <span class="text-white font-semibold"><?= $char['health'] ?>/<?= $char['health_max'] ?></span></p>
            <form method="post" data-ajax="true">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="hospital_heal">
                <button class="action-btn w-full py-2 rounded font-semibold text-sm
                    <?= $btnDisabled ? 'bg-zinc-700 cursor-not-allowed' : 'bg-green-700 hover:bg-green-600' ?>"
                    <?= $btnDisabled ? 'disabled' : '' ?>>
                    <?= $btnLabel ?>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($turf): ?>
        <?php
            $heat = $turf['heat'];
            $heatLabel = 'Low'; $heatClass = 'text-zinc-400';
            if      ($heat >= 90) { $heatLabel = 'CRITICAL'; $heatClass = 'text-red-500'; }
            elseif  ($heat >= 70) { $heatLabel = 'HIGH';     $heatClass = 'text-red-400'; }
            elseif  ($heat >= 40) { $heatLabel = 'ELEVATED'; $heatClass = 'text-yellow-400'; }
        ?>
        <div class="panel p-4 rounded-xl">
            <div class="flex items-center justify-between mb-2">
                <h2 class="panel-title" style="margin:0">📍 <?= e($turf['name']) ?></h2>
                <span class="text-xs <?= $heatClass ?>" id="turf-heat-display" data-heat="<?= $heat ?>">
                    🔥 <span id="turf-heat-value"><?= $heat ?></span>/100
                    <span id="turf-heat-label">(<?= $heatLabel ?>)</span>
                </span>
            </div>
            <div class="space-y-1.5" id="turf-control-bars">
                <?php foreach ($turfControl as $row): ?>
                    <?php $isTop = ($row === $turfControl[0]); ?>
                    <div class="<?= $isTop ? 'font-semibold text-white' : 'text-zinc-400' ?>"
                         id="control-faction-<?= $row['faction_id'] ?>">
                        <div class="flex justify-between text-xs mb-0.5">
                            <span><?= $isTop ? '👑 ' : '' ?><?= e($row['name']) ?></span>
                            <span class="control-pct"><?= $row['control_percent'] ?>%</span>
                        </div>
                        <div class="w-full bg-zinc-800 rounded h-1.5">
                            <div class="h-1.5 rounded control-bar transition-all duration-500"
                                 style="width:<?= $row['control_percent'] ?>%;background:<?= e($row['color']) ?>"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- CAPTURE / WAR -->
        <div class="panel p-4 rounded-xl">
            <h2 class="panel-title">🗺️ Turf Control</h2>
            <form method="post" data-ajax="true">
                <?= csrf_input() ?>
                <?php if ($activeWar && $activeWar['turf_id'] == $char['turf_id']): ?>
                    <a href="war.php" class="action-btn block w-full text-center py-2 rounded font-semibold text-sm bg-red-600 hover:bg-red-500">
                        View Turf War
                    </a>
                <?php elseif ($warCooldownActive): ?>
                    <button class="w-full py-2 rounded font-semibold text-sm bg-zinc-700 cursor-not-allowed" disabled>
                        War Cooldown Active
                    </button>
                <?php elseif ($canStartWar): ?>
                    <input type="hidden" name="action" value="start_war">
                    <button class="action-btn w-full py-2 rounded font-semibold text-sm bg-red-700 hover:bg-red-600">
                        Start Turf War
                    </button>
                <?php elseif ($char['faction_type'] === 'gang'): ?>
                    <?php $captureCost = 15 + ($char['capture_streak'] * 5); ?>
                    <input type="hidden" name="action" value="capture">
                    <button class="action-btn w-full py-2 rounded font-semibold text-sm
                        <?= $char['xp'] >= $xpNeeded || $activeWar ? 'bg-zinc-700 cursor-not-allowed' : 'bg-purple-600 hover:bg-purple-500' ?>"
                        <?= $char['xp'] >= $xpNeeded || $activeWar ? 'disabled' : '' ?>>
                        Capture Turf (<?= $captureCost ?> Energy)
                    </button>
                <?php else: ?>
                    <button class="w-full py-2 rounded font-semibold text-sm bg-zinc-700 cursor-not-allowed" disabled>
                        Law Enforcement Cannot Capture Turf
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <!-- WEAPONS (gang only) -->
        <?php if ($char['faction_type'] !== 'law'): ?>
        <div class="panel p-4 rounded-xl">
            <?php if ($hasAmmuNation): ?>
                <h2 class="panel-title" style="display:flex;align-items:center;gap:5px;">
                    <img src="assets/img/ammu_nation.gif" alt="Ammu-Nation" style="height:18px;">
                    Ammu-Nation
                </h2>
            <?php else: ?>
                <h2 class="panel-title">🔫 Weapons</h2>
            <?php endif; ?>
            <p class="text-zinc-400 text-xs mb-3">
                Equipped: <span id="equipped-weapon-name" class="text-white font-semibold"><?= e($char['weapon_name'] ?? 'Fists') ?></span>
                &nbsp;+<span id="equipped-war-pts"><?= (int)($char['war_points_bonus'] ?? 0) ?></span>pts
                &nbsp;+<span id="equipped-kill-chance"><?= (int)($char['kill_chance_bonus'] ?? 0) ?></span>%
            </p>
            <?php if ($hasAmmuNation): ?>
                <?php
                    $stmtWeapons = $pdo->prepare("SELECT * FROM weapons WHERE city_id = ? ORDER BY price ASC");
                    $stmtWeapons->execute([current_city_id()]);
                    $allWeapons  = $stmtWeapons->fetchAll();
                ?>
                <?php if ($ownsThisTurf): ?>
                    <p class="text-green-400 text-xs mb-2 font-semibold">20% owner discount applied.</p>
                <?php endif; ?>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach ($allWeapons as $w): ?>
                        <?php
                            $isEquipped   = ((int)$w['id'] === (int)$char['weapon_id']);
                            $displayPrice = $ownsThisTurf ? (int)round($w['price'] * (1 - $ownerDiscount)) : (int)$w['price'];
                            $canAfford    = ($char['money'] >= $displayPrice);
                        ?>
                        <div class="bg-zinc-800 border <?= $isEquipped ? 'border-orange-500' : 'border-zinc-700' ?> rounded-lg p-2.5 flex flex-col gap-1.5 weapon-card"
                             data-weapon-id="<?= $w['id'] ?>">
                            <div class="font-semibold text-xs weapon-card-name <?= $isEquipped ? 'text-orange-400' : 'text-white' ?>">
                                <?= e($w['name']) ?>
                                <?php if ($isEquipped): ?><span class="weapon-equipped-tag ml-1">(Eq)</span><?php endif; ?>
                            </div>
                            <div class="text-xs text-zinc-400 leading-tight"><?= e($w['description']) ?></div>
                            <div class="text-xs text-zinc-300">+<?= $w['war_points_bonus'] ?>pts +<?= $w['kill_chance_bonus'] ?>%</div>
                            <?php if ($isEquipped): ?>
                                <div class="weapon-card-status mt-auto text-xs text-center text-orange-400 font-semibold py-0.5">Equipped</div>
                            <?php elseif ($w['price'] === 0): ?>
                                <form method="post" data-ajax="true">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="buy_weapon">
                                    <input type="hidden" name="weapon_id" value="<?= $w['id'] ?>">
                                    <button class="w-full mt-auto text-xs py-1 rounded bg-zinc-700 hover:bg-zinc-600 font-semibold">Equip (Free)</button>
                                </form>
                            <?php elseif ($canAfford): ?>
                                <form method="post" data-ajax="true">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="buy_weapon">
                                    <input type="hidden" name="weapon_id" value="<?= $w['id'] ?>">
                                    <button class="w-full mt-auto text-xs py-1 rounded bg-orange-600 hover:bg-orange-500 font-semibold">
                                        Buy $<?= number_format($displayPrice) ?>
                                        <?php if ($ownsThisTurf && $w['price'] > 0): ?>
                                            <span class="line-through text-orange-200/50 ml-1">$<?= number_format($w['price']) ?></span>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="mt-auto text-xs text-center text-zinc-500 py-0.5">$<?= number_format($displayPrice) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-zinc-500 text-sm">Visit an Ammu-Nation to browse and purchase weapons.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div><!-- .actions-right -->

</div><!-- .actions-grid -->

<script>
(function () {
    const msgBox    = document.getElementById('action-message');
    const msgBar    = document.getElementById('action-message-bar');

    function showMessage(text, success) {
        if (!msgBox || !msgBar) return;
        msgBar.textContent = text;
        msgBar.className = 'p-2.5 rounded-lg text-xs font-semibold ' + (
            success
                ? 'bg-green-900/30 border border-green-700 text-green-300'
                : 'bg-zinc-800 border border-zinc-700 text-zinc-200'
        );
        msgBox.classList.remove('hidden');
        clearTimeout(msgBox._timer);
        msgBox._timer = setTimeout(() => msgBox.classList.add('hidden'), 5000);
    }

    function updateWeapon(w) {
        const nameEl = document.getElementById('equipped-weapon-name');
        const ptsEl  = document.getElementById('equipped-war-pts');
        const killEl = document.getElementById('equipped-kill-chance');
        if (nameEl) nameEl.textContent = w.name;
        if (ptsEl)  ptsEl.textContent  = w.war_points_bonus;
        if (killEl) killEl.textContent = w.kill_chance_bonus;

        document.querySelectorAll('.weapon-card').forEach(function(card) {
            const isNow = parseInt(card.dataset.weaponId) === w.id;
            card.classList.toggle('border-orange-500', isNow);
            card.classList.toggle('border-zinc-700',  !isNow);
            const nameDiv = card.querySelector('.weapon-card-name');
            if (nameDiv) {
                nameDiv.classList.toggle('text-orange-400', isNow);
                nameDiv.classList.toggle('text-white',      !isNow);
                let tag = nameDiv.querySelector('.weapon-equipped-tag');
                if (isNow && !tag) {
                    tag = document.createElement('span');
                    tag.className = 'weapon-equipped-tag ml-1';
                    tag.textContent = '(Eq)';
                    nameDiv.appendChild(tag);
                } else if (!isNow && tag) {
                    tag.remove();
                }
            }
            const statusDiv = card.querySelector('.weapon-card-status');
            if (isNow && !statusDiv) {
                const d = document.createElement('div');
                d.className = 'weapon-card-status mt-auto text-xs text-center text-orange-400 font-semibold py-0.5';
                d.textContent = 'Equipped';
                card.appendChild(d);
            } else if (!isNow && statusDiv) {
                statusDiv.remove();
            }
        });
    }

    function updateHeat(heat) {
        const display = document.getElementById('turf-heat-display');
        const valEl   = document.getElementById('turf-heat-value');
        const lblEl   = document.getElementById('turf-heat-label');
        if (!display || !valEl || !lblEl) return;
        valEl.textContent = heat;
        let label = 'Low', cls = 'text-zinc-400';
        if      (heat >= 90) { label = 'CRITICAL'; cls = 'text-red-500'; }
        else if (heat >= 70) { label = 'HIGH';     cls = 'text-red-400'; }
        else if (heat >= 40) { label = 'ELEVATED'; cls = 'text-yellow-400'; }
        lblEl.textContent = '(' + label + ')';
        display.className = 'text-xs ' + cls;
    }

    function updateTurfControl(controls) {
        controls.forEach(function(c) {
            const row = document.getElementById('control-faction-' + c.faction_id);
            if (!row) return;
            const pct = row.querySelector('.control-pct');
            const bar = row.querySelector('.control-bar');
            if (pct) pct.textContent = c.control_percent + '%';
            if (bar) bar.style.width = c.control_percent + '%';
        });
    }

    function updateStats(char) {
        const el = (id) => document.getElementById(id);
        if (el('topbar-cash'))   el('topbar-cash').textContent   = '$' + char.money.toLocaleString();
        if (el('topbar-energy')) el('topbar-energy').textContent = char.energy + '/' + char.energy_max;
        if (el('topbar-xp-val')) el('topbar-xp-val').textContent = char.xp + '/' + char.xp_needed;
        if (el('topbar-level'))  el('topbar-level').textContent  = 'Lv.' + char.level;
        if (el('topbar-hp-val')) el('topbar-hp-val').textContent = char.health + '/' + char.health_max;
    }

    document.querySelectorAll('form[data-ajax="true"]').forEach(function (form) {
        form.addEventListener('submit', async function (e) {
            const action = (form.querySelector('[name="action"]') || {}).value;
            if (action === 'start_war') return;
            e.preventDefault();

            const btn = form.querySelector('button[type="submit"], button:not([type])');
            if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }

            try {
                const res  = await fetch('actions.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form)
                });
                const data = await res.json();
                if (data.redirect) { window.location.href = data.redirect; return; }
                if (data.message)      showMessage(data.message, !data.message.toLowerCase().includes('fail') && !data.message.toLowerCase().includes('not enough') && !data.message.toLowerCase().includes('wait'));
                if (data.char)         updateStats(data.char);
                if (data.level_up)     showMessage('🎉 You leveled up!', true);
                if (data.turf_control) updateTurfControl(data.turf_control);
                if (data.heat !== null) updateHeat(data.heat);
                if (data.weapon)       updateWeapon(data.weapon);
            } catch (err) {
                console.error(err);
            } finally {
                if (btn) { btn.disabled = false; btn.style.opacity = ''; }
            }
        });
    });
})();
</script>

<?php require_once 'templates/footer.php'; ?>
