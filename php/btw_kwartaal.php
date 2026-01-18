<?php
/**
 * BTW per Kwartaal Overzicht
 *
 * Toont BTW-berekeningen en aangifte per kwartaal
 *
 * @author P. Theijssen
 */

require 'auth_functions.php';
require_login();

// Get user info and admin status
$user_id = get_current_user_id();
$is_admin = is_admin();
require 'config.php';

// Determine year and quarter
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$quarter = isset($_GET['quarter']) ? (int)$_GET['quarter'] : ceil(date('n') / 3);

// Calculate quarter dates
$quarterMonths = [
    1 => ['start' => "$year-01-01", 'end' => "$year-03-31", 'name' => "Eerste kwartaal ($year)"],
    2 => ['start' => "$year-04-01", 'end' => "$year-06-30", 'name' => "Tweede kwartaal ($year)"],
    3 => ['start' => "$year-07-01", 'end' => "$year-09-30", 'name' => "Derde kwartaal ($year)"],
    4 => ['start' => "$year-10-01", 'end' => "$year-12-31", 'name' => "Vierde kwartaal ($year)"]
];

$startDate = $quarterMonths[$quarter]['start'];
$endDate = $quarterMonths[$quarter]['end'];
$quarterName = $quarterMonths[$quarter]['name'];

// Check if VAT columns exist in the database
$vatColumnsExist = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'vat_percentage'");
    $vatColumnsExist = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $vatColumnsExist = false;
}

// Check if vat_rates table exists
$vatRatesTableExists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'vat_rates'");
    $vatRatesTableExists = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $vatRatesTableExists = false;
}

