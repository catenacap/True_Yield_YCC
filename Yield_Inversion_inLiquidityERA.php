<?php
$host = 'xxxx';
$username = 'xxx';
$password = 'xxx';
$database = 'xxx';

// Connect to the database
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch 10-year yield data from FRED API (Monthly)
$apiUrl10yr = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=REAINTRATREARAT10Y&scale=left&cosd=1982-01-01&coed=2024-05-01&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Monthly&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-06-05&revision_date=2024-06-05&nd=1982-01-01";
$csvData10yr = file_get_contents($apiUrl10yr);
$lines10yr = explode("\n", $csvData10yr);
array_shift($lines10yr); // Remove header
$yield10yr = [];
foreach ($lines10yr as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $date = date('Y-m-d', strtotime($data[0]));
        $yield10yr[$date] = $data[1];
    }
}

// Fetch 5-year yield data from FRED API (Daily)
$apiUrl5yr = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=DGS5&scale=left&cosd=1962-01-02&coed=2024-06-03&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-06-05&revision_date=2024-06-05&nd=1962-01-02";
$csvData5yr = file_get_contents($apiUrl5yr);
$lines5yr = explode("\n", $csvData5yr);
array_shift($lines5yr); // Remove header
$yield5yr = [];
foreach ($lines5yr as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $date = date('Y-m-d', strtotime($data[0]));
        $yield5yr[$date] = $data[1];
    }
}

// Convert daily 5-year yield data to monthly by averaging the values
$monthly5yr = [];
foreach ($yield5yr as $date => $value) {
    $month = date('Y-m', strtotime($date));
    if (!isset($monthly5yr[$month])) {
        $monthly5yr[$month] = ['sum' => 0, 'count' => 0];
    }
    $monthly5yr[$month]['sum'] += $value;
    $monthly5yr[$month]['count'] += 1;
}

$yield5yrMonthly = [];
foreach ($monthly5yr as $month => $data) {
    $yield5yrMonthly["$month-01"] = $data['sum'] / $data['count'];
}

// Calculate 5-year minus 10-year value
$yieldDifference = [];
foreach ($yield10yr as $date => $value10yr) {
    if (isset($yield5yrMonthly[$date])) {
        $yieldDifference[$date] = $yield5yrMonthly[$date] - $value10yr;
    }
}

// Generate Plotly chart
echo '<script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
<div id="chart"></div>
<script>
    var trace = {
        x: [' . implode(',', array_map(function ($d) {
            return "'$d'";
        }, array_keys($yieldDifference))) . '],
        y: [' . implode(',', array_map(function ($d) {
            return $d;
        }, $yieldDifference)) . '],
        type: "scatter",
        name: "5-year minus 10-year Yield",
        line: { shape: "spline" }
    };
    var shapes = [{
        type: "line",
        x0: "' . array_keys($yieldDifference)[0] . '",
        x1: "' . end(array_keys($yieldDifference)) . '",
        y0: 0,
        y1: 0,
        line: {
            color: "red",
            width: 2
        }
    }];

    // Add vertical shapes for values below 0
    ' . implode("\n", array_map(function ($date, $value) {
        if ($value < 0) {
            return 'shapes.push({
                type: "rect",
                x0: "' . $date . '",
                x1: "' . $date . '",
                y0: ' . $value . ',
                y1: 0,
                fillcolor: "red",
                opacity: 0.2,
                line: {
                    width: 0
                }
            });';
        }
    }, array_keys($yieldDifference), $yieldDifference)) . '

    var layout = {
        title: "5-year minus 10-year Yield",
        xaxis: {
            title: "Date"
        },
        yaxis: {
            title: "Yield (%)"
        },
        shapes: shapes
    };
    var data = [trace];
    Plotly.newPlot("chart", data, layout);
</script>';

// Close the database connection
$conn->close();
?>

