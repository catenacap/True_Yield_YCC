<?php
$host = 'xxx';
$username = 'xxx';
$password = 'xxx';
$database = 'xxx';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get today's date in the format required for FRED API
$todayDate = date('Y-m-d');

// Fetch 30-Year Mortgage Rate data
$apiUrlMortgage = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=MORTGAGE30US&scale=left&cosd=1971-04-02&coed=$todayDate&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Weekly%2C%20Ending%20Thursday&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=$todayDate&revision_date=$todayDate&nd=1971-04-02";
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
$convexityAdjustment = -1.47; // Example adjustment value in percentage points

// Apply convexity adjustment
$mortgageDataAdjusted = [];
foreach ($mortgageData as $date => $rate) {
    $mortgageDataAdjusted[$date] = $rate + $convexityAdjustment;
}

// Calculate synthetic 10-Year Yield from the convexity-adjusted 30-Year Mortgage Rate
$synthetic10YearYield = [];
foreach ($mortgageDataAdjusted as $date => $rate) {
    $synthetic10YearYield[$date] = $rate - 0.5; // Example adjustment to create a synthetic 10-Year Yield
}

// Fetch 10-Year Treasury minus 2-Year Treasury data (T10Y2Y)
$apiUrlT10Y2Y = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1320&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=T10Y2Y&scale=left&cosd=1976-06-01&coed=$todayDate&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=$todayDate&revision_date=$todayDate&nd=1976-06-01";
$csvDataT10Y2Y = file_get_contents($apiUrlT10Y2Y);
$linesT10Y2Y = explode("\n", $csvDataT10Y2Y);
array_shift($linesT10Y2Y); // Remove header
$T10Y2YData = [];
foreach ($linesT10Y2Y as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $date = date('Y-m-d', strtotime($data[0]));
        $T10Y2YData[$date] = $data[1];
    }
}

// Fetch 2-Year Treasury Yield data (DGS2)
$apiUrlDGS2 = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1320&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=DGS2&scale=left&cosd=1976-06-01&coed=$todayDate&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=$todayDate&revision_date=$todayDate&nd=1976-06-01";
$csvDataDGS2 = file_get_contents($apiUrlDGS2);
$linesDGS2 = explode("\n", $csvDataDGS2);
array_shift($linesDGS2); // Remove header
$DGS2Data = [];
foreach ($linesDGS2 as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $date = date('Y-m-d', strtotime($data[0]));
        $DGS2Data[$date] = $data[1];
    }
}

// Calculate synthetic 10-Year minus 2-Year yield
$synthetic10Minus2 = [];
foreach ($synthetic10YearYield as $date => $rate) {
    if (isset($DGS2Data[$date])) {
        $synthetic10Minus2[$date] = $rate - $DGS2Data[$date];
    }
}

// Filter data for plotting (only include data from 1998 onwards)
$plotStartDate = '1998-01-01';

$filteredMortgageData = array_filter($mortgageData, function ($date) use ($plotStartDate) {
    return $date >= $plotStartDate;
}, ARRAY_FILTER_USE_KEY);

$filteredT10Y2YData = array_filter($T10Y2YData, function ($date) use ($plotStartDate) {
    return $date >= $plotStartDate;
}, ARRAY_FILTER_USE_KEY);

$filteredSynthetic10Minus2 = array_filter($synthetic10Minus2, function ($date) use ($plotStartDate) {
    return $date >= $plotStartDate;
}, ARRAY_FILTER_USE_KEY);

// Save filtered data to CSV (optional, for debugging or storage)
$csvFileName = 'mortgage_treasury_data_filtered.csv';
$csvFile = fopen($csvFileName, 'w');
fputcsv($csvFile, ['Date', '30-Year Mortgage Rate', 'Synthetic 10-2 Spread', 'T10Y2Y Spread']);
foreach ($filteredMortgageData as $date => $mortgageRate) {
    $syntheticSpread = isset($filteredSynthetic10Minus2[$date]) ? $filteredSynthetic10Minus2[$date] : '';
    $t10y2yRate = isset($filteredT10Y2YData[$date]) ? $filteredT10Y2YData[$date] : '';
    fputcsv($csvFile, [$date, $mortgageRate, $syntheticSpread, $t10y2yRate]);
}
fclose($csvFile);

// Plot with Plotly (filtered data from 1998 onwards)
echo '<style>
    body {
        background-color: #000000;
        color: #ffffff;
    }
    #chart {
        width: 100%;
        height: 100vh;
    }
</style>
<script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
<div id="chart"></div>
<script>
    var trace1 = {
        x: [' . implode(',', array_map(function ($d) { return "'$d'"; }, array_keys($filteredMortgageData))) . '],
        y: [' . implode(',', array_map(function ($d) { return $d; }, $filteredMortgageData)) . '],
        type: "scatter",
        name: "30-Year Mortgage Rate",
        line: { shape: "spline" }
    };

    var trace2 = {
        x: [' . implode(',', array_map(function ($d) { return "'$d'"; }, array_keys($filteredT10Y2YData))) . '],
        y: [' . implode(',', array_map(function ($d) { return $d; }, $filteredT10Y2YData)) . '],
        type: "scatter",
        name: "T10Y2Y Spread",
        line: { shape: "spline" }
    };

    var trace3 = {
        x: [' . implode(',', array_map(function ($d) { return "'$d'"; }, array_keys($filteredSynthetic10Minus2))) . '],
        y: [' . implode(',', array_map(function ($d) { return $d; }, $filteredSynthetic10Minus2)) . '],
        type: "scatter",
        name: "Synthetic 10-2 Spread",
        line: { shape: "spline" }
    };

    var layout = {
        title: "Mortgage, T10Y2Y Spread, and Synthetic 10-2 Spread",
        xaxis: {
            title: "Date",
            color: "#ffffff"
        },
        yaxis: {
            title: "Rate (%)",
            titlefont: { color: "#1f77b4" },
            tickfont: { color: "#ffffff" }
        },
        legend: {
            orientation: "h",
            y: -0.2,
            font: {
                color: "#ffffff"
            }
        },
        plot_bgcolor: "#000000",
        paper_bgcolor: "#000000",
        font: {
            color: "#ffffff"
        }
    };

    var data = [trace1, trace2, trace3];
    Plotly.newPlot("chart", data, layout);
</script>';
?>
