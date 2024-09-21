<?php
$host = 'xxx';
$username = 'xxx';
$password = 'xxx';
$database = 'xxx';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to truncate a table
function truncateTable($conn, $table) {
    $query = "TRUNCATE TABLE $table";
    if ($conn->query($query) === FALSE) {
        echo "Error: " . $query . "<br>" . $conn->error;
    }
}

// Function to insert values into a table
function insertValue($conn, $table, $date, $value) {
    $query = "INSERT INTO $table (date, value) VALUES ('$date', $value)";
    if ($conn->query($query) === FALSE) {
        echo "Error: " . $query . "<br>" . $conn->error;
    }
}

// Calculate the current date for dynamic FRED data calls
$currentDate = date('Y-m-d');

// Truncate the tables
truncateTable($conn, 'real_10yr_minus_synthetic_2yr');
truncateTable($conn, 'real_10yr_minus_synthetic_5yr');
truncateTable($conn, 'synthetic_10yr_minus_synthetic_2yr');

// Fetch 30-Year Mortgage Rate data
$apiUrlMortgage = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=MORTGAGE30US&scale=left&cosd=1971-04-02&coed=$currentDate&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Weekly%2C%20Ending%20Thursday&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=$currentDate&revision_date=$currentDate&nd=1971-04-02";
$csvDataMortgage = file_get_contents($apiUrlMortgage);
$linesMortgage = explode("\n", $csvDataMortgage);
array_shift($linesMortgage); // Remove header
$mortgageData = [];
foreach ($linesMortgage as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $date = date('Y-m-d', strtotime($data[0]));
        $mortgageData[$date] = $data[1];
    }
}

// Calculate synthetic 10-Year, 5-Year, and 2-Year Yields using a simple method
$synthetic10YearYield = [];
$synthetic2YearYield = [];
$synthetic5YearYield = [];

foreach ($mortgageData as $date => $rate) {
    $synthetic10YearYield[$date] = $rate - 0.5; // Adjust to create a synthetic 10-Year Yield
    $synthetic2YearYield[$date] = $rate - 1.5;  // Adjust to create a synthetic 2-Year Yield
    $synthetic5YearYield[$date] = $rate - 1.0;  // Adjust to create a synthetic 5-Year Yield
}

// Fetch 10-Year Constant Maturity Treasury data from FRED API
$apiUrl10YCM = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=DGS10&scale=left&cosd=1962-01-02&coed=$currentDate&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=$currentDate&revision_date=$currentDate&nd=1962-01-02";
$csvData10YCM = file_get_contents($apiUrl10YCM);
$lines10YCM = explode("\n", $csvData10YCM);
array_shift($lines10YCM); // Remove header
$cm10YData = [];
foreach ($lines10YCM as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $date = date('Y-m-d', strtotime($data[0]));
        $cm10YData[$date] = $data[1];
    }
}

// Calculate 10-Year Constant Maturity minus synthetic 2-Year
$cm10YMinusSynthetic2Y = [];
foreach ($cm10YData as $date => $yield10) {
    if (isset($synthetic2YearYield[$date])) {
        $cm10YMinusSynthetic2Y[$date] = $yield10 - $synthetic2YearYield[$date];
        insertValue($conn, 'real_10yr_minus_synthetic_2yr', $date, $cm10YMinusSynthetic2Y[$date]);
    }
}

// Calculate 10-Year Constant Maturity minus synthetic 5-Year
$cm10YMinusSynthetic5Y = [];
foreach ($cm10YData as $date => $yield10) {
    if (isset($synthetic5YearYield[$date])) {
        $cm10YMinusSynthetic5Y[$date] = $yield10 - $synthetic5YearYield[$date];
        insertValue($conn, 'real_10yr_minus_synthetic_5yr', $date, $cm10YMinusSynthetic5Y[$date]);
    }
}