// Function to get VAT rate name for a specific date and percentage
function get_vat_rate_name($pdo, $date, $percentage) {
    try {
        $stmt = $pdo->prepare("
            SELECT name
            FROM vat_rates
            WHERE is_active = TRUE
              AND rate = ?
              AND effective_from <= ?
              AND (effective_to IS NULL OR effective_to >= ?)
            ORDER BY effective_from DESC
            LIMIT 1
        ");
        $stmt->execute([$percentage, $date, $date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['name'])) {
            return $result['name'];
        }
    } catch (Exception $e) {
        // If table doesn't exist or error, fall back to generic name
    }
    
    // Fallback names based on percentage
    if ($percentage == 21) return 'Hoog tarief';
    if ($percentage == 9) return 'Verlaagd tarief';
    if ($percentage == 0) return 'Vrijgesteld';
    return $percentage . '%';
}

// Get transactions for the quarter
if ($is_admin) {
    // Admin sees all transactions
    if ($vatColumnsExist) {
        // With VAT columns
        if ($vatRatesTableExists) {
            // Include VAT rate name from vat_rates table
            $stmt = $pdo->prepare("
                SELECT
                    t.*,
                    c.name as category,
                    -- Calculate VAT amounts
                    CASE
                        WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                            t.amount - (t.amount / (1 + (t.vat_percentage / 100)))
                        WHEN t.vat_included = FALSE AND t.vat_percentage > 0 THEN
                            t.amount * (t.vat_percentage / 100)
                        ELSE 0
                    END as vat_amount,
                    -- Calculate base amount
                    CASE
                        WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                            t.amount / (1 + (t.vat_percentage / 100))
                        ELSE t.amount
                    END as base_amount,
                    -- Get VAT rate name
                    COALESCE(
                        (SELECT vr.name
                         FROM vat_rates vr
                         WHERE vr.is_active = TRUE
                           AND vr.rate = t.vat_percentage
                           AND vr.effective_from <= t.date
                           AND (vr.effective_to IS NULL OR vr.effective_to >= t.date)
                         ORDER BY vr.effective_from DESC
                         LIMIT 1),
                        CASE
                            WHEN t.vat_percentage = 21 THEN 'Hoog tarief'
                            WHEN t.vat_percentage = 9 THEN 'Verlaagd tarief'
                            WHEN t.vat_percentage = 0 THEN 'Vrijgesteld'
                            ELSE CONCAT(t.vat_percentage, '%')
                        END
                    ) as vat_rate_name
                FROM transactions t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.date BETWEEN ? AND ?
                ORDER BY t.date
            ");
        } else {
            // Without vat_rates table
            $stmt = $pdo->prepare("
                SELECT
                    t.*,
                    c.name as category,
                    -- Calculate VAT amounts
                    CASE
                        WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                            t.amount - (t.amount / (1 + (t.vat_percentage / 100)))
                        WHEN t.vat_included = FALSE AND t.vat_percentage > 0 THEN
                            t.amount * (t.vat_percentage / 100)
                        ELSE 0
                    END as vat_amount,
                    -- Calculate base amount
                    CASE
                        WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                            t.amount / (1 + (t.vat_percentage / 100))
                        ELSE t.amount
                    END as base_amount,
                    CONCAT(t.vat_percentage, '%') as vat_rate_name
                FROM transactions t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.date BETWEEN ? AND ?
                ORDER BY t.date
            ");
        }
    } else {
        // Without VAT columns (simplified)
        $stmt = $pdo->prepare("
            SELECT t.*, c.name as category
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.date BETWEEN ? AND ?
            ORDER BY t.date
        ");
    }
    $stmt->execute([$startDate, $endDate]);
} else {
    // Regular user - only see their own transactions
    if ($vatColumnsExist) {
        // With VAT columns
        if ($vatRatesTableExists) {
            // Include VAT rate name from vat_rates table
            $stmt = $pdo->prepare("
                SELECT
                    t.*,
                    c.name as category,
                    -- Calculate VAT amounts
                    CASE
                        WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                            t.amount - (t.amount / (1 + (t.vat_percentage / 100)))
                        WHEN t.vat_included = FALSE AND t.vat_percentage > 0 THEN
                            t.amount * (t.vat_percentage / 100)
                        ELSE 0
                    END as vat_amount,
                    -- Calculate base amount
                    CASE
                        WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                            t.amount / (1 + (t.vat_percentage / 100))
                        ELSE t.amount
                    END as base_amount,
                    -- Get VAT rate name
                    COALESCE(
                        (SELECT vr.name
                         FROM vat_rates vr
                         WHERE vr.is_active = TRUE
                           AND vr.rate = t.vat_percentage
                           AND vr.effective_from <= t.date
                           AND (vr.effective_to IS NULL OR vr.effective_to >= t.date)
                         ORDER BY vr.effective_from DESC
                         LIMIT 1),
                        CASE
                            WHEN t.vat_percentage = 21 THEN 'Hoog tarief'
                            WHEN t.vat_percentage = 9 THEN 'Verlaagd tarief'
                            WHEN t.vat_percentage = 0 THEN 'Vrijgesteld'
                            ELSE CONCAT(t.vat_percentage, '%')
                        END
                    ) as vat_rate_name
                FROM transactions t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.date BETWEEN ? AND ? AND t.user_id = ?
                ORDER BY t.date
            ");
        } else {
            // Without vat_rates table
            $stmt = $pdo->prepare("
                SELECT
                    t.*,
                    c.name as category,
                    -- Calculate VAT amounts
                    CASE
                        WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                            t.amount - (t.amount / (1 + (t.vat_percentage / 100)))
                        WHEN t.vat_included = FALSE AND t.vat_percentage > 0 THEN
                            t.amount * (t.vat_percentage / 100)
                        ELSE 0
                    END as vat_amount,
                    -- Calculate base amount
                    CASE
                        WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                            t.amount / (1 + (t.vat_percentage / 100))
                        ELSE t.amount
                    END as base_amount,
                    CONCAT(t.vat_percentage, '%') as vat_rate_name
                FROM transactions t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.date BETWEEN ? AND ? AND t.user_id = ?
                ORDER BY t.date
            ");
        }
    } else {
        // Without VAT columns (simplified)
        $stmt = $pdo->prepare("
            SELECT t.*, c.name as category
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.date BETWEEN ? AND ? AND t.user_id = ?
            ORDER BY t.date
        ");
    }
    $stmt->execute([$startDate, $endDate, $user_id]);
}
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate VAT summary
$vatSummary = [
    'total_income' => 0,
    'total_expenses' => 0,
    'vat_on_income' => 0,
    'vat_on_expenses' => 0,
    'net_vat' => 0
];

foreach ($transactions as $t) {
    if ($t['type'] == 'inkomst') {
        $vatSummary['total_income'] += $t['amount'];
        if ($vatColumnsExist && isset($t['vat_amount'])) {
            $vatSummary['vat_on_income'] += $t['vat_amount'];
        }
    } else {
        $vatSummary['total_expenses'] += $t['amount'];
        if ($vatColumnsExist && isset($t['vat_amount']) && isset($t['vat_deductible']) && $t['vat_deductible']) {
            $vatSummary['vat_on_expenses'] += $t['vat_amount'];
        }
    }
}

$vatSummary['net_vat'] = $vatSummary['vat_on_income'] - $vatSummary['vat_on_expenses'];

// Group by VAT rate if available - IMPROVED VERSION with detailed breakdown
$vatByRate = [];
$vatDetailed = [
    'income_by_rate' => [],
    'expense_by_rate' => [],
    'vat_income_by_rate' => [],
    'vat_expense_by_rate' => []
];

if ($vatColumnsExist) {
    if ($is_admin) {
        // Admin sees all transactions
        if ($vatRatesTableExists) {
            // With historical VAT rates - get detailed breakdown
            $stmt = $pdo->prepare("
                SELECT
                    t.vat_percentage,
                    COALESCE(
                        (SELECT vr.name
                         FROM vat_rates vr
                         WHERE vr.is_active = TRUE
                           AND vr.rate = t.vat_percentage
                           AND vr.effective_from <= t.date
                           AND (vr.effective_to IS NULL OR vr.effective_to >= t.date)
                         ORDER BY vr.effective_from DESC
                         LIMIT 1),
                        CASE
                            WHEN t.vat_percentage = 21 THEN 'Hoog tarief'
                            WHEN t.vat_percentage = 9 THEN 'Verlaagd tarief'
                            WHEN t.vat_percentage = 0 THEN 'Vrijgesteld'
                            ELSE CONCAT(t.vat_percentage, '%')
                        END
                    ) as vat_rate_name,
                    t.type,
                    SUM(t.amount) as total_amount,
                    COUNT(*) as count,
                    -- Calculate VAT amounts properly
                    SUM(
                        CASE
                            WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                                t.amount - (t.amount / (1 + (t.vat_percentage / 100)))
                            WHEN t.vat_included = FALSE AND t.vat_percentage > 0 THEN
                                t.amount * (t.vat_percentage / 100)
                            ELSE 0
                        END
                    ) as total_vat_amount,
                    -- Calculate base amount (excl. VAT)
                    SUM(
                        CASE
                            WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                                t.amount / (1 + (t.vat_percentage / 100))
                            ELSE t.amount
                        END
                    ) as base_amount
                FROM transactions t
                WHERE t.date BETWEEN ? AND ? AND t.vat_percentage IS NOT NULL
                GROUP BY t.vat_percentage, vat_rate_name, t.type
                ORDER BY t.vat_percentage DESC, t.type
            ");
        } else {
            // Without vat_rates table - simple grouping by percentage
            $stmt = $pdo->prepare("
                SELECT
                    vat_percentage,
                    CONCAT(vat_percentage, '%') as vat_rate_name,
                    type,
                    SUM(amount) as total_amount,
                    COUNT(*) as count,
                    -- Calculate VAT amounts properly
                    SUM(
                        CASE
                            WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                                amount - (amount / (1 + (vat_percentage / 100)))
                            WHEN vat_included = FALSE AND vat_percentage > 0 THEN
                                amount * (vat_percentage / 100)
                            ELSE 0
                        END
                    ) as total_vat_amount,
                    -- Calculate base amount (excl. VAT)
                    SUM(
                        CASE
                            WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                                amount / (1 + (vat_percentage / 100))
                            ELSE amount
                        END
                    ) as base_amount
                FROM transactions
                WHERE date BETWEEN ? AND ? AND vat_percentage IS NOT NULL
                GROUP BY vat_percentage, type
                ORDER BY vat_percentage DESC, type
            ");
        }
        $stmt->execute([$startDate, $endDate]);
    } else {
        // Regular user - only see their own transactions
        if ($vatRatesTableExists) {
            // With historical VAT rates - get detailed breakdown
            $stmt = $pdo->prepare("
                SELECT
                    t.vat_percentage,
                    COALESCE(
                        (SELECT vr.name
                         FROM vat_rates vr
                         WHERE vr.is_active = TRUE
                           AND vr.rate = t.vat_percentage
                           AND vr.effective_from <= t.date
                           AND (vr.effective_to IS NULL OR vr.effective_to >= t.date)
                         ORDER BY vr.effective_from DESC
                         LIMIT 1),
                        CASE
                            WHEN t.vat_percentage = 21 THEN 'Hoog tarief'
                            WHEN t.vat_percentage = 9 THEN 'Verlaagd tarief'
                            WHEN t.vat_percentage = 0 THEN 'Vrijgesteld'
                            ELSE CONCAT(t.vat_percentage, '%')
                        END
                    ) as vat_rate_name,
                    t.type,
                    SUM(t.amount) as total_amount,
                    COUNT(*) as count,
                    -- Calculate VAT amounts properly
                    SUM(
                        CASE
                            WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                                t.amount - (t.amount / (1 + (t.vat_percentage / 100)))
                            WHEN t.vat_included = FALSE AND t.vat_percentage > 0 THEN
                                t.amount * (t.vat_percentage / 100)
                            ELSE 0
                        END
                    ) as total_vat_amount,
                    -- Calculate base amount (excl. VAT)
                    SUM(
                        CASE
                            WHEN t.vat_included = TRUE AND t.vat_percentage > 0 THEN
                                t.amount / (1 + (t.vat_percentage / 100))
                            ELSE t.amount
                        END
                    ) as base_amount
                FROM transactions t
                WHERE t.date BETWEEN ? AND ? AND t.vat_percentage IS NOT NULL AND t.user_id = ?
                GROUP BY t.vat_percentage, vat_rate_name, t.type
                ORDER BY t.vat_percentage DESC, t.type
            ");
            $stmt->execute([$startDate, $endDate, $user_id]);
        } else {
            // Without vat_rates table - simple grouping by percentage
            $stmt = $pdo->prepare("
                SELECT
                    vat_percentage,
                    CONCAT(vat_percentage, '%') as vat_rate_name,
                    type,
                    SUM(amount) as total_amount,
                    COUNT(*) as count,
                    -- Calculate VAT amounts properly
                    SUM(
                        CASE
                            WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                                amount - (amount / (1 + (vat_percentage / 100)))
                            WHEN vat_included = FALSE AND vat_percentage > 0 THEN
                                amount * (vat_percentage / 100)
                            ELSE 0
                        END
                    ) as total_vat_amount,
                    -- Calculate base amount (excl. VAT)
                    SUM(
                        CASE
                            WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                                amount / (1 + (vat_percentage / 100))
                            ELSE amount
                        END
                    ) as base_amount
                FROM transactions
                WHERE date BETWEEN ? AND ? AND vat_percentage IS NOT NULL AND user_id = ?
                GROUP BY vat_percentage, type
                ORDER BY vat_percentage DESC, type
            ");
            $stmt->execute([$startDate, $endDate, $user_id]);
        }
    }
    $vatByRate = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize data by rate for detailed breakdown
    foreach ($vatByRate as $row) {
        $rate = $row['vat_percentage'];
        $type = $row['type'];
        
        if ($type == 'inkomst') {
            $vatDetailed['income_by_rate'][$rate] = ($vatDetailed['income_by_rate'][$rate] ?? 0) + $row['total_amount'];
            $vatDetailed['vat_income_by_rate'][$rate] = ($vatDetailed['vat_income_by_rate'][$rate] ?? 0) + $row['total_vat_amount'];
        } else {
            $vatDetailed['expense_by_rate'][$rate] = ($vatDetailed['expense_by_rate'][$rate] ?? 0) + $row['total_amount'];
            $vatDetailed['vat_expense_by_rate'][$rate] = ($vatDetailed['vat_expense_by_rate'][$rate] ?? 0) + $row['total_vat_amount'];
        }
    }
}

// Get monthly VAT breakdown
$monthlyVat = [];
if ($vatColumnsExist) {
    if ($is_admin) {
        // Admin sees all transactions
        $stmt = $pdo->prepare("
            SELECT
                MONTH(date) as month,
                YEAR(date) as year,
                vat_percentage,
                type,
                SUM(
                    CASE
                        WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                            amount - (amount / (1 + (vat_percentage / 100)))
                        WHEN vat_included = FALSE AND vat_percentage > 0 THEN
                            amount * (vat_percentage / 100)
                        ELSE 0
                    END
                ) as vat_amount,
                SUM(amount) as total_amount,
                COUNT(*) as count
            FROM transactions
            WHERE date BETWEEN ? AND ? AND vat_percentage IS NOT NULL
            GROUP BY MONTH(date), YEAR(date), vat_percentage, type
            ORDER BY MONTH(date), vat_percentage DESC, type
        ");
        $stmt->execute([$startDate, $endDate]);
    } else {
        // Regular user - only see their own transactions
        $stmt = $pdo->prepare("
            SELECT
                MONTH(date) as month,
                YEAR(date) as year,
                vat_percentage,
                type,
                SUM(
                    CASE
                        WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                            amount - (amount / (1 + (vat_percentage / 100)))
                        WHEN vat_included = FALSE AND vat_percentage > 0 THEN
                            amount * (vat_percentage / 100)
                        ELSE 0
                    END
                ) as vat_amount,
                SUM(amount) as total_amount,
                COUNT(*) as count
            FROM transactions
            WHERE date BETWEEN ? AND ? AND vat_percentage IS NOT NULL AND user_id = ?
            GROUP BY MONTH(date), YEAR(date), vat_percentage, type
            ORDER BY MONTH(date), vat_percentage DESC, type
        ");
        $stmt->execute([$startDate, $endDate, $user_id]);
    }
    $monthlyVat = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get monthly summary
$monthlySummary = [];
if ($vatColumnsExist) {
    if ($is_admin) {
        // Admin sees all transactions
        $stmt = $pdo->prepare("
            SELECT
                MONTH(date) as month,
                YEAR(date) as year,
                SUM(CASE WHEN type = 'inkomst' THEN
                    CASE
                        WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                            amount - (amount / (1 + (vat_percentage / 100)))
                        WHEN vat_included = FALSE AND vat_percentage > 0 THEN
                            amount * (vat_percentage / 100)
                        ELSE 0
                    END
                    ELSE 0 END) as vat_income,
                SUM(CASE WHEN type = 'uitgave' AND vat_deductible = TRUE THEN
                    CASE
                        WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                            amount - (amount / (1 + (vat_percentage / 100)))
                        WHEN vat_included = FALSE AND vat_percentage > 0 THEN
                            amount * (vat_percentage / 100)
                        ELSE 0
                    END
                    ELSE 0 END) as vat_expense_deductible,
                SUM(CASE WHEN type = 'uitgave' AND (vat_deductible = FALSE OR vat_deductible IS NULL) THEN
                    CASE
                        WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                            amount - (amount / (1 + (vat_percentage / 100)))
                        WHEN vat_included = FALSE AND vat_percentage > 0 THEN
                            amount * (vat_percentage / 100)
                        ELSE 0
                    END
                    ELSE 0 END) as vat_expense_nondeductible
            FROM transactions
            WHERE date BETWEEN ? AND ? AND vat_percentage IS NOT NULL
            GROUP BY MONTH(date), YEAR(date)
            ORDER BY MONTH(date)
        ");
        $stmt->execute([$startDate, $endDate]);
    } else {
        // Regular user - only see their own transactions
        $stmt = $pdo->prepare("
            SELECT
                MONTH(date) as month,
                YEAR(date) as year,
                SUM(CASE WHEN type = 'inkomst' THEN
                    CASE
                        WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                            amount - (amount / (1 + (vat_percentage / 100)))
                        WHEN vat_included = FALSE AND vat_percentage > 0 THEN
                            amount * (vat_percentage / 100)
                        ELSE 0
                    END
                    ELSE 0 END) as vat_income,
                SUM(CASE WHEN type = 'uitgave' AND vat_deductible = TRUE THEN
                    CASE
                        WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                            amount - (amount / (1 + (vat_percentage / 100)))
                        WHEN vat_included = FALSE AND vat_percentage > 0 THEN
                            amount * (vat_percentage / 100)
                        ELSE 0
                    END
                    ELSE 0 END) as vat_expense_deductible,
                SUM(CASE WHEN type = 'uitgave' AND (vat_deductible = FALSE OR vat_deductible IS NULL) THEN
                    CASE
                        WHEN vat_included = TRUE AND vat_percentage > 0 THEN
                            amount - (amount / (1 + (vat_percentage / 100)))
                        WHEN vat_included = FALSE AND vat_percentage > 0 THEN
                            amount * (vat_percentage / 100)
                        ELSE 0
                    END
                    ELSE 0 END) as vat_expense_nondeductible
            FROM transactions
            WHERE date BETWEEN ? AND ? AND vat_percentage IS NOT NULL AND user_id = ?
            GROUP BY MONTH(date), YEAR(date)
            ORDER BY MONTH(date)
        ");
        $stmt->execute([$startDate, $endDate, $user_id]);
    }
    $monthlySummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BTW per Kwartaal - Boekhouden</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Profile dropdown styles */
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .profile-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            cursor: pointer;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .profile-icon:hover {
            transform: scale(1.05);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.5);
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 50px;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 8px;
            z-index: 1000;
            overflow: hidden;
        }
        
        .dropdown-content.show {
            display: block;
        }
        
        .dropdown-header {
            padding: 15px;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
        }
        
        .dropdown-header .user-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 3px;
        }
        
        .dropdown-header .user-email {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .dropdown-header .user-role {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-top: 5px;
        }
        
        .dropdown-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .dropdown-menu li {
            border-bottom: 1px solid #f0f0f0;
        }
        
        .dropdown-menu li:last-child {
            border-bottom: none;
        }
        
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .dropdown-menu a:hover {
            background-color: #f8f9fa;
        }
        
        .dropdown-menu a i {
            width: 20px;
            color: #7f8c8d;
        }
        
        .dropdown-menu .logout-link {
            color: #e74c3c !important;
        }
        
        .dropdown-menu .logout-link:hover {
            background-color: rgba(231, 76, 60, 0.1);
        }
        
        .dropdown-menu .logout-link i {
            color: #e74c3c;
        }
        
        .user-info-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            color: white;
            font-size: 0.9rem;
            position: relative;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-logo-container">
            <div class="logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 60" width="200" height="60">
                    <defs>
                        <linearGradient id="header-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#2c3e50;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#3498db;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    <rect x="5" y="5" width="50" height="50" rx="10" ry="10" fill="url(#header-gradient)" stroke="#2c3e50" stroke-width="1.5"/>
                    <rect x="15" y="15" width="30" height="30" rx="3" ry="3" fill="white" opacity="0.9"/>
                    <rect x="15" y="15" width="5" height="30" rx="1" ry="1" fill="#2c3e50"/>
                    <line x1="25" y1="20" x2="40" y2="20" stroke="#3498db" stroke-width="1"/>
                    <line x1="25" y1="25" x2="40" y2="25" stroke="#3498db" stroke-width="1"/>
                    <line x1="25" y1="30" x2="40" y2="30" stroke="#3498db" stroke-width="1"/>
                    <line x1="25" y1="35" x2="40" y2="35" stroke="#3498db" stroke-width="1"/>
                    <line x1="25" y1="40" x2="40" y2="40" stroke="#3498db" stroke-width="1"/>
                    <text x="32" y="38" text-anchor="middle" fill="#2c3e50" font-family="Arial, sans-serif" font-weight="bold" font-size="14">€</text>
                    <text x="70" y="30" font-family="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" font-size="22" font-weight="600" fill="white">BOEK!N</text>
                </svg>
            </div>
            <div class="header-text">
                <h1>BTW Overzicht per Kwartaal</h1>
                <p>BTW-berekeningen en aangifte per kwartaal</p>
            </div>
        </div>
    </div>

    <nav class="nav-bar">
        <ul class="nav-links">
            <li><a href="../index.php">Transacties</a></li>
            <li><a href="add.php">Nieuwe Transactie</a></li>
            <li><a href="profit_loss.php">Kosten Baten</a></li>
            <li><a href="btw_kwartaal.php" class="active">BTW Kwartaal</a></li>
            <li><a href="balans.php">Balans</a></li>
            <?php if ($is_admin): ?>
                <li><a href="admin_dashboard.php">Admin Dashboard</a></li>
            <?php endif; ?>
        </ul>
        <div class="user-info-nav">
            <div class="profile-dropdown">
                <?php
                $user = get_current_user_data();
                $user_initial = strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 1));
                $user_name = $user['full_name'] ?? $user['username'] ?? 'Gebruiker';
                $user_email = $user['email'] ?? '';
                $user_role = $user['user_type'] ?? 'gebruiker';
                $role_display = ($user_role === 'administrator') ? 'Administrator' : 'Gebruiker';
                ?>
                <div class="profile-icon" id="profileIcon">
                    <?php echo $user_initial; ?>
                </div>
                <div class="dropdown-content" id="profileDropdown">
                    <div class="dropdown-header">
                        <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                        <?php if ($user_email): ?>
                        <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                        <?php endif; ?>
                        <div class="user-role"><?php echo htmlspecialchars($role_display); ?></div>
                    </div>
                    <ul class="dropdown-menu">
                        <li><a href="../index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <?php if ($is_admin): ?>
                        <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a></li>
                        <li><a href="admin_users.php"><i class="fas fa-users"></i> Gebruikersbeheer</a></li>
                        <?php endif; ?>
                        <li><a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Uitloggen</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><?php echo $quarterName; ?></h2>
        <p class="neutral">Periode: <?php echo date('d-m-Y', strtotime($startDate)); ?> t/m <?php echo date('d-m-Y', strtotime($endDate)); ?></p>
        
        <?php if (!$vatColumnsExist): ?>
        <div class="alert alert-warning">
            <strong>Let op:</strong> De database ondersteunt nog geen BTW-velden. Om BTW te berekenen moet u eerst:
            <ol style="margin: 10px 0 10px 20px;">
                <li>De database bijwerken met het nieuwe schema (schema_vat.sql)</li>
                <li>BTW-gegevens toevoegen aan bestaande transacties</li>
                <li>Nieuwe transacties met BTW-percentage invoeren</li>
            </ol>
            <p>De onderstaande berekeningen zijn gebaseerd op veronderstellingen.</p>
        </div>
        <?php endif; ?>
        
        <div class="filter-bar">
            <form method="get" class="filter-form" style="display: flex; gap: 1rem; align-items: center;">
                <div class="form-group" style="margin: 0;">
                    <label for="year" style="margin-right: 0.5rem;">Jaar:</label>
                    <select id="year" name="year" class="form-control form-control-sm">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <label for="quarter" style="margin-right: 0.5rem;">Kwartaal:</label>
                    <select id="quarter" name="quarter" class="form-control form-control-sm">
                        <option value="1" <?php echo $quarter == 1 ? 'selected' : ''; ?>>Q1 (jan-mrt)</option>
                        <option value="2" <?php echo $quarter == 2 ? 'selected' : ''; ?>>Q2 (apr-jun)</option>
                        <option value="3" <?php echo $quarter == 3 ? 'selected' : ''; ?>>Q3 (jul-sep)</option>
                        <option value="4" <?php echo $quarter == 4 ? 'selected' : ''; ?>>Q4 (okt-dec)</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary btn-sm">Toon Kwartaal</button>
            </form>
        </div>
        
        <div class="card-grid">
            <div class="card">
                <h3 class="card-title">Omzet (excl. BTW)</h3>
                <div class="positive amount">€<?php echo number_format($vatSummary['total_income'], 2); ?></div>
                <p class="neutral">Totaal inkomsten dit kwartaal</p>
            </div>
            
            <div class="card">
                <h3 class="card-title">Kosten (excl. BTW)</h3>
                <div class="negative amount">€<?php echo number_format($vatSummary['total_expenses'], 2); ?></div>
                <p class="neutral">Totaal uitgaven dit kwartaal</p>
            </div>
            
            <div class="card">
                <h3 class="card-title">Af te dragen BTW</h3>
                <div class="<?php echo $vatSummary['vat_on_income'] >= 0 ? 'negative' : 'positive'; ?> amount">
                    €<?php echo number_format($vatSummary['vat_on_income'], 2); ?>
                </div>
                <p class="neutral">
                    <?php echo $vatSummary['vat_on_income'] >= 0 ? 'BTW over inkomsten' : 'BTW te ontvangen (creditnota\'s)'; ?>
                </p>
            </div>
            
            <div class="card">
                <h3 class="card-title">Voorbelasting BTW</h3>
                <div class="<?php echo $vatSummary['vat_on_expenses'] >= 0 ? 'positive' : 'negative'; ?> amount">
                    €<?php echo number_format($vatSummary['vat_on_expenses'], 2); ?>
                </div>
                <p class="neutral">
                    <?php echo $vatSummary['vat_on_expenses'] >= 0 ? 'BTW over aftrekbare kosten' : 'BTW terug te betalen (credits)'; ?>
                </p>
            </div>
            
            <div class="card" style="grid-column: span 2; background: linear-gradient(135deg, #2c3e50, #3498db); color: white;">
                <h3 class="card-title" style="color: white;">Netto BTW verschuldigd</h3>
                <div class="amount" style="font-size: 2.5rem; color: white;">
                    €<?php echo number_format($vatSummary['net_vat'], 2); ?>
                </div>
                <p style="font-size: 1.2rem; margin-top: 10px;">
                    <?php if ($vatSummary['net_vat'] > 0): ?>
                    <strong>Te betalen aan de Belastingdienst</strong>
                    <?php elseif ($vatSummary['net_vat'] < 0): ?>
                    <strong>Terug te ontvangen van de Belastingdienst</strong>
                    <?php else: ?>
                    <strong>Geen BTW verschuldigd</strong>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <?php if ($vatColumnsExist && !empty($vatByRate)): ?>
        <div class="card">
            <h3 class="card-title">BTW per Tarief - Gedetailleerd Overzicht</h3>
            
            <!-- Detailed VAT Rate Breakdown -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>BTW Tarief</th>
                            <th>Type</th>
                            <th>Aantal transacties</th>
                            <th>Totaal bedrag (incl. BTW)</th>
                            <th>Bedrag excl. BTW</th>
                            <th>BTW bedrag</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vatByRate as $row): ?>
                        <tr class="vat-rate-<?php echo (int)$row['vat_percentage']; ?>">
                            <td>
                                <strong><?php echo $row['vat_percentage']; ?>%</strong>
                                <?php if (isset($row['vat_rate_name']) && $row['vat_rate_name'] != $row['vat_percentage'] . '%'): ?>
                                <br><small class="neutral"><?php echo htmlspecialchars($row['vat_rate_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="<?php
                                    if ($row['type'] == 'inkomst') {
                                        echo $row['total_amount'] >= 0 ? 'positive' : 'negative';
                                    } else {
                                        echo $row['total_amount'] >= 0 ? 'negative' : 'positive';
                                    }
                                ?>">
                                    <?php
                                    // Show special label for credit notes
                                    if ($row['type'] == 'inkomst' && $row['total_amount'] < 0) {
                                        echo 'Creditnota (Inkomst)';
                                    } elseif ($row['type'] == 'uitgave' && $row['total_amount'] < 0) {
                                        echo 'Credit (Uitgave)';
                                    } else {
                                        echo ucfirst($row['type']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $row['count']; ?></td>
                            <td>€<?php echo number_format($row['total_amount'], 2); ?></td>
                            <td>€<?php echo isset($row['base_amount']) ? number_format($row['base_amount'], 2) : number_format($row['total_amount'] / (1 + ($row['vat_percentage']/100)), 2); ?></td>
                            <td>
                                €<?php echo isset($row['total_vat_amount']) ? number_format($row['total_vat_amount'], 2) : number_format($row['total_amount'] * ($row['vat_percentage'] / 100), 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary by VAT Rate -->
            <div style="margin-top: 2rem;">
                <h4>Totaal Bedragen per BTW Tarief</h4>
                <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                    <?php
                    // Get unique VAT rates
                    $uniqueRates = [];
                    foreach ($vatByRate as $row) {
                        $rate = $row['vat_percentage'];
                        if (!in_array($rate, $uniqueRates)) {
                            $uniqueRates[] = $rate;
                        }
                    }
                    sort($uniqueRates);
                    
                    foreach ($uniqueRates as $rate):
                        $incomeTotal = $vatDetailed['income_by_rate'][$rate] ?? 0;
                        $expenseTotal = $vatDetailed['expense_by_rate'][$rate] ?? 0;
                        $vatIncome = $vatDetailed['vat_income_by_rate'][$rate] ?? 0;
                        $vatExpense = $vatDetailed['vat_expense_by_rate'][$rate] ?? 0;
                    ?>
                    <div class="card" style="background: #f8f9fa;">
                        <h5 class="card-title"><?php echo $rate; ?>% Tarief</h5>
                        <div class="stats-grid" style="grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <div>
                                <small class="neutral">Inkomsten:</small>
                                <div class="positive">€<?php echo number_format($incomeTotal, 2); ?></div>
                            </div>
                            <div>
                                <small class="neutral">Uitgaven:</small>
                                <div class="negative">€<?php echo number_format($expenseTotal, 2); ?></div>
                            </div>
                            <div>
                                <small class="neutral">BTW inkomsten:</small>
                                <div>€<?php echo number_format($vatIncome, 2); ?></div>
                            </div>
                            <div>
                                <small class="neutral">BTW uitgaven:</small>
                                <div>€<?php echo number_format($vatExpense, 2); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Monthly VAT by Rate Breakdown -->
        <?php if ($vatColumnsExist && !empty($monthlyVat)): ?>
        <div class="card">
            <h3 class="card-title">BTW per Tarief per Maand</h3>
            <p class="neutral">Gedetailleerd overzicht van BTW-bedragen per tarief voor elke maand in het kwartaal</p>
            
            <?php
            // Organize monthly VAT data by month and rate
            $monthlyByRate = [];
            $monthNames = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maart', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Augustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December'
            ];
            
            foreach ($monthlyVat as $row) {
                $month = (int)$row['month'];
                $rate = $row['vat_percentage'];
                $type = $row['type'];
                
                if (!isset($monthlyByRate[$month])) {
                    $monthlyByRate[$month] = [
                        'name' => $monthNames[$month] ?? "Maand $month",
                        'rates' => []
                    ];
                }
                
                if (!isset($monthlyByRate[$month]['rates'][$rate])) {
                    $monthlyByRate[$month]['rates'][$rate] = [
                        'income' => 0,
                        'expense' => 0,
                        'vat_income' => 0,
                        'vat_expense' => 0,
                        'total_amount' => 0,
                        'vat_amount' => 0
                    ];
                }
                
                if ($type == 'inkomst') {
                    $monthlyByRate[$month]['rates'][$rate]['income'] += $row['total_amount'];
                    $monthlyByRate[$month]['rates'][$rate]['vat_income'] += $row['vat_amount'];
                } else {
                    $monthlyByRate[$month]['rates'][$rate]['expense'] += $row['total_amount'];
                    $monthlyByRate[$month]['rates'][$rate]['vat_expense'] += $row['vat_amount'];
                }
                
                $monthlyByRate[$month]['rates'][$rate]['total_amount'] += $row['total_amount'];
                $monthlyByRate[$month]['rates'][$rate]['vat_amount'] += $row['vat_amount'];
            }
            
            // Sort months
            ksort($monthlyByRate);
            ?>
            
            <?php foreach ($monthlyByRate as $monthNum => $monthData): ?>
            <div class="month-section" style="margin-bottom: 2rem; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1rem;">
                <h4 style="margin-top: 0; color: #2c3e50;"><?php echo $monthData['name']; ?></h4>
                
                <div class="table-container">
                    <table class="data-table" style="font-size: 0.9rem;">
                        <thead>
                            <tr>
                                <th>BTW Tarief</th>
                                <th>Inkomsten (incl. BTW)</th>
                                <th>Uitgaven (incl. BTW)</th>
                                <th>BTW over Inkomsten</th>
                                <th>BTW over Uitgaven</th>
                                <th>Totaal BTW</th>
                                <th>Netto BTW</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Sort rates
                            $rates = array_keys($monthData['rates']);
                            sort($rates);
                            
                            $monthTotalIncome = 0;
                            $monthTotalExpense = 0;
                            $monthTotalVatIncome = 0;
                            $monthTotalVatExpense = 0;
                            
                            foreach ($rates as $rate):
                                $data = $monthData['rates'][$rate];
                                $netVat = $data['vat_income'] - $data['vat_expense'];
                                
                                $monthTotalIncome += $data['income'];
                                $monthTotalExpense += $data['expense'];
                                $monthTotalVatIncome += $data['vat_income'];
                                $monthTotalVatExpense += $data['vat_expense'];
                            ?>
                            <tr>
                                <td><strong><?php echo $rate; ?>%</strong></td>
                                <td class="positive">€<?php echo number_format($data['income'], 2); ?></td>
                                <td class="negative">€<?php echo number_format($data['expense'], 2); ?></td>
                                <td class="negative">€<?php echo number_format($data['vat_income'], 2); ?></td>
                                <td class="positive">€<?php echo number_format($data['vat_expense'], 2); ?></td>
                                <td>€<?php echo number_format($data['vat_amount'], 2); ?></td>
                                <td class="<?php echo $netVat >= 0 ? 'negative' : 'positive'; ?>">
                                    <strong>€<?php echo number_format($netVat, 2); ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot style="background: #f8f9fa; font-weight: bold;">
                            <tr>
                                <td><strong>Totaal <?php echo $monthData['name']; ?>:</strong></td>
                                <td class="positive">€<?php echo number_format($monthTotalIncome, 2); ?></td>
                                <td class="negative">€<?php echo number_format($monthTotalExpense, 2); ?></td>
                                <td class="negative">€<?php echo number_format($monthTotalVatIncome, 2); ?></td>
                                <td class="positive">€<?php echo number_format($monthTotalVatExpense, 2); ?></td>
                                <td>€<?php echo number_format($monthTotalVatIncome + $monthTotalVatExpense, 2); ?></td>
                                <td class="<?php echo ($monthTotalVatIncome - $monthTotalVatExpense) >= 0 ? 'negative' : 'positive'; ?>">
                                    <strong>€<?php echo number_format($monthTotalVatIncome - $monthTotalVatExpense, 2); ?></strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Monthly summary cards -->
                <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-top: 1rem;">
                    <div class="card" style="background: #f8f9fa; padding: 0.75rem;">
                        <small class="neutral">Totaal Inkomsten</small>
                        <div class="positive" style="font-size: 1.2rem;">€<?php echo number_format($monthTotalIncome, 2); ?></div>
                    </div>
                    <div class="card" style="background: #f8f9fa; padding: 0.75rem;">
                        <small class="neutral">Totaal Uitgaven</small>
                        <div class="negative" style="font-size: 1.2rem;">€<?php echo number_format($monthTotalExpense, 2); ?></div>
                    </div>
                    <div class="card" style="background: #f8f9fa; padding: 0.75rem;">
                        <small class="neutral">BTW over Inkomsten</small>
                        <div class="negative" style="font-size: 1.2rem;">€<?php echo number_format($monthTotalVatIncome, 2); ?></div>
                    </div>
                    <div class="card" style="background: #f8f9fa; padding: 0.75rem;">
                        <small class="neutral">BTW over Uitgaven</small>
                        <div class="positive" style="font-size: 1.2rem;">€<?php echo number_format($monthTotalVatExpense, 2); ?></div>
                    </div>
                    <div class="card" style="background: linear-gradient(135deg, #2c3e50, #3498db); color: white; padding: 0.75rem; grid-column: span 2;">
                        <small style="color: rgba(255,255,255,0.9);">Netto BTW <?php echo $monthData['name']; ?></small>
                        <div style="font-size: 1.5rem; color: white;">
                            €<?php echo number_format($monthTotalVatIncome - $monthTotalVatExpense, 2); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Quarterly summary from monthly data -->
            <div class="card" style="background: linear-gradient(135deg, #34495e, #2c3e50); color: white; margin-top: 2rem;">
                <h4 style="color: white;">Kwartaal Samenvatting (op basis van maandelijkse data)</h4>
                <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <?php
                    $quarterTotalIncome = 0;
                    $quarterTotalExpense = 0;
                    $quarterTotalVatIncome = 0;
                    $quarterTotalVatExpense = 0;
                    
                    foreach ($monthlyByRate as $monthData) {
                        foreach ($monthData['rates'] as $rateData) {
                            $quarterTotalIncome += $rateData['income'];
                            $quarterTotalExpense += $rateData['expense'];
                            $quarterTotalVatIncome += $rateData['vat_income'];
                            $quarterTotalVatExpense += $rateData['vat_expense'];
                        }
                    }
                    
                    $quarterNetVat = $quarterTotalVatIncome - $quarterTotalVatExpense;
                    ?>
                    <div style="padding: 1rem;">
                        <small>Totaal Inkomsten Kwartaal</small>
                        <div style="font-size: 1.5rem; color: #a3e4d7;">€<?php echo number_format($quarterTotalIncome, 2); ?></div>
                    </div>
                    <div style="padding: 1rem;">
                        <small>Totaal Uitgaven Kwartaal</small>
                        <div style="font-size: 1.5rem; color: #f5b7b1;">€<?php echo number_format($quarterTotalExpense, 2); ?></div>
                    </div>
                    <div style="padding: 1rem;">
                        <small>BTW over Inkomsten</small>
                        <div style="font-size: 1.5rem; color: #f5b7b1;">€<?php echo number_format($quarterTotalVatIncome, 2); ?></div>
                    </div>
                    <div style="padding: 1rem;">
                        <small>BTW over Uitgaven</small>
                        <div style="font-size: 1.5rem; color: #a3e4d7;">€<?php echo number_format($quarterTotalVatExpense, 2); ?></div>
                    </div>
                    <div style="padding: 1rem; grid-column: span 2; text-align: center;">
                        <small>Netto BTW Kwartaal</small>
                        <div style="font-size: 2rem; color: white;">
                            €<?php echo number_format($quarterNetVat, 2); ?>
                        </div>
                        <div style="margin-top: 0.5rem;">
                            <?php if ($quarterNetVat > 0): ?>
                            <span style="background: #e74c3c; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.9rem;">
                                Te betalen aan Belastingdienst
                            </span>
                            <?php elseif ($quarterNetVat < 0): ?>
                            <span style="background: #2ecc71; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.9rem;">
                                Terug te ontvangen
                            </span>
                            <?php else: ?>
                            <span style="background: #7f8c8d; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.9rem;">
                                Geen BTW verschuldigd
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Monthly VAT Insight -->
        <?php if ($vatColumnsExist && !empty($monthlySummary)): ?>
        <div class="card">
            <h3 class="card-title">BTW Overzicht per Maand</h3>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Maand</th>
                            <th>BTW over Inkomsten</th>
                            <th>BTW over Aftrekbare Kosten</th>
                            <th>BTW over Niet-aftrekbare Kosten</th>
                            <th>Netto BTW per Maand</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $monthNames = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maart', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Augustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December'
                        ];
                        
                        foreach ($monthlySummary as $monthRow):
                            $month = (int)$monthRow['month'];
                            $vatIncome = $monthRow['vat_income'] ?? 0;
                            $vatExpenseDeductible = $monthRow['vat_expense_deductible'] ?? 0;
                            $vatExpenseNonDeductible = $monthRow['vat_expense_nondeductible'] ?? 0;
                            $netVatMonth = $vatIncome - $vatExpenseDeductible;
                        ?>
                        <tr>
                            <td><strong><?php echo $monthNames[$month] ?? "Maand $month"; ?></strong></td>
                            <td class="negative">€<?php echo number_format($vatIncome, 2); ?></td>
                            <td class="positive">€<?php echo number_format($vatExpenseDeductible, 2); ?></td>
                            <td class="neutral">€<?php echo number_format($vatExpenseNonDeductible, 2); ?></td>
                            <td class="<?php echo $netVatMonth >= 0 ? 'negative' : 'positive'; ?>">
                                <strong>€<?php echo number_format($netVatMonth, 2); ?></strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td>Totaal Kwartaal:</td>
                            <td class="negative">€<?php echo number_format($vatSummary['vat_on_income'], 2); ?></td>
                            <td class="positive">€<?php echo number_format($vatSummary['vat_on_expenses'], 2); ?></td>
                            <td class="neutral">€<?php
                                $nonDeductibleTotal = 0;
                                foreach ($monthlySummary as $monthRow) {
                                    $nonDeductibleTotal += $monthRow['vat_expense_nondeductible'] ?? 0;
                                }
                                echo number_format($nonDeductibleTotal, 2);
                            ?></td>
                            <td class="<?php echo $vatSummary['net_vat'] >= 0 ? 'negative' : 'positive'; ?>">
                                <strong>€<?php echo number_format($vatSummary['net_vat'], 2); ?></strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Monthly VAT Chart (simplified) -->
            <div style="margin-top: 2rem;">
                <h4>BTW Ontwikkeling per Maand</h4>
                <div style="background: white; border-radius: 8px; padding: 1rem; border: 1px solid #e0e0e0;">
                    <div style="display: flex; height: 200px; align-items: flex-end; gap: 10px; padding: 0 1rem;">
                        <?php foreach ($monthlySummary as $monthRow):
                            $month = (int)$monthRow['month'];
                            $vatIncome = $monthRow['vat_income'] ?? 0;
                            $vatExpenseDeductible = $monthRow['vat_expense_deductible'] ?? 0;
                            $netVatMonth = $vatIncome - $vatExpenseDeductible;
                            
                            // Calculate bar heights (max 150px)
                            $maxValue = max(array_map(function($m) {
                                return abs($m['vat_income'] ?? 0) + abs($m['vat_expense_deductible'] ?? 0);
                            }, $monthlySummary));
                            
                            if ($maxValue == 0) $maxValue = 1;
                            
                            $incomeHeight = ($vatIncome / $maxValue) * 150;
                            $expenseHeight = ($vatExpenseDeductible / $maxValue) * 150;
                            $netHeight = (abs($netVatMonth) / $maxValue) * 150;
                        ?>
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%;">
                            <div style="display: flex; flex-direction: column; height: 150px; width: 100%; position: relative;">
                                <!-- BTW over inkomsten (red) -->
                                <?php if ($vatIncome > 0): ?>
                                <div style="
                                    background: #dc3545;
                                    height: <?php echo $incomeHeight; ?>px;
                                    width: 30%;
                                    position: absolute;
                                    bottom: 0;
                                    left: 0;
                                    border-radius: 4px 4px 0 0;
                                " title="BTW inkomsten: €<?php echo number_format($vatIncome, 2); ?>"></div>
                                <?php endif; ?>
                                
                                <!-- BTW over kosten (green) -->
                                <?php if ($vatExpenseDeductible > 0): ?>
                                <div style="
                                    background: #28a745;
                                    height: <?php echo $expenseHeight; ?>px;
                                    width: 30%;
                                    position: absolute;
                                    bottom: 0;
                                    left: 35%;
                                    border-radius: 4px 4px 0 0;
                                " title="BTW kosten: €<?php echo number_format($vatExpenseDeductible, 2); ?>"></div>
                                <?php endif; ?>
                                
                                <!-- Netto BTW (blue/orange) -->
                                <?php if ($netVatMonth != 0): ?>
                                <div style="
                                    background: <?php echo $netVatMonth >= 0 ? '#3498db' : '#f39c12'; ?>;
                                    height: <?php echo $netHeight; ?>px;
                                    width: 30%;
                                    position: absolute;
                                    bottom: 0;
                                    left: 70%;
                                    border-radius: 4px 4px 0 0;
                                " title="Netto BTW: €<?php echo number_format($netVatMonth, 2); ?>"></div>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top: 10px; font-size: 0.8rem; text-align: center;">
                                <?php echo substr($monthNames[$month] ?? "M$month", 0, 3); ?><br>
                                <small>€<?php echo number_format($netVatMonth, 0); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="display: flex; justify-content: center; gap: 20px; margin-top: 20px;">
                        <div style="display: flex; align-items: center;">
                            <div style="width: 15px; height: 15px; background: #dc3545; margin-right: 5px;"></div>
                            <small>BTW inkomsten</small>
                        </div>
                        <div style="display: flex; align-items: center;">
                            <div style="width: 15px; height: 15px; background: #28a745; margin-right: 5px;"></div>
                            <small>BTW kosten</small>
                        </div>
                        <div style="display: flex; align-items: center;">
                            <div style="width: 15px; height: 15px; background: #3498db; margin-right: 5px;"></div>
                            <small>Netto te betalen</small>
                        </div>
                        <div style="display: flex; align-items: center;">
                            <div style="width: 15px; height: 15px; background: #f39c12; margin-right: 5px;"></div>
                            <small>Netto terug</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h3 class="card-title">Transacties in dit kwartaal</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Omschrijving</th>
                            <th>Bedrag</th>
                            <th>Type</th>
                            <th>Categorie</th>
                            <?php if ($vatColumnsExist): ?>
                            <th>BTW %</th>
                            <th>BTW bedrag</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="<?php echo $vatColumnsExist ? 7 : 5; ?>" style="text-align: center; padding: 2rem;">
                                <div class="alert alert-info">
                                    Geen transacties gevonden in dit kwartaal.
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><?php echo date('d-m-Y', strtotime($t['date'])); ?></td>
                            <td><?php echo htmlspecialchars($t['description']); ?></td>
                            <td class="<?php
                                if ($t['type'] == 'inkomst') {
                                    echo $t['amount'] >= 0 ? 'positive' : 'negative';
                                } else {
                                    echo $t['amount'] >= 0 ? 'negative' : 'positive';
                                }
                            ?>">
                                €<?php echo number_format($t['amount'], 2); ?>
                            </td>
                            <td>
                                <span class="<?php
                                    if ($t['type'] == 'inkomst') {
                                        echo $t['amount'] >= 0 ? 'positive' : 'negative';
                                    } else {
                                        echo $t['amount'] >= 0 ? 'negative' : 'positive';
                                    }
                                ?>">
                                    <?php
                                    // Show special label for credit notes
                                    if ($t['type'] == 'inkomst' && $t['amount'] < 0) {
                                        echo 'Creditnota (Inkomst)';
                                    } elseif ($t['type'] == 'uitgave' && $t['amount'] < 0) {
                                        echo 'Credit (Uitgave)';
                                    } else {
                                        echo ucfirst($t['type']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($t['category'] ?: 'Geen'); ?></td>
                            <?php if ($vatColumnsExist): ?>
                            <td>
                                <?php
                                if (isset($t['vat_percentage']) && $t['vat_percentage'] > 0) {
                                    echo '<strong>' . $t['vat_percentage'] . '%</strong>';
                                    if (isset($t['vat_rate_name']) && $t['vat_rate_name'] != $t['vat_percentage'] . '%') {
                                        echo '<br><small class="neutral">' . htmlspecialchars($t['vat_rate_name']) . '</small>';
                                    }
                                    if (isset($t['vat_included']) && $t['vat_included']) {
                                        echo '<br><small>(incl.)</small>';
                                    }
                                    if (isset($t['vat_deductible']) && $t['vat_deductible'] && $t['type'] == 'uitgave') {
                                        echo '<br><small class="positive">[aftrekbaar]</small>';
                                    }
                                } else {
                                    echo '0%';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if (isset($t['vat_amount']) && $t['vat_amount'] != 0) {
                                    echo '€' . number_format($t['vat_amount'], 2);
                                } else {
                                    echo '€0,00';
                                }
                                ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <h3 class="card-title">BTW Berekeningswijze</h3>
            <div class="alert alert-info">
                <p><strong>De BTW-berekening werkt als volgt:</strong></p>
                <ul>
                    <li><strong>Af te dragen BTW:</strong> BTW over alle inkomsten (omzet)</li>
                    <li><strong>Voorbelasting BTW:</strong> BTW over aftrekbare uitgaven (zakelijke kosten)</li>
                    <li><strong>Netto BTW:</strong> Af te dragen BTW - Voorbelasting BTW</li>
                </ul>
                <p><strong>Standaard BTW-tarieven in Nederland:</strong> 0% (vrijgesteld), 9% (verlaagd tarief), 21% (hoog tarief)</p>
            </div>
            
            <div class="alert alert-warning">
                <p><strong>Belangrijke aantekeningen:</strong></p>
                <ul>
                    <li>BTW-aangifte moet uiterlijk de laatste dag van de maand volgend op het kwartaal worden gedaan</li>
                    <li>Alleen BTW op zakelijke kosten is aftrekbaar (voorbelasting)</li>
                    <li>BTW op privé-uitgaven is niet aftrekbaar</li>
                    <li>Controleer altijd of uw berekeningen overeenkomen met uw administratie</li>
                </ul>
            </div>
        </div>
        
        <div class="btn-group">
            <a href="profit_loss.php?year=<?php echo $year; ?>" class="btn btn-secondary">Kosten Baten Overzicht</a>
            <a href="balans.php?date=<?php echo $endDate; ?>" class="btn btn-secondary">Balans Overzicht</a>
            <a href="../index.php" class="btn btn-primary">Terug naar Transacties</a>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit form when year or quarter changes on mobile
            const yearSelect = document.getElementById('year');
            const quarterSelect = document.getElementById('quarter');
            
            function checkAndSubmit() {
                // On mobile, auto-submit for better UX
                if (window.innerWidth < 768) {
                    document.querySelector('.filter-form').submit();
                }
            }
            
            yearSelect.addEventListener('change', checkAndSubmit);
            quarterSelect.addEventListener('change', checkAndSubmit);
        });
    </script>
    
    <script>
        // Profile dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const profileIcon = document.getElementById('profileIcon');
            const profileDropdown = document.getElementById('profileDropdown');
            
            if (profileIcon && profileDropdown) {
                // Toggle dropdown on click
                profileIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('show');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!profileIcon.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.remove('show');
                    }
                });
                
                // Close dropdown when clicking on a link inside it
                const dropdownLinks = profileDropdown.querySelectorAll('a');
                dropdownLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        profileDropdown.classList.remove('show');
                    });
                });
            }
        });
    </script>
    
    <footer style="text-align: center; padding: 20px; margin-top: 40px; color: #666; font-size: 12px; border-top: 1px solid #eee;">
        powered by P. Theijssen
    </footer>
</body>
</html>