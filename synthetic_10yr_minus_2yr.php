<?php
$host = 'xxxx';
$username = 'xxx';
$password = 'xxx';
$database = 'xxx';

//https://www.ft.com/content/77f545b7-e96c-46ad-b5ee-01d2a7072a02
//https://capitalwars.substack.com/p/screw-the-yield-curve
//https://www.richmondfed.org/publications/research/economic_brief/2023/eb_23-27

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

// Function to calculate dynamic convexity adjustment
function calculateConvexityAdjustment($convexity, $changeInYield) {
    return 0.5 * $convexity * pow($changeInYield, 2); // CA = 1/2 * CV * (Δy)^2
}

// Truncate the tables
truncateTable($conn, 'real_10yr_minus_synthetic_2yr');
truncateTable($conn, 'real_10yr_minus_synthetic_5yr');
truncateTable($conn, 'synthetic_10yr_minus_synthetic_2yr');

// Fetch 30-Year Mortgage Rate data
$apiUrlMortgage = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=MORTGAGE30US&scale=left&cosd=1971-04-02&coed=2024-05-30&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Weekly%2C%20Ending%20Thursday&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-06-05&revision_date=2024-06-05&nd=1971-04-02";
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

// Calculate dynamic convexity adjustment
$mortgageDataAdjusted = [];
$previousRate = null;
$convexity = 780; // Example convexity value, this should ideally be determined based on actual bond data

foreach ($mortgageData as $date => $rate) {
    if ($previousRate !== null) {
        $changeInYield = $rate - $previousRate;
        $convexityAdjustment = calculateConvexityAdjustment($convexity, $changeInYield);
        $mortgageDataAdjusted[$date] = $rate + $convexityAdjustment;
    } else {
        $mortgageDataAdjusted[$date] = $rate;
    }
    $previousRate = $rate;
}

// Calculate synthetic 10-Year Yield from the convexity adjusted 30-Year Mortgage Rate
$synthetic10YearYield = [];
foreach ($mortgageDataAdjusted as $date => $rate) {
    $synthetic10YearYield[$date] = $rate - 0.5; // Example adjustment to create a synthetic 10-Year Yield
}

// Calculate synthetic 2-Year Yield from the convexity adjusted 30-Year Mortgage Rate
$synthetic2YearYield = [];
foreach ($mortgageDataAdjusted as $date => $rate) {
    $synthetic2YearYield[$date] = $rate - 1.5; // Example adjustment to create a synthetic 2-Year Yield
}

// Calculate synthetic 5-Year Yield from the convexity adjusted 30-Year Mortgage Rate
$synthetic5YearYield = [];
foreach ($mortgageDataAdjusted as $date => $rate) {
    $synthetic5YearYield[$date] = $rate - 1.0; // Example adjustment to create a synthetic 5-Year Yield
}

// Fetch 10-Year Constant Maturity Treasury data from FRED API
$apiUrl10YCM = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=DGS10&scale=left&cosd=1962-01-02&coed=2024-06-04&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-06-05&revision_date=2024-06-05&nd=1962-01-02";
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
$apiUrlRec = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=off&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=JHDUSRGDPBR&scale=left&cosd=1967-10-01&coed=2023-10-01&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Quarterly&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-06-05&revision_date=2024-06-05&nd=1967-10-01";
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
$apiUrlT10Y2Y = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=T10Y2Y&scale=left&cosd=1976-06-01&coed=2024-06-05&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-06-05&revision_date=2024-06-05&nd=1976-06-01";
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
        'x1' => '2024-06-05', // Update to the latest available data point
        'y1' => 1,
        'fillcolor' => 'rgba(255, 0, 0, 0.2)',
        'line' => ['width' => 0]
    ];
}

// Generate Plotly chart
echo '<script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
<div id="chart"></div>
<script>
    var trace1 = {
        x: ' . json_encode(array_keys($t10y2yData)) . ',
        y: ' . json_encode(array_values($t10y2yData)) . ',
        type: "scatter",
        name: "10-Year Treasury minus 2-Year Treasury",
        line: { shape: "spline" }
    };

    var trace2 = {
        x: ' . json_encode(array_keys($synthetic10YearYield)) . ',
        y: ' . json_encode(array_values($synthetic10YearYield)) . ',
        type: "scatter",
        name: "Synthetic 10-Year Yield",
        line: { shape: "spline" }
    };

    var trace3 = {
        x: ' . json_encode(array_keys($synthetic2YearYield)) . ',
        y: ' . json_encode(array_values($synthetic2YearYield)) . ',
        type: "scatter",
        name: "Synthetic 2-Year Yield",
        line: { shape: "spline" }
    };

    var trace4 = {
        x: ' . json_encode(array_keys($synthetic5YearYield)) . ',
        y: ' . json_encode(array_values($synthetic5YearYield)) . ',
        type: "scatter",
        name: "Synthetic 5-Year Yield",
        line: { shape: "spline" }
    };

    var trace5 = {
        x: ' . json_encode(array_keys($cm10YMinusSynthetic2Y)) . ',
        y: ' . json_encode(array_values($cm10YMinusSynthetic2Y)) . ',
        type: "scatter",
        name: "10-Year Constant minus Synthetic 2-Year",
        line: { shape: "spline" }
    };

    var trace6 = {
        x: ' . json_encode(array_keys($cm10YMinusSynthetic5Y)) . ',
        y: ' . json_encode(array_values($cm10YMinusSynthetic5Y)) . ',
        type: "scatter",
        name: "10-Year Constant minus Synthetic 5-Year",
        line: { shape: "spline" }
    };

    var layout = {
        title: "Yield Spreads and Synthetic Yields with Recessions",
        xaxis: {
            title: "Date"
        },
        yaxis: {
            title: "Rate (%)",
            titlefont: { color: "#1f77b4" },
            tickfont: { color: "#1f77b4" }
        },
        shapes: ' . json_encode($recessionShapes) . '
    };

    var data = [trace1, trace5, trace6];
    Plotly.newPlot("chart", data, layout);
</script>';
?>