// Calculate synthetic 10-Year minus synthetic 2-Year
$synthetic10YearMinus2Year = [];
foreach ($synthetic10YearYield as $date => $yield10) {
    if (isset($synthetic2YearYield[$date])) {
        $synthetic10YearMinus2Year[$date] = $yield10 - $synthetic2YearYield[$date];
        insertValue($conn, 'synthetic_10yr_minus_synthetic_2yr', $date, $synthetic10YearMinus2Year[$date]);
    }
}

// Fetch Recession data from FRED API
$apiUrlRec = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=off&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=JHDUSRGDPBR&scale=left&cosd=1967-10-01&coed=$currentDate&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Quarterly&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=$currentDate&revision_date=$currentDate&nd=1967-10-01";
$csvDataRec = file_get_contents($apiUrlRec);
$linesRec = explode("\n", $csvDataRec);
array_shift($linesRec); // Remove header
$recData = [];
foreach ($linesRec as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2) {
        $date = date('Y-m-d', strtotime($data[0]));
        $recData[$date] = $data[1];
    }
}

// Fetch T10Y2Y data from FRED API
$apiUrlT10Y2Y = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=T10Y2Y&scale=left&cosd=1976-06-01&coed=$currentDate&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=$currentDate&revision_date=$currentDate&nd=1976-06-01";
$csvDataT10Y2Y = file_get_contents($apiUrlT10Y2Y);
$linesT10Y2Y = explode("\n", $csvDataT10Y2Y);
array_shift($linesT10Y2Y); // Remove header
$t10y2yData = [];
foreach ($linesT10Y2Y as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $date = date('Y-m-d', strtotime($data[0]));
        $t10y2yData[$date] = $data[1];
    }
}

// Prepare recession shapes
$recessionShapes = [];
$inRecession = false;
$start = '';

foreach ($recData as $date => $value) {
    if ($value == 1 && !$inRecession) {
        $inRecession = true;
        $start = $date;
    } elseif ($value == 0 && $inRecession) {
        $inRecession = false;
        $recessionShapes[] = [
            'type' => 'rect',
            'xref' => 'x',
            'yref' => 'paper',
            'x0' => $start,
            'y0' => 0,
            'x1' => $date,
            'y1' => 1,
            'fillcolor' => 'rgba(255, 0, 0, 0.2)',
            'line' => ['width' => 0]
        ];
    }
}

if ($inRecession) {
    $recessionShapes[] = [
        'type' => 'rect',
        'xref' => 'x',
        'yref' => 'paper',
        'x0' => $start,
        'y0' => 0,
        'x1' => $currentDate,
        'y1' => 1,
        'fillcolor' => 'rgba(255, 0, 0, 0.2)',
        'line' => ['width' => 0]
    ];
}

// Export the calculated data to CSV
$csvFileName = 'Synthetic_Yields_and_Spreads.csv';
$csvFile = fopen($csvFileName, 'w');
fputcsv($csvFile, ['Date', 'Synthetic 10-Year Yield', 'Synthetic 2-Year Yield', 'Synthetic 5-Year Yield', '10-Year Constant Minus Synthetic 2-Year', '10-Year Constant Minus Synthetic 5-Year']);

foreach ($synthetic10YearYield as $date => $yield10) {
    $synthetic2 = isset($synthetic2YearYield[$date]) ? $synthetic2YearYield[$date] : '';
    $synthetic5 = isset($synthetic5YearYield[$date]) ? $synthetic5YearYield[$date] : '';
    $minusSynthetic2 = isset($cm10YMinusSynthetic2Y[$date]) ? $cm10YMinusSynthetic2Y[$date] : '';
    $minusSynthetic5 = isset($cm10YMinusSynthetic5Y[$date]) ? $cm10YMinusSynthetic5Y[$date] : '';
    fputcsv($csvFile, [$date, $yield10, $synthetic2, $synthetic5, $minusSynthetic2, $minusSynthetic5]);
}

fclose($csvFile);

echo "Data has been exported to $csvFileName";

?>

