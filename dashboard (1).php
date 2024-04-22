<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user'])) {
    header("Location: login.html");
    exit;
}

$servername = "localhost";
$username = "cicrancr_cicran1";
$password = "Spike2005.";
$dbname = "cicrancr_visitor_info";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function getSearchColumns($tableName) {
    switch ($tableName) {
        case 'admin_users':
            return ['username'];
        case 'clicks':
        case 'device_browser':
        case 'form_submissions':
        case 'page_views':
        case 'referral_source':
        case 'users':
        case 'user_ips':
        case 'user_sessions':
            return ['user_id'];
        case 'error_tracking':
            return ['user_id', 'url'];
        default:
            return [];
    }
}

function fetchData($conn, $tableName, $searchQuery = '', $page = 1, $rowsPerPage = 10, $sortOrder = 'DESC', $timeFilter = 'all') {
    $offset = ($page - 1) * $rowsPerPage;
    $searchColumns = getSearchColumns($tableName);
    $whereClauses = [];
    $dateFilterQuery = "";

    if (!empty($searchQuery)) {
        foreach ($searchColumns as $column) {
            $whereClauses[] = "$column LIKE '%$searchQuery%'";
        }
    }

    switch ($timeFilter) {
        case 'today':
            $dateFilterQuery = "DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $dateFilterQuery = "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $dateFilterQuery = "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $dateFilterQuery = "created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            break;
    }

    $whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' OR ', $whereClauses) : '';
    if (!empty($dateFilterQuery)) {
        $whereSQL .= (empty($whereSQL) ? 'WHERE ' : ' AND ') . $dateFilterQuery;
    }

    $query = "SELECT *, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as formatted_created_at FROM `$tableName` $whereSQL ORDER BY created_at $sortOrder LIMIT $offset, $rowsPerPage";
    $result = $conn->query($query);
    if (!$result) {
        throw new Exception("SQL Error: " . $conn->error);
    }
    return $result;
}

function getTotalPages($conn, $tableName, $searchQuery = '', $rowsPerPage = 10, $sortOrder = 'DESC', $timeFilter = 'all') {
    $searchColumns = getSearchColumns($tableName);
    $whereClauses = [];
    $dateFilterQuery = "";

    if (!empty($searchQuery)) {
        foreach ($searchColumns as $column) {
            $whereClauses[] = "$column LIKE '%$searchQuery%'";
        }
    }

    switch ($timeFilter) {
        case 'today':
            $dateFilterQuery = " AND DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $dateFilterQuery = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $dateFilterQuery = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $dateFilterQuery = " AND created at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            break;
    }

    $whereSQL = !empty($whereClauses) ? 'WHERE (' . implode(' OR ', $whereClauses) . ')' : '';
    $sql = "SELECT COUNT(*) as count FROM `$tableName` $whereSQL $dateFilterQuery";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return ceil($row['count'] / $rowsPerPage);
}

$searchQuery = isset($_POST['searchQuery']) ? $_POST['searchQuery'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$rowsPerPage = 10;
$sortOrder = isset($_POST['sortOrder']) ? $_POST['sortOrder'] : 'DESC';
$timeFilter = isset($_POST['timeFilter']) ? $_POST['timeFilter'] : 'all';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        table, th, td { border: 1px solid black; border-collapse: collapse; }
        th, td { padding: 10px; }
        th { text-align: left; }
        .hidden { display: none; }
        .toggle-button { background-color: #007bff; color: white; border: none; padding: 0.375rem 0.75rem; font-size: 1rem; border-radius: 0.25rem; cursor: pointer; margin-bottom: 10px; text-align: center; }
        form { margin-top: 20px; }
        .pagination { padding: 10px; }
        a { text-decoration: none; color: blue; padding: 8px; }
        .active { font-weight: bold; color: red; }
    </style>
    <script>
        function toggleVisibility(id) {
            var element = document.getElementById(id);
            element.style.display = element.style.display === "none" ? "table" : "none";
            localStorage.setItem('lastToggled', id);
        }

        document.addEventListener('DOMContentLoaded', function() {
            var lastToggled = localStorage.getItem('lastToggled');
            if (lastToggled) {
                var element = document.getElementById(lastToggled);
                if (element) {
                    element.style.display = 'table';
                }
            }
        });
    </script>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></h1>
    <a href="logout.php">Logout</a>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <input type="text" name="searchQuery" placeholder="Enter search term..." value="<?php echo htmlspecialchars($searchQuery); ?>">
        <select name="sortOrder">
            <option value="ASC" <?php if ($sortOrder === 'ASC') echo 'selected'; ?>>Oldest First</option>
            <option value="DESC" <?php if ($sortOrder === 'DESC') echo 'selected'; ?>>Latest First</option>
        </select>
        <button type="submit">Search</button>
        <button type="button" onclick="window.location.href='<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>';">Reset</button>
    </form>
    <?php
    $tables = ["admin_users", "clicks", "device_browser", "error_tracking", "form_submissions", "page_views", "referral_source", "users", "user_ips", "user_sessions"];
    foreach ($tables as $table) {
        $result = fetchData($conn, $table, $searchQuery, $page, $rowsPerPage, $sortOrder, $timeFilter);
        echo "<h2 onclick='toggleVisibility(\"$table" . "Data\")' class='toggle-button'>" . ucfirst(str_replace('_', ' ', $table)) . " - Click to Toggle</h2>";
        echo "<div id='$table" . "Data' class='hidden'><table><tr>";
        $fields = $result->fetch_fields();
        foreach ($fields as $field) {
            echo "<th>{$field->name}</th>";
        }
        echo "</tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($fields as $field) {
                echo "<td>" . htmlspecialchars($row[$field->name] ?? '') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table></div>";
        $totalPages = getTotalPages($conn, $table, $searchQuery, $rowsPerPage, $sortOrder, $timeFilter);
        if ($totalPages > 1) {
            echo "<div class='pagination'>";
            for ($i = 1; $i <= $totalPages; $i++) {
                echo "<a href='?page=$i' class='" . ($i == $page ? "active" : "") . "'>" . $i . "</a> ";
            }
            echo "</div>";
        }
    }
    ?>
</body>
</html>




























