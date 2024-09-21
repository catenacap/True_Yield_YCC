<?php
$host = 'xxxx';
$username = 'xxx';
$password = 'xxx';
$database = 'xxxx';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to fetch and parse CSV data from a URL
function fetchCSVData($url) {
    $csvData = file_get_contents($url);
    $lines = explode("\n", $csvData);
    array_shift($lines); // Remove header
    $data = [];
    foreach ($lines as $line) {
        $row = str_getcsv($line);
        if (count($row) >= 2 && is_numeric($row[1])) {
            $date = date('Y-m-d', strtotime($row[0]));
            $data[$date] = $row[1];
        }
    }
    return $data;
}

// Fetch 30-Year Mortgage Rate data
$mortgageData = fetchCSVData("https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=MORTGAGE30US&scale=left&cosd=1971-04-02&coed=2024-05-30&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Weekly%2C%20Ending%20Thursday&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-06-05&revision_date=2024-06-05&nd=1971-04-02");

// Fetch 10-Year Constant Maturity Treasury data
$cm10YData = fetchCSVData("https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=DGS10&scale=left&cosd=1962-01-02&coed=2024-06-04&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-06-05&revision_date=2024-06-05&nd=1962-01-02");

// Fetch 2-Year Constant Maturity Treasury data
$cm2YData = fetchCSVData("https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=DGS2&scale=left&cosd=1976-06-01&coed=2024-06-04&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-06-06&revision_date=2024-06-06&nd=1976-06-01");

// Calculate 30-Year Mortgage Rate minus 10-Year Constant Maturity
$mortgageMinus10YCM = [];
foreach ($mortgageData as $date => $rate30) {
    if (isset($cm10YData[$date])) {
        $mortgageMinus10YCM[$date] = $rate30 - $cm10YData[$date];
    }
}

// Fetch MOVE Index data from the database
$moveIndexData = [];
$query = "SELECT date, value FROM `US-Treasury-Move-Index`";
$result = $conn->query($query);

if ($result === false) {
    die("Error executing query: " . $conn->error);
}

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $moveIndexData[$row['date']] = $row['value'];
    }
} else {
    echo "No data found in the `US-Treasury-Move-Index` table.<br>";
}

// Fetch STLFSI4 data
$stlfsi4Data = fetchCSVData("https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=STLFSI4&scale=left&cosd=1993-12-31&coed=2024-05-24&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Weekly%2C%20Ending%20Friday&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-06-06&revision_date=2024-06-06&nd=1993-12-31");

// Calculate 30-Year Mortgage Rate minus 10-Year Constant Maturity minus 2-Year Constant Maturity
$mortgageMinus10YMinus2Y = [];
foreach ($mortgageData as $date => $rate30) {
    if (isset($cm10YData[$date]) && isset($cm2YData[$date])) {
        $mortgageMinus10YMinus2Y[$date] = $rate30 - $cm10YData[$date] - $cm2YData[$date];
    }
}

// Sort data by date
ksort($mortgageData);
ksort($cm10YData);
ksort($cm2YData);
ksort($mortgageMinus10YCM);
ksort($moveIndexData);
ksort($stlfsi4Data);
ksort($mortgageMinus10YMinus2Y);

// Fetch Recession data from FRED API
$recData = fetchCSVData("https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=off&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=JHDUSRGDPBR&scale=left&cosd=1967-10-01&coed=2023-10-01&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Quarterly&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-06-05&revision_date=2024-06-05&nd=1967-10-01");

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
        x: ' . json_encode(array_keys($mortgageData)) . ',
        y: ' . json_encode(array_values($mortgageData)) . ',
        type: "scatter",
        name: "30-Year Mortgage Rate",
        line: { shape: "spline" },
        yaxis: "y1"
    };

    var trace2 = {
        x: ' . json_encode(array_keys($cm10YData)) . ',
        y: ' . json_encode(array_values($cm10YData)) . ',
        type: "scatter",
        name: "10-Year Constant Maturity",
        line: { shape: "spline" },
        yaxis: "y2"
    };

    var trace3 = {
        x: ' . json_encode(array_keys($mortgageMinus10YCM)) . ',
        y: ' . json_encode(array_values($mortgageMinus10YCM)) . ',
        type: "scatter",
        name: "30-Year minus 10-Year",
        line: { shape: "spline" },
        yaxis: "y3"
    };

    var trace4 = {
        x: ' . json_encode(array_keys($moveIndexData)) . ',
        y: ' . json_encode(array_values($moveIndexData)) . ',
        type: "scatter",
        name: "MOVE Index",
        line: { shape: "spline" },
        yaxis: "y4"
    };

    var trace5 = {
        x: ' . json_encode(array_keys($stlfsi4Data)) . ',
        y: ' . json_encode(array_values($stlfsi4Data)) . ',
        type: "scatter",
        name: "STLFSI4",
        line: { shape: "spline" },
        yaxis: "y5"
    };

    var trace6 = {
        x: ' . json_encode(array_keys($mortgageMinus10YMinus2Y)) . ',
        y: ' . json_encode(array_values($mortgageMinus10YMinus2Y)) . ',
        type: "scatter",
        name: "30-Year minus 10-Year minus 2-Year",
        line: { shape: "spline" },
        yaxis: "y6"
    };

    var layout = {
        title: "Yield Spreads, Mortgage Rates, MOVE Index, and STLFSI4 with Recessions",
        xaxis: {
            title: "Date"
        },
        yaxis: {
            title: "30-Year Mortgage Rate",
            titlefont: { color: "#1f77b4" },
            tickfont: { color: "#1f77b4" },
            side: "left"
        },
        yaxis2: {
            title: "10-Year Constant Maturity",
            titlefont: { color: "#ff7f0e" },
            tickfont: { color: "#ff7f0e" },
            overlaying: "y",
            side: "right"
        },
        yaxis3: {
            title: "30-Year minus 10-Year",
            titlefont: { color: "#2ca02c" },
            tickfont: { color: "#2ca02c" },
            overlaying: "y",
            side: "left",
            position: 0.15
        },
        yaxis4: {
            title: "MOVE Index",
            titlefont: { color: "#d62728" },
            tickfont: { color: "#d62728" },
            overlaying: "y",
            side: "right",
            position: 0.85
        },
        yaxis5: {
            title: "STLFSI4",
            titlefont: { color: "#9467bd" },
            tickfont: { color: "#9467bd" },
            overlaying: "y",
            side: "right",
            position: 0.65
        },
        yaxis6: {
            title: "30-Year minus 10-Year minus 2-Year",
            titlefont: { color: "#8c564b" },
            tickfont: { color: "#8c564b" },
            overlaying: "y",
            side: "left",
            position: 0.35
        },
        shapes: ' . json_encode($recessionShapes) . '
    };

    var data = [trace1, trace2, trace3, trace4, trace5, trace6];
    Plotly.newPlot("chart", data, layout);
</script>';
?>


