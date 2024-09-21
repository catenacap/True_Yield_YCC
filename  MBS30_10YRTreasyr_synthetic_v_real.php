<?php
///doesn't function
$host = 'xxx';
$username = 'xxx';
$password = 'xxx';
$database = 'xxx';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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

// Convexity adjustment value (example value, replace with your calculation/model)
$convexityAdjustment = 0.1; // Example adjustment value in percentage points

// Apply convexity adjustment
$mortgageDataAdjusted = [];
foreach ($mortgageData as $date => $rate) {
    $mortgageDataAdjusted[$date] = $rate + $convexityAdjustment;
}

// Calculate synthetic 10-Year Yield from the convexity adjusted 30-Year Mortgage Rate
$synthetic10YearYield = [];
foreach ($mortgageDataAdjusted as $date => $rate) {
    $synthetic10YearYield[$date] = $rate - 0.5; // Example adjustment to create a synthetic 10-Year Yield
}

// Fetch 2-Year Treasury Yield data from FRED
$apiUrl2Year = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1320&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=DGS2&scale=left&cosd=1976-06-01&coed=2024-08-27&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-08-29&revision_date=2024-08-29&nd=1976-06-01";
$csvData2Year = file_get_contents($apiUrl2Year);
$lines2Year = explode("\n", $csvData2Year);
array_shift($lines2Year); // Remove header
$twoYearYield = [];
foreach ($lines2Year as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $date = date('Y-m-d', strtotime($data[0]));
        $twoYearYield[$date] = $data[1];
    }
}

// Calculate Synthetic 10-Year Yield minus 2-Year Yield
$synthetic10YearMinus2Year = [];
foreach ($synthetic10YearYield as $date => $rate) {
    if (isset($twoYearYield[$date])) {
        $synthetic10YearMinus2Year[$date] = $rate - $twoYearYield[$date];
    }
}

// Fetch 10-Year minus 2-Year Yield data from FRED
$apiUrl10YMinus2Y = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1320&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=T10Y2Y&scale=left&cosd=1976-06-01&coed=2024-08-28&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=2024-08-29&revision_date=2024-08-29&nd=1976-06-01";
$csvData10YMinus2Y = file_get_contents($apiUrl10YMinus2Y);
$lines10YMinus2Y = explode("\n", $csvData10YMinus2Y);
array_shift($lines10YMinus2Y); // Remove header
$tenYearMinusTwoYearYield = [];
foreach ($lines10YMinus2Y as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $date = date('Y-m-d', strtotime($data[0]));
        $tenYearMinusTwoYearYield[$date] = $data[1];
    }
}

// Generate Plotly chart
echo '<script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
<div id="chart"></div>
<script>
    var trace1 = {
        x: [' . implode(',', array_map(function ($d) { return "'$d'"; }, array_keys($synthetic10YearYield))) . '],
        y: [' . implode(',', array_map(function ($d) { return $d; }, $synthetic10YearYield)) . '],
        type: "scatter",
        name: "Synthetic 10-Year Yield",
        line: { shape: "spline" }
    };

    var trace2 = {
        x: [' . implode(',', array_map(function ($d) { return "'$d'"; }, array_keys($synthetic10YearMinus2Year))) . '],
        y: [' . implode(',', array_map(function ($d) { return $d; }, $synthetic10YearMinus2Year)) . '],
        type: "scatter",
        name: "Synthetic 10-Year Minus 2-Year Yield",
        line: { shape: "spline" }
    };

    var trace3 = {
        x: [' . implode(',', array_map(function ($d) { return "'$d'"; }, array_keys($tenYearMinusTwoYearYield))) . '],
        y: [' . implode(',', array_map(function ($d) { return $d; }, $tenYearMinusTwoYearYield)) . '],
        type: "scatter",
        name: "10-Year Minus 2-Year Yield from FRED",
        line: { shape: "spline" }
    };

    var layout = {
        title: "Synthetic 10-Year Yield and Spread Analysis",
        xaxis: {
            title: "Date"
        },
        yaxis: {
            title: "Yield (%)"
        }
    };

    var data = [trace1, trace2, trace3];
    Plotly.newPlot("chart", data, layout);
</script>';
?>

