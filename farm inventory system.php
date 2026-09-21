<?php
/**
 * Farm Inventory Management System
 * Tutorial 3 - Task 1
 *
 * Requirement: no if / elseif / else / switch statements anywhere in this file.
 * Instead this uses:
 *   - the null coalescing operator (??) for defaults
 *   - short-circuit `and` / `&&` evaluation in place of conditionals
 *   - array functions (array_push, array_slice, usort, array_map, count)
 *   - foreach loops for output (loops are allowed, only branching is banned)
 */

session_start();

// ---------------------------------------------------------------------
// 1. Initialise storage
// ---------------------------------------------------------------------
$_SESSION['inventory'] = $_SESSION['inventory'] ?? [];

// ---------------------------------------------------------------------
// 2. Handle "reset" action  (no if — short-circuit `and`)
// ---------------------------------------------------------------------
$isReset = isset($_GET['reset']);
$isReset and $_SESSION['inventory'] = [];
$isReset and header('Location: ' . basename(__FILE__));
$isReset and exit();

// ---------------------------------------------------------------------
// 3. Handle new record submission (no if — short-circuit `&&`)
// ---------------------------------------------------------------------
$item         = trim($_POST['item'] ?? '');
$quantity     = $_POST['quantity'] ?? '';
$dateEntered  = $_POST['date_entered'] ?? '';

$isValidSubmission =
    ($_SERVER['REQUEST_METHOD'] === 'POST')
    && !empty($item)
    && is_numeric($quantity)
    && !empty($dateEntered)
    && (count($_SESSION['inventory']) < 20);

$isValidSubmission && array_push($_SESSION['inventory'], [
    'item'         => htmlspecialchars($item, ENT_QUOTES),
    'quantity'     => (int) $quantity,
    'date_entered' => htmlspecialchars($dateEntered, ENT_QUOTES),
]);

// Safety cap at 20 records (array function, no conditional)
$_SESSION['inventory'] = array_slice($_SESSION['inventory'], 0, 20);

// ---------------------------------------------------------------------
// 4. Build the sorted (descending by quantity) copy for display
// ---------------------------------------------------------------------
$sortedInventory = $_SESSION['inventory'];
usort($sortedInventory, fn($a, $b) => $b['quantity'] <=> $a['quantity']);

$count = count($_SESSION['inventory']);

// Progress bar width, clamped to 100%, via array function instead of if
$progressPercent = min(100, ($count / 20) * 100);

// A "records remaining to reach minimum of 10" figure, floored at 0
$remainingToMin = max(0, 10 - $count);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Farm Inventory Management System</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        background: #f4f7f2;
        margin: 0;
        padding: 2rem 1rem;
        color: #223322;
    }
    .container {
        max-width: 900px;
        margin: 0 auto;
    }
    h1 {
        color: #2e5b2e;
        margin-bottom: 0.2rem;
    }
    .subtitle {
        color: #5a6b5a;
        margin-top: 0;
        margin-bottom: 1.5rem;
    }
    .card {
        background: #fff;
        border-radius: 10px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 1.5rem;
    }
    form {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr auto;
        gap: 0.75rem;
        align-items: end;
    }
    label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 0.3rem;
        color: #3a4a3a;
    }
    input {
        width: 100%;
        padding: 0.5rem 0.6rem;
        border: 1px solid #c9d3c9;
        border-radius: 6px;
        font-size: 0.95rem;
    }
    button {
        background: #2e5b2e;
        color: #fff;
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.95rem;
        font-weight: 600;
    }
    button:hover { background: #244a24; }
    .status-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.6rem;
        font-size: 0.9rem;
        color: #3a4a3a;
    }
    .progress-track {
        background: #e4ebe4;
        border-radius: 20px;
        height: 10px;
        overflow: hidden;
    }
    .progress-fill {
        background: linear-gradient(90deg,#6ea86e,#2e5b2e);
        height: 100%;
        border-radius: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.92rem;
    }
    th, td {
        text-align: left;
        padding: 0.6rem 0.7rem;
        border-bottom: 1px solid #eef2ee;
    }
    th {
        background: #eef5ee;
        color: #2e5b2e;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    tr:hover td { background: #f8fbf7; }
    .rank {
        display: inline-block;
        width: 1.6rem;
        height: 1.6rem;
        line-height: 1.6rem;
        text-align: center;
        border-radius: 50%;
        background: #dcebdc;
        color: #2e5b2e;
        font-weight: 700;
        font-size: 0.8rem;
    }
    .reset-link {
        display: inline-block;
        margin-top: 0.5rem;
        color: #a33;
        font-size: 0.85rem;
        text-decoration: none;
    }
    .reset-link:hover { text-decoration: underline; }
    .empty-note {
        color: #7a8a7a;
        font-style: italic;
        padding: 1rem 0;
    }
</style>
</head>
<body>
<div class="container">
    <h1>🌾 Farm Inventory Management System</h1>
    <p class="subtitle">Enter 10–20 inventory records. Records are sorted by quantity, highest first.</p>

    <div class="card">
        <form method="POST" action="">
            <div>
                <label for="item">Inventory Item</label>
                <input type="text" id="item" name="item" placeholder="e.g. Maize bags" required>
            </div>
            <div>
                <label for="quantity">Quantity</label>
                <input type="number" id="quantity" name="quantity" placeholder="e.g. 120" required>
            </div>
            <div>
                <label for="date_entered">Date Entered</label>
                <input type="date" id="date_entered" name="date_entered" required>
            </div>
            <button type="submit">Add Record</button>
        </form>
    </div>

    <div class="card">
        <div class="status-row">
            <span><?= $count ?> / 20 records entered</span>
            <span><?= $remainingToMin ?> more needed to reach the minimum of 10</span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" style="width: <?= $progressPercent ?>%;"></div>
        </div>
    </div>

    <div class="card">
        <h2 style="color:#2e5b2e; margin-top:0;">Inventory (sorted by quantity, descending)</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Date Entered</th>
                </tr>
            </thead>
            <tbody>
<?php
$rank = 0;
foreach ($sortedInventory as $record) {
    $rank++;
    echo "<tr>
            <td><span class=\"rank\">{$rank}</span></td>
            <td>{$record['item']}</td>
            <td>{$record['quantity']}</td>
            <td>{$record['date_entered']}</td>
          </tr>";
}
?>
            </tbody>
        </table>
        <?php
        // Empty-state note selected by array index (0 or 1) instead of an if statement
        $emptyNotes = [
            0 => '<p class="empty-note">No records yet — add your first inventory item above.</p>',
            1 => '',
        ];
        echo $emptyNotes[(int) ($count > 0)];
        ?>
        <a class="reset-link" href="?reset=1">Reset all records</a>
    </div>
</div>
</body>
</html>
