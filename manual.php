<?php
require_once 'includes/bootstrap.php';
require_login();

$char = get_character();

if (!$char) {
    redirect('character-create.php');
}

require_once 'templates/header.php';
?>

<style>
.manual-sidebar a {
    display: block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    color: #a1a1aa;
    transition: all 0.15s;
    border-left: 2px solid transparent;
}
.manual-sidebar a:hover,
.manual-sidebar a.active {
    color: #fff;
    border-left-color: var(--accent);
    background: rgba(255,255,255,0.04);
}
.manual-sidebar .sidebar-group {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #52525b;
    padding: 12px 12px 4px;
}
.section-anchor {
    scroll-margin-top: 90px;
}
.callout {
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
}
.callout-tip    { background: rgba(34,197,94,0.08);  border: 1px solid rgba(34,197,94,0.25);  color: #86efac; }
.callout-warn   { background: rgba(234,179,8,0.08);  border: 1px solid rgba(234,179,8,0.25);  color: #fde68a; }
.callout-danger { background: rgba(239,68,68,0.08);  border: 1px solid rgba(239,68,68,0.3);   color: #fca5a5; }
.callout-info   { background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.25); color: #c7d2fe; }
.stat-table { width: 100%; font-size: 13px; border-collapse: collapse; }
.stat-table th { text-align: left; padding: 6px 10px; color: #71717a; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 1px solid rgba(255,255,255,0.07); }
.stat-table td { padding: 8px 10px; border-bottom: 1px solid rgba(255,255,255,0.04); color: #d4d4d8; vertical-align: top; }
.stat-table tr:last-child td { border-bottom: none; }
.stat-table tr:hover td { background: rgba(255,255,255,0.02); }
.badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.badge-gang { background: rgba(239,68,68,0.15); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
.badge-law  { background: rgba(59,130,246,0.15); color: #93c5fd; border: 1px solid rgba(59,130,246,0.3); }
.badge-both { background: rgba(161,161,170,0.1); color: #d4d4d8; border: 1px solid rgba(255,255,255,0.1); }
.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}
.section-header h2 {
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0;
}
.section-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: var(--accent-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
</style>

<div class="max-w-7xl mx-auto px-4 pb-12">

    <!-- PAGE TITLE -->
    <div class="py-6 mb-2">
        <h1 class="text-4xl font-bold">Game Manual</h1>
        <p class="text-zinc-400 mt-1">Everything you need to dominate Los Santos.</p>
    </div>

    <div class="flex gap-6 items-start">

        <!-- SIDEBAR -->
        <aside class="hidden lg:block w-52 flex-shrink-0 sticky top-24 manual-sidebar">

            <div class="panel rounded-xl py-3">
                <div class="sidebar-group">Getting Started</div>
                <a href="#overview">The City</a>
                <a href="#factions">Factions</a>
                <a href="#character">Character & Stats</a>

                <div class="sidebar-group">Core Systems</div>
                <a href="#energy">Energy</a>
                <a href="#food">Food & Hunger</a>
                <a href="#leveling">Leveling Up</a>
                <a href="#movement">Movement</a>

                <div class="sidebar-group">Actions</div>
                <a href="#gang-actions">Gang Actions</a>
                <a href="#lspd-actions">LSPD Operations</a>
                <a href="#weapons">Weapons</a>

                <div class="sidebar-group">Territory</div>
                <a href="#heat">Heat System</a>
                <a href="#turf-control">Turf Control</a>
                <a href="#turf-wars">Turf Wars</a>

                <div class="sidebar-group">Other</div>
                <a href="#hospital">Hospital</a>
                <a href="#chat">Chat</a>

                <div class="sidebar-group">Strategy</div>
                <a href="#tips">Tips</a>
            </div>

        </aside>

        <!-- MAIN CONTENT -->
        <div class="flex-1 space-y-6 min-w-0">

            <!-- OVERVIEW -->
            <div class="panel p-6 rounded-xl section-anchor" id="overview">
                <div class="section-header">
                    <div class="section-icon">🏙️</div>
                    <h2>The City</h2>
                </div>

                <p class="text-zinc-300 text-sm leading-relaxed mb-4">
                    The Streets: Turf Wars is a browser-based gang strategy game set in Los Santos. You join a faction, move between districts, earn money, gain power, and fight to control the city — one turf at a time.
                </p>

                <p class="text-zinc-300 text-sm leading-relaxed mb-4">
                    Control is measured in <strong class="text-white">turf ownership</strong>. Each district on the map can be held by any faction. Taking territory requires consistent pressure — committing crimes, running drugs, capturing ground, and winning wars.
                </p>

                <div class="callout callout-info mt-4">
                    <strong>The goal:</strong> Your faction should own more of the city than anyone else. Check the Dominance page to see the current standings.
                </div>
            </div>

            <!-- FACTIONS -->
            <div class="panel p-6 rounded-xl section-anchor" id="factions">
                <div class="section-header">
                    <div class="section-icon">⚔️</div>
                    <h2>Factions</h2>
                </div>

                <p class="text-zinc-400 text-sm mb-4">Every player belongs to one of six factions. Gangs fight for territory through crime and warfare. LSPD fights to suppress them.</p>

                <table class="stat-table">
                    <thead>
                        <tr>
                            <th>Faction</th>
                            <th>Type</th>
                            <th>Playstyle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="font-semibold" style="color:#22c55e">Grove Street Families</span></td>
                            <td><span class="badge badge-gang">Gang</span></td>
                            <td>Territory-focused, strong in numbers</td>
                        </tr>
                        <tr>
                            <td><span class="font-semibold" style="color:#d93bf8">Ballas</span></td>
                            <td><span class="badge badge-gang">Gang</span></td>
                            <td>Aggressive pushers, high drug income</td>
                        </tr>
                        <tr>
                            <td><span class="font-semibold" style="color:#06b6d4">Varrios Los Aztecas</span></td>
                            <td><span class="badge badge-gang">Gang</span></td>
                            <td>Tactical, good at turf disruption</td>
                        </tr>
                        <tr>
                            <td><span class="font-semibold" style="color:#eab308">Vagos</span></td>
                            <td><span class="badge badge-gang">Gang</span></td>
                            <td>Fast movers, charisma-heavy earners</td>
                        </tr>
                        <tr>
                            <td><span class="font-semibold" style="color:#ef4444">Triads</span></td>
                            <td><span class="badge badge-gang">Gang</span></td>
                            <td>Disciplined, hard to dislodge from turf</td>
                        </tr>
                        <tr>
                            <td><span class="font-semibold" style="color:#3b82f6">LSPD</span></td>
                            <td><span class="badge badge-law">Law</span></td>
                            <td>Suppression and lockdown, counter-gang</td>
                        </tr>
                    </tbody>
                </table>

                <div class="callout callout-warn mt-4">
                    LSPD players cannot commit crimes or start turf wars. Their role is to make life harder for gangs — suppressing influence and locking down districts.
                </div>
            </div>

            <!-- CHARACTER & STATS -->
            <div class="panel p-6 rounded-xl section-anchor" id="character">
                <div class="section-header">
                    <div class="section-icon">📊</div>
                    <h2>Character & Stats</h2>
                </div>

                <p class="text-zinc-400 text-sm mb-5">Your character has four core attributes that affect nearly every system in the game. They increase by 1 each time you train that stat.</p>

                <table class="stat-table mb-6">
                    <thead>
                        <tr>
                            <th>Stat</th>
                            <th>What it does</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-orange-400 font-semibold">💪 Strength</td>
                            <td>
                                Increases war attack points by <code class="text-zinc-300 bg-zinc-800 px-1 rounded">+floor(STR / 5)</code> per hit.<br>
                                Increases turf capture base gain by <code class="text-zinc-300 bg-zinc-800 px-1 rounded">+floor(STR / 10)</code>.
                            </td>
                        </tr>
                        <tr>
                            <td class="text-blue-400 font-semibold">🧠 Intelligence</td>
                            <td>
                                Boosts crime success chance by <code class="text-zinc-300 bg-zinc-800 px-1 rounded">+1.25%</code> per point.<br>
                                Boosts drug run success by <code class="text-zinc-300 bg-zinc-800 px-1 rounded">+1.2%</code> per point.<br>
                                Improves LSPD operation effectiveness and payout.
                            </td>
                        </tr>
                        <tr>
                            <td class="text-green-400 font-semibold">🛡️ Endurance</td>
                            <td>
                                Reduces energy cost per action: <code class="text-zinc-300 bg-zinc-800 px-1 rounded">max(6, 10 − floor(END / 12))</code>.<br>
                                Makes you harder to kill in wars — each 6 endurance reduces enemy death roll by 1%.
                            </td>
                        </tr>
                        <tr>
                            <td class="text-pink-400 font-semibold">🗣️ Charisma</td>
                            <td>
                                Adds <code class="text-zinc-300 bg-zinc-800 px-1 rounded">+3</code> to crime payouts per point.<br>
                                Adds <code class="text-zinc-300 bg-zinc-800 px-1 rounded">+5</code> to drug run payouts per point.<br>
                                Adds <code class="text-zinc-300 bg-zinc-800 px-1 rounded">+floor(CHA / 15)</code> to turf capture influence.<br>
                                Improves patrol and LSPD payouts.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="callout callout-tip">
                    <strong>Early game tip:</strong> Train Endurance first to reduce energy costs — you'll get more actions per regen cycle. Once comfortable, branch into Strength or Intelligence depending on your playstyle.
                </div>
            </div>

            <!-- ENERGY -->
            <div class="panel p-6 rounded-xl section-anchor" id="energy">
                <div class="section-header">
                    <div class="section-icon">⚡</div>
                    <h2>Energy</h2>
                </div>

                <p class="text-zinc-400 text-sm mb-4">Energy is required for every action in the game. It regenerates automatically over time, but the rate depends entirely on how well-fed you are.</p>

                <h3 class="text-sm font-semibold text-zinc-300 mb-2 uppercase tracking-wider">Regeneration Rates</h3>
                <table class="stat-table mb-5">
                    <thead>
                        <tr><th>Food Level</th><th>Regen Rate</th></tr>
                    </thead>
                    <tbody>
                        <tr><td class="text-green-400">75 – 100 (Full)</td><td>1 energy every <strong>5 minutes</strong></td></tr>
                        <tr><td class="text-yellow-400">25 – 74 (Hungry)</td><td>1 energy every <strong>6.6 minutes</strong></td></tr>
                        <tr><td class="text-orange-400">1 – 24 (Starving)</td><td>1 energy every <strong>10 minutes</strong></td></tr>
                        <tr><td class="text-red-400">0 (Empty)</td><td><strong>No regeneration</strong></td></tr>
                    </tbody>
                </table>

                <h3 class="text-sm font-semibold text-zinc-300 mb-2 uppercase tracking-wider">Action Costs</h3>
                <table class="stat-table mb-5">
                    <thead>
                        <tr><th>Action</th><th>Base Cost</th><th>Reduced By</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Train</td><td>10</td><td>Endurance</td></tr>
                        <tr><td>Commit Crime</td><td>10</td><td>Endurance</td></tr>
                        <tr><td>Run Drugs</td><td>10</td><td>Endurance</td></tr>
                        <tr><td>Capture Turf</td><td>15 (+5 per streak)</td><td>Endurance</td></tr>
                        <tr><td>Eat Food</td><td>2</td><td>—</td></tr>
                        <tr><td>LSPD Suppress / Intel</td><td>10</td><td>Endurance</td></tr>
                        <tr><td>LSPD Target Faction</td><td>20</td><td>—</td></tr>
                        <tr><td>LSPD Lockdown</td><td>30</td><td>—</td></tr>
                    </tbody>
                </table>

                <div class="callout callout-info">
                    With max Endurance reduction, standard actions cost as little as <strong>6 energy</strong>. The Lockdown and Target Faction operations are fixed regardless of Endurance.
                </div>
            </div>

            <!-- FOOD -->
            <div class="panel p-6 rounded-xl section-anchor" id="food">
                <div class="section-header">
                    <div class="section-icon">🍔</div>
                    <h2>Food & Hunger</h2>
                </div>

                <p class="text-zinc-400 text-sm mb-4">Food represents your character's physical condition. It depletes with every action and must be topped up at food stores in the city.</p>

                <h3 class="text-sm font-semibold text-zinc-300 mb-2 uppercase tracking-wider">Food Drain Per Action</h3>
                <table class="stat-table mb-5">
                    <thead><tr><th>Action</th><th>Food Lost</th></tr></thead>
                    <tbody>
                        <tr><td>Train</td><td>−2</td></tr>
                        <tr><td>Commit Crime</td><td>−3</td></tr>
                        <tr><td>Run Drugs</td><td>−4</td></tr>
                        <tr><td>Capture Turf</td><td>−5</td></tr>
                        <tr><td>LSPD Operations</td><td>−1 to −3</td></tr>
                    </tbody>
                </table>

                <h3 class="text-sm font-semibold text-zinc-300 mb-2 uppercase tracking-wider">Hunger Effects</h3>
                <table class="stat-table mb-5">
                    <thead><tr><th>Food</th><th>Effect</th></tr></thead>
                    <tbody>
                        <tr><td class="text-green-400">75 – 100</td><td>Full performance, fastest energy regen</td></tr>
                        <tr><td class="text-yellow-400">50 – 74</td><td>Slower energy regen. Crime / drug XP slightly reduced</td></tr>
                        <tr><td class="text-orange-400">25 – 49</td><td>Significantly slower regen. −5% to crime / drug success</td></tr>
                        <tr><td class="text-red-400">1 – 24</td><td>Severely reduced regen. −10% to success. XP penalties</td></tr>
                        <tr><td class="text-red-600 font-bold">0</td><td>No regen. −20% to success. Health damage on each page load</td></tr>
                    </tbody>
                </table>

                <h3 class="text-sm font-semibold text-zinc-300 mb-2 uppercase tracking-wider">Food Stores</h3>
                <table class="stat-table mb-4">
                    <thead><tr><th>Store</th><th>Cost</th><th>Food Restored</th><th>Quality</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>🍕 Pizza Stack</td>
                            <td class="text-green-400">$80</td>
                            <td>+15 to +25</td>
                            <td class="text-zinc-500">Budget</td>
                        </tr>
                        <tr>
                            <td>🍗 Cluckin' Bell</td>
                            <td class="text-yellow-400">$100</td>
                            <td>+20 to +35</td>
                            <td class="text-zinc-400">Standard</td>
                        </tr>
                        <tr>
                            <td>🍔 Burger Shot</td>
                            <td class="text-orange-400">$150</td>
                            <td>+30 to +45</td>
                            <td class="text-green-400">Premium</td>
                        </tr>
                    </tbody>
                </table>

                <div class="callout callout-warn">
                    Food stores are only available in specific turfs. Check the Location panel on your Dashboard to see what's available where you are. If there's no store nearby, you'll need to move.
                </div>
            </div>

            <!-- LEVELING -->
            <div class="panel p-6 rounded-xl section-anchor" id="leveling">
                <div class="section-header">
                    <div class="section-icon">🏆</div>
                    <h2>Leveling Up</h2>
                </div>

                <p class="text-zinc-400 text-sm mb-4">XP is earned from training, crimes, drug runs, and capturing turfs. When you hit the XP threshold for your level, you level up.</p>

                <table class="stat-table mb-5">
                    <thead><tr><th>Level</th><th>XP Required</th></tr></thead>
                    <tbody>
                        <tr><td>1 → 2</td><td>100 XP</td></tr>
                        <tr><td>2 → 3</td><td>140 XP</td></tr>
                        <tr><td>3 → 4</td><td>180 XP</td></tr>
                        <tr><td>4 → 5</td><td>220 XP</td></tr>
                        <tr><td class="text-zinc-500">Level N → N+1</td><td class="text-zinc-500">100 + (N−1) × 40 XP</td></tr>
                    </tbody>
                </table>

                <h3 class="text-sm font-semibold text-zinc-300 mb-2 uppercase tracking-wider">XP per Action</h3>
                <table class="stat-table mb-5">
                    <thead><tr><th>Action</th><th>XP (Success)</th><th>XP (Fail)</th></tr></thead>
                    <tbody>
                        <tr><td>Train</td><td>+8 (less if hungry)</td><td>—</td></tr>
                        <tr><td>Commit Crime</td><td>+12</td><td>+4</td></tr>
                        <tr><td>Run Drugs</td><td>+12</td><td>+4</td></tr>
                        <tr><td>Capture Turf</td><td>+15 (less if hungry)</td><td>—</td></tr>
                        <tr><td>LSPD Suppress</td><td>+10</td><td>—</td></tr>
                        <tr><td>LSPD Target</td><td>+12</td><td>—</td></tr>
                        <tr><td>LSPD Lockdown</td><td>+15</td><td>—</td></tr>
                        <tr><td>LSPD Intel Sweep</td><td>+8</td><td>—</td></tr>
                    </tbody>
                </table>

                <div class="callout callout-danger">
                    <strong>XP cap:</strong> You cannot gain XP beyond the threshold for your current level. You <em>must</em> level up (visit the Dashboard) before further XP is counted. Leveling up happens automatically when you load the Dashboard.
                </div>
            </div>

            <!-- MOVEMENT -->
            <div class="panel p-6 rounded-xl section-anchor" id="movement">
                <div class="section-header">
                    <div class="section-icon">🧭</div>
                    <h2>Movement</h2>
                </div>

                <p class="text-zinc-400 text-sm mb-3">
                    You can move between turfs freely from the Dashboard. Click <strong class="text-white">Move Location</strong> to open the travel modal and select any district. Your current location is shown in the Location panel.
                </p>

                <p class="text-zinc-400 text-sm mb-3">
                    Your location determines which actions are available to you — food stores, Ammu-Nation, Hospital, and the ability to capture or declare war are all tied to the turf you're currently in.
                </p>

                <div class="callout callout-warn text-xs">
                    Each move costs <strong>−2 food</strong>. There is no cooldown on movement, but the food drain adds up if you're constantly travelling.
                </div>

                <div class="callout callout-tip">
                    Use the <strong>Turf Map</strong> to check which districts are contested and where your faction is weakest before deciding where to move.
                </div>
            </div>

            <!-- GANG ACTIONS -->
            <div class="panel p-6 rounded-xl section-anchor" id="gang-actions">
                <div class="section-header">
                    <div class="section-icon">🔫</div>
                    <h2>Gang Actions</h2>
                </div>

                <span class="badge badge-gang mb-4 inline-block">Gang only</span>

                <!-- TRAIN -->
                <div class="mb-6">
                    <h3 class="text-base font-bold text-white mb-1">🏋️ Train</h3>
                    <p class="text-zinc-400 text-sm mb-2">Choose a stat to improve by 1. Costs energy and food. The best long-term investment in the game.</p>
                    <div class="callout callout-tip text-xs">Training while hungry gives less XP. Keep food above 50 to get the full 8 XP per session.</div>
                </div>

                <!-- CRIME -->
                <div class="mb-6">
                    <h3 class="text-base font-bold text-white mb-1">🔫 Commit Crime</h3>
                    <p class="text-zinc-400 text-sm mb-2">A street-level hustle. Roll against a success chance and earn cash if you pull it off. Raises heat either way.</p>
                    <table class="stat-table mb-2">
                        <thead><tr><th>Outcome</th><th>Reward</th></tr></thead>
                        <tbody>
                            <tr><td class="text-green-400">Success</td><td>$350–$650 + (CHA × 3), +12 XP, heat +5</td></tr>
                            <tr><td class="text-red-400">Fail</td><td>+4 XP, heat +5</td></tr>
                        </tbody>
                    </table>
                    <p class="text-xs text-zinc-500">Base success: 55% + (INT × 1.25%) + (CHA × 0.35%) − food penalty − (heat × 0.2%). Capped at 5%–95%.</p>
                </div>

                <!-- DRUGS -->
                <div class="mb-6">
                    <h3 class="text-base font-bold text-white mb-1">💊 Run Drugs</h3>
                    <p class="text-zinc-400 text-sm mb-2">Higher risk, higher reward than crime. Also pushes turf control in your favour on success. Blocked during lockdown or active turf wars.</p>
                    <table class="stat-table mb-2">
                        <thead><tr><th>Outcome</th><th>Reward</th></tr></thead>
                        <tbody>
                            <tr><td class="text-green-400">Success</td><td>$450–$800 + (CHA × 5), +12 XP, heat +7, +2% turf control</td></tr>
                            <tr><td class="text-red-400">Fail</td><td>+4 XP, heat +7</td></tr>
                        </tbody>
                    </table>
                    <p class="text-xs text-zinc-500">Base success: 55% + (INT × 1.2%) + (CHA × 0.5%) − food penalty − (heat × 0.25%). Capped at 5%–95%.</p>
                </div>

                <!-- CAPTURE TURF -->
                <div class="mb-6">
                    <h3 class="text-base font-bold text-white mb-1">🚩 Capture Turf</h3>
                    <p class="text-zinc-400 text-sm mb-2">Pushes your faction's influence in your current district. The backbone of territorial expansion. Has a 45-second cooldown per character.</p>

                    <table class="stat-table mb-3">
                        <thead><tr><th>Factor</th><th>Effect</th></tr></thead>
                        <tbody>
                            <tr><td>Base gain</td><td>5 + floor(STR / 10)%</td></tr>
                            <tr><td>Charisma bonus</td><td>+floor(CHA / 15)%</td></tr>
                            <tr><td>Defensive resistance</td><td>Scales down gain when top faction has high control. At 100% control, gain is reduced by up to 70%.</td></tr>
                            <tr><td>Diminishing returns</td><td>High activity (5+ actions in 60s) reduces gain to prevent spamming</td></tr>
                            <tr><td>Comeback bonus</td><td>×1.5 gain if your faction owns zero turfs citywide</td></tr>
                            <tr><td>Capture streak</td><td>Each consecutive capture (within 2 min) costs +5 more energy</td></tr>
                        </tbody>
                    </table>

                    <div class="callout callout-info text-xs">
                        Capture shifts your faction up <em>and</em> reduces all rivals by half the amount gained. Normalisation keeps total at 100% across all factions.
                    </div>
                </div>

                <!-- START WAR -->
                <div>
                    <h3 class="text-base font-bold text-white mb-1">⚔️ Start Turf War</h3>
                    <p class="text-zinc-400 text-sm mb-2">Declare open war on the faction that owns your current turf. This is the primary way to flip ownership.</p>
                    <div class="callout callout-warn text-xs">
                        You can only declare war if: your faction has the <strong>most control</strong> in the turf but does <strong>not own it</strong>. The turf must not be on war cooldown (24 hours after the last war). The defending faction must not already be in another war.
                    </div>
                </div>
            </div>

            <!-- LSPD ACTIONS -->
            <div class="panel p-6 rounded-xl section-anchor" id="lspd-actions">
                <div class="section-header">
                    <div class="section-icon">🚔</div>
                    <h2>LSPD Operations</h2>
                </div>

                <span class="badge badge-law mb-4 inline-block">LSPD only</span>

                <p class="text-zinc-400 text-sm mb-5">LSPD players don't earn money through crime — they earn it through police operations. All four operations share a <strong class="text-white">30-second cooldown</strong>.</p>

                <div class="space-y-5">

                    <div>
                        <h3 class="text-base font-bold text-white mb-1">🔵 Suppress Turf</h3>
                        <p class="text-zinc-400 text-sm mb-1">Drains influence from <em>all</em> gang factions in the current turf and reduces heat. LSPD gains a small foothold.</p>
                        <p class="text-xs text-zinc-500">Drain: 2 + floor(INT/10)% per gang. Heat reduction: 4 + floor(INT/10). Payout: $200–350 + INT×2 + CHA×2. Cost: ~10 energy.</p>
                    </div>

                    <div>
                        <h3 class="text-base font-bold text-white mb-1">🎯 Target Faction</h3>
                        <p class="text-zinc-400 text-sm mb-1">Focus resources against one specific gang. Deals a heavier influence drain than Suppress but uses more energy.</p>
                        <p class="text-xs text-zinc-500">Drain: 5 + floor(INT/8)% from target. Payout: $250–400 + INT×2. Cost: 20 energy.</p>
                    </div>

                    <div>
                        <h3 class="text-base font-bold text-white mb-1">🔒 Lockdown</h3>
                        <p class="text-zinc-400 text-sm mb-1">Spikes heat in the current turf, potentially pushing it into lockdown. Disables all gang criminal actions until heat drops below 90.</p>
                        <p class="text-xs text-zinc-500">Heat spike: +25 + floor(INT/8). Cannot use if heat is already ≥ 85. Payout: $150–250. Cost: 30 energy.</p>
                        <div class="callout callout-danger text-xs mt-2">This is your most powerful tool — coordinate with other LSPD players to keep a turf locked down and watch gang income dry up.</div>
                    </div>

                    <div>
                        <h3 class="text-base font-bold text-white mb-1">🔍 Intel Sweep</h3>
                        <p class="text-zinc-400 text-sm mb-1">Reveals which gangs have been most active in the last 5 minutes. Applies a minor drain to all gangs and lowers heat slightly.</p>
                        <p class="text-xs text-zinc-500">Shows action counts per gang (last 5 min). Drain: 1% all gangs. Payout: $100–200 + INT×3. Cost: ~10 energy.</p>
                    </div>

                </div>
            </div>

            <!-- WEAPONS -->
            <div class="panel p-6 rounded-xl section-anchor" id="weapons">
                <div class="section-header">
                    <div class="section-icon">🔫</div>
                    <h2>Weapons</h2>
                </div>

                <p class="text-zinc-400 text-sm mb-4">
                    Weapons are purchased at <strong class="text-white">Ammu-Nation</strong> stores, which are only available in specific turfs. Once bought, a weapon stays equipped until you buy a new one.
                </p>

                <table class="stat-table mb-4">
                    <thead><tr><th>Stat</th><th>Effect</th></tr></thead>
                    <tbody>
                        <tr>
                            <td class="text-orange-400 font-semibold">War Points Bonus</td>
                            <td>Added directly to attack points each time you hit in a turf war. Stacks with Strength.</td>
                        </tr>
                        <tr>
                            <td class="text-red-400 font-semibold">Kill Chance Bonus</td>
                            <td>Adds a flat % to your death roll, making it more likely your hits eliminate an enemy participant.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="callout callout-tip">
                    Move to a turf with an Ammu-Nation (shown in the Location panel on Dashboard) and visit the Actions page to browse and buy. Check the map to find one nearby.
                </div>
            </div>

            <!-- HEAT -->
            <div class="panel p-6 rounded-xl section-anchor" id="heat">
                <div class="section-header">
                    <div class="section-icon">🔥</div>
                    <h2>Heat System</h2>
                </div>

                <p class="text-zinc-400 text-sm mb-4">Heat measures police attention in a turf on a 0–100 scale. Every crime and drug run raises it. Left unchecked, heat escalates to full lockdown.</p>

                <table class="stat-table mb-5">
                    <thead><tr><th>Heat Level</th><th>Status</th><th>Effect</th></tr></thead>
                    <tbody>
                        <tr><td class="text-green-400">0 – 69</td><td>Clear</td><td>No penalties</td></tr>
                        <tr><td class="text-yellow-400">70 – 89</td><td>⚠️ Hot</td><td>Warning shown. Crime / drug success reduced by heat × 0.2 / 0.25%</td></tr>
                        <tr><td class="text-red-500 font-bold">90 – 100</td><td>🚫 Lockdown</td><td>Crime and drug running completely disabled</td></tr>
                    </tbody>
                </table>

                <h3 class="text-sm font-semibold text-zinc-300 mb-2 uppercase tracking-wider">What raises and lowers heat</h3>
                <table class="stat-table mb-4">
                    <thead><tr><th>Action</th><th>Heat Change</th></tr></thead>
                    <tbody>
                        <tr><td>Commit Crime (win or lose)</td><td class="text-red-400">+5</td></tr>
                        <tr><td>Run Drugs (win or lose)</td><td class="text-red-400">+7</td></tr>
                        <tr><td>LSPD Suppress Turf</td><td class="text-green-400">−4 + floor(INT/10)</td></tr>
                        <tr><td>LSPD Target Faction</td><td class="text-green-400">−2</td></tr>
                        <tr><td>LSPD Lockdown</td><td class="text-red-400">+25 + floor(INT/8)</td></tr>
                        <tr><td>LSPD Intel Sweep</td><td class="text-green-400">−1</td></tr>
                    </tbody>
                </table>

                <div class="callout callout-info">
                    Heat decays automatically at <strong>1 point every 3 minutes</strong> when nothing is happening in a turf. LSPD operations accelerate this, but sustained gang activity can outpace natural decay.
                </div>
            </div>

            <!-- TURF CONTROL -->
            <div class="panel p-6 rounded-xl section-anchor" id="turf-control">
                <div class="section-header">
                    <div class="section-icon">🗺️</div>
                    <h2>Turf Control</h2>
                </div>

                <p class="text-zinc-400 text-sm mb-4">
                    Every turf has a control percentage for each faction. These always add up to 100%. The faction with the highest percentage is the dominant force — but the faction listed as <strong class="text-white">owner</strong> is whoever last won a turf war there.
                </p>

                <table class="stat-table mb-5">
                    <thead><tr><th>Concept</th><th>What it means</th></tr></thead>
                    <tbody>
                        <tr>
                            <td class="text-white font-semibold">Dominant Faction</td>
                            <td>Faction with the highest control %. Required to declare war.</td>
                        </tr>
                        <tr>
                            <td class="text-white font-semibold">Owner</td>
                            <td>Faction that last won a war here. Shown on the map. You are defending if you own it.</td>
                        </tr>
                        <tr>
                            <td class="text-white font-semibold">Normalisation</td>
                            <td>After every action, control percentages are recalculated to always total 100%.</td>
                        </tr>
                        <tr>
                            <td class="text-white font-semibold">Defensive Resistance</td>
                            <td>The higher the leading faction's control, the harder it is to chip away. Prevents instant takeovers.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="callout callout-info">
                    To take ownership of a turf, you first need to grind your faction's control above all rivals through captures and drug runs — then declare war when you hold the dominant position.
                </div>
            </div>

            <!-- TURF WARS -->
            <div class="panel p-6 rounded-xl section-anchor" id="turf-wars">
                <div class="section-header">
                    <div class="section-icon">⚔️</div>
                    <h2>Turf Wars</h2>
                </div>

                <p class="text-zinc-400 text-sm mb-5">
                    Turf wars are real-time battles between two factions. Every member of both factions is automatically enrolled as a participant. The war is fought on the <strong class="text-white">War page</strong> — get there fast when one starts.
                </p>

                <h3 class="text-sm font-semibold text-zinc-300 mb-2 uppercase tracking-wider">How to start a war</h3>
                <p class="text-zinc-400 text-sm mb-4">Go to Actions while in the contested turf. The "Start War" button appears when your faction holds the most control but does not own the turf. It challenges the current owner.</p>

                <h3 class="text-sm font-semibold text-zinc-300 mb-3 uppercase tracking-wider">Combat</h3>
                <table class="stat-table mb-5">
                    <thead><tr><th>Mechanic</th><th>Detail</th></tr></thead>
                    <tbody>
                        <tr><td>Attack cooldown</td><td>5 seconds between each attack</td></tr>
                        <tr><td>Points per hit</td><td>8–18 base + floor(STR / 5) + weapon bonus</td></tr>
                        <tr><td>Win by score</td><td>First faction to reach the target score wins</td></tr>
                        <tr><td>Win by elimination</td><td>Eliminate all enemy participants to win instantly</td></tr>
                        <tr><td>Death chance per hit</td><td>max(2%, 8% + rand(0–5%) + kill bonus − floor(target END / 6))</td></tr>
                        <tr><td>Dead players</td><td>Cannot attack for the rest of the war, but can spectate</td></tr>
                    </tbody>
                </table>

                <h3 class="text-sm font-semibold text-zinc-300 mb-2 uppercase tracking-wider">After the war</h3>
                <table class="stat-table mb-5">
                    <thead><tr><th>Outcome</th><th>Result</th></tr></thead>
                    <tbody>
                        <tr>
                            <td class="text-green-400">Winner</td>
                            <td>Takes ownership of the turf. Gains +20% control (capped at 70% pre-normalisation).</td>
                        </tr>
                        <tr>
                            <td class="text-red-400">Loser</td>
                            <td>Loses control to a floor of max(15%, 35% of current %). Never completely wiped out.</td>
                        </tr>
                        <tr>
                            <td>Both factions</td>
                            <td>All participants respawned. Cooldowns reset. Turf enters a <strong>24-hour war cooldown</strong> — no new wars until it expires.</td>
                        </tr>
                    </tbody>
                </table>

                <div class="grid md:grid-cols-2 gap-3">
                    <div class="callout callout-tip text-xs">
                        <strong>Attackers:</strong> Numbers matter. More faction members attacking = more points per second. Coordinate attack times to maintain pressure.
                    </div>
                    <div class="callout callout-warn text-xs">
                        <strong>Defenders:</strong> High Endurance makes you hard to kill. Prioritise staying alive — a dead defender is a lost attacker for your team.
                    </div>
                </div>
            </div>

            <!-- HOSPITAL -->
            <div class="panel p-6 rounded-xl section-anchor" id="hospital">
                <div class="section-header">
                    <div class="section-icon">🏥</div>
                    <h2>Hospital</h2>
                </div>

                <p class="text-zinc-400 text-sm mb-4">
                    Hospitals restore your character to full health. They are only available in specific turfs — check the Location panel on the Dashboard to see if one is present where you are.
                </p>

                <table class="stat-table mb-4">
                    <thead><tr><th>Condition</th><th>Cost</th></tr></thead>
                    <tbody>
                        <tr><td>Standard heal (full HP restore)</td><td class="text-yellow-400">$500</td></tr>
                        <tr><td>Your faction owns this turf</td><td class="text-green-400">$400 <span class="text-zinc-500">(20% discount)</span></td></tr>
                    </tbody>
                </table>

                <div class="callout callout-tip">
                    Controlling turfs with hospitals gives your faction a real economic advantage — your members pay less to stay healthy. Worth prioritising in war planning.
                </div>
            </div>

            <!-- CHAT -->
            <div class="panel p-6 rounded-xl section-anchor" id="chat">
                <div class="section-header">
                    <div class="section-icon">💬</div>
                    <h2>Chat</h2>
                </div>

                <p class="text-zinc-400 text-sm mb-4">
                    The chat panel on the Dashboard has three channels. Messages update in real time.
                </p>

                <table class="stat-table mb-4">
                    <thead><tr><th>Channel</th><th>Who can see it</th></tr></thead>
                    <tbody>
                        <tr><td class="text-white font-semibold">Global</td><td>Every player in the game</td></tr>
                        <tr><td class="text-white font-semibold">Faction</td><td>Only members of your faction</td></tr>
                        <tr><td class="text-white font-semibold">Zone</td><td>Only players currently in your turf</td></tr>
                    </tbody>
                </table>

                <div class="callout callout-warn text-xs">
                    Messages are capped at 200 characters. Admins can mute players from the Players page.
                </div>
            </div>

            <!-- TIPS -->
            <div class="panel p-6 rounded-xl section-anchor" id="tips">
                <div class="section-header">
                    <div class="section-icon">🧠</div>
                    <h2>Tips & Strategy</h2>
                </div>

                <div class="grid md:grid-cols-2 gap-3">
                    <div class="callout callout-tip">
                        <strong>Keep food above 75.</strong> The difference in energy regen between full and empty is massive over time. Burger Shot is worth the extra cost.
                    </div>
                    <div class="callout callout-tip">
                        <strong>Train Endurance early.</strong> Reducing action costs from 10 to 6 energy means 67% more actions per regen cycle for the same investment.
                    </div>
                    <div class="callout callout-tip">
                        <strong>Don't over-capture solo.</strong> Diminishing returns kick in hard at 5+ actions per minute in a turf. Spread captures out or rotate with teammates.
                    </div>
                    <div class="callout callout-tip">
                        <strong>Watch the streak cost.</strong> Capture streak adds +5 energy per consecutive action. If you're short on energy, wait 2 minutes to reset the streak.
                    </div>
                    <div class="callout callout-warn">
                        <strong>Don't declare war unprepared.</strong> All faction members are enrolled as participants. A small faction fighting a large one is at a numbers disadvantage — line up your team first.
                    </div>
                    <div class="callout callout-warn">
                        <strong>LSPD can block you hard.</strong> If your turf hits lockdown, you earn zero from crime or drugs until heat drops. Keep an eye on the heat level before committing actions.
                    </div>
                    <div class="callout callout-info">
                        <strong>Comeback mechanic.</strong> If your faction owns zero turfs citywide, every capture action gives 1.5× the normal influence gain. Use this window aggressively to get back in the game.
                    </div>
                    <div class="callout callout-info">
                        <strong>Get a weapon before wars.</strong> Even a basic weapon adds guaranteed flat points and kill chance to every single attack. Find an Ammu-Nation on the map and gear up before the fight.
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function() {
    const links = document.querySelectorAll('.manual-sidebar a');
    const sections = document.querySelectorAll('.section-anchor');

    function setActive() {
        let current = '';
        sections.forEach(sec => {
            if (window.scrollY + 120 >= sec.offsetTop) {
                current = sec.id;
            }
        });
        links.forEach(a => {
            a.classList.toggle('active', a.getAttribute('href') === '#' + current);
        });
    }

    window.addEventListener('scroll', setActive, { passive: true });
    setActive();

    links.forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            const target = document.querySelector(a.getAttribute('href'));
            if (target) target.scrollIntoView({ behavior: 'smooth' });
        });
    });
})();
</script>

<?php require_once 'templates/footer.php'; ?>
