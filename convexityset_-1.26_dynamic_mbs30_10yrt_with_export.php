<?php

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

// Convexity adjustment value
$convexityAdjustment = -1.26;

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

// Fetch 2-Year Treasury Rate data for Synthetic 10-Year Yield minus 2-Year
$apiUrl2YearTreasury = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=DGS2&scale=left&cosd=1976-06-01&coed=$todayDate&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=$todayDate&revision_date=$todayDate&nd=1976-06-01";
$csvData2YearTreasury = file_get_contents($apiUrl2YearTreasury);
$lines2YearTreasury = explode("\n", $csvData2YearTreasury);
array_shift($lines2YearTreasury); // Remove header
$twoYearTreasuryData = [];
foreach ($lines2YearTreasury as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $date = date('Y-m-d', strtotime($data[0]));
        $twoYearTreasuryData[$date] = $data[1];
    }
}

// Calculate Synthetic 10-Year minus 2-Year
$synthetic10Minus2 = [];
foreach ($synthetic10YearYield as $date => $synthetic10YearRate) {
    if (isset($twoYearTreasuryData[$date])) {
        $synthetic10Minus2[$date] = $synthetic10YearRate - $twoYearTreasuryData[$date];
    }
}

// Fetch 10-Year minus 2-Year Treasury Spread
$apiUrl10Y2Y = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=T10Y2Y&scale=left&cosd=1976-06-01&coed=$todayDate&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=$todayDate&revision_date=$todayDate&nd=1976-06-01";
$csvData10Y2Y = file_get_contents($apiUrl10Y2Y);
$lines10Y2Y = explode("\n", $csvData10Y2Y);
array_shift($lines10Y2Y); // Remove header
$tenYearMinusTwoYearData = [];
foreach ($lines10Y2Y as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $date = date('Y-m-d', strtotime($data[0]));
        $tenYearMinusTwoYearData[$date] = $data[1];
    }
}

// Calculate Yield Suppression (YCC) - Spread between Synthetic 10 minus 2 and 10-Year minus 2-Year Treasury Spread
$yieldSuppression = [];
foreach ($synthetic10Minus2 as $date => $syntheticSpread) {
    if (isset($tenYearMinusTwoYearData[$date])) {
        $yieldSuppression[$date] = $syntheticSpread - $tenYearMinusTwoYearData[$date];
    }
}

// Fetch Recession data from FRED API
$apiUrlRec = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=off&txtcolor=%23444444&ts=12&tts=12&width=1320&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=JHDUSRGDPBR&scale=left&cosd=1967-10-01&coed=$todayDate&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Quarterly&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=$todayDate&revision_date=$todayDate&nd=1967-10-01";
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

// Save data to CSV
$csvFileName = 'Synthetic10minus2_convexity_changed.csv';
$csvFile = fopen($csvFileName, 'w');
fputcsv($csvFile, ['Date', '30-Year Mortgage Rate', 'Adjusted 30-Year Mortgage Rate', 'Synthetic 10-Year Yield', '2-Year Treasury Rate', 'Synthetic 10 minus 2', '10-Year minus 2-Year Treasury Spread', 'Yield Suppression (YCC)']);

foreach ($mortgageData as $date => $mortgageRate) {
    $adjustedRate = isset($mortgageDataAdjusted[$date]) ? $mortgageDataAdjusted[$date] : '';
    $syntheticRate = isset($synthetic10YearYield[$date]) ? $synthetic10YearYield[$date] : '';
    $twoYearRate = isset($twoYearTreasuryData[$date]) ? $twoYearTreasuryData[$date] : '';
    $syntheticMinusTwo = isset($synthetic10Minus2[$date]) ? $synthetic10Minus2[$date] : '';
    $tenYearMinusTwoYear = isset($tenYearMinusTwoYearData[$date]) ? $tenYearMinusTwoYearData[$date] : '';
    $yieldSuppressionValue = isset($yieldSuppression[$date]) ? $yieldSuppression[$date] : '';
    fputcsv($csvFile, [$date, $mortgageRate, $adjustedRate, $syntheticRate, $twoYearRate, $syntheticMinusTwo, $tenYearMinusTwoYear, $yieldSuppressionValue]);
}

fclose($csvFile);

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
        'x1' => $todayDate, // Update to the latest available data point
        'y1' => 1,
        'fillcolor' => 'rgba(255, 0, 0, 0.2)',
        'line' => ['width' => 0]
    ];
}

// Generate Plotly chart
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
    var mortgageData = ' . json_encode($mortgageData) . ';
    var mortgageDataAdjusted = ' . json_encode($mortgageDataAdjusted) . ';
    var synthetic10YearYield = ' . json_encode($synthetic10YearYield) . ';
    var twoYearTreasuryData = ' . json_encode($twoYearTreasuryData) . ';
    var synthetic10Minus2 = ' . json_encode($synthetic10Minus2) . ';
    var tenYearMinusTwoYearData = ' . json_encode($tenYearMinusTwoYearData) . ';
    var yieldSuppression = ' . json_encode($yieldSuppression) . ';
    var recessionShapes = ' . json_encode($recessionShapes) . ';

    var trace1 = {
        x: Object.keys(mortgageData),
        y: Object.values(mortgageData),
        type: "scatter",
        name: "30-Year Mortgage Rate",
        line: { shape: "spline" },
        visible: "legendonly"
    };

    var trace2 = {
        x: Object.keys(mortgageDataAdjusted),
        y: Object.values(mortgageDataAdjusted),
        type: "scatter",
        name: "Adjusted 30-Year Mortgage Rate",
        line: { shape: "spline" },
        visible: "legendonly"
    };

    var trace3 = {
        x: Object.keys(synthetic10YearYield),
        y: Object.values(synthetic10YearYield),
        type: "scatter",
        name: "Synthetic 10-Year Yield",
        line: { shape: "spline" },
        visible: "legendonly" // Disabled by default
    };

    var trace4 = {
        x: Object.keys(synthetic10Minus2),
        y: Object.values(synthetic10Minus2),
        type: "scatter",
        name: "Synthetic 10 minus 2",
        line: { shape: "spline" }
    };

    var trace5 = {
        x: Object.keys(tenYearMinusTwoYearData),
        y: Object.values(tenYearMinusTwoYearData),
        type: "scatter",
        name: "10-Year minus 2-Year Treasury Spread",
        line: { shape: "spline" }
    };

    var trace6 = {
        x: Object.keys(yieldSuppression),
        y: Object.values(yieldSuppression),
        type: "scatter",
        name: "Yield Suppression (YCC)",
        line: { shape: "spline" }
    };

    var layout = {
        title: "Risk Free Rate (Real Rate) & US Treasury/Fed YCC",
        xaxis: {
            title: "Date",
            color: "#ffffff"
        },
        yaxis: {
            title: "Yield (%)",
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
        },
        shapes: recessionShapes,
    };

    var data = [trace1, trace2, trace3, trace4, trace5, trace6];
    Plotly.newPlot("chart", data, layout);
</script>';
?>
