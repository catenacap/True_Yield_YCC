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

// Fetch Recession data from FRED API
$apiUrlRec = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=off&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=JHDUSRGDPBR&scale=left&cosd=1967-10-01&coed=$todayDate&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Quarterly&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=$todayDate&revision_date=$todayDate&nd=1967-10-01";
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

// Fetch 10-Year Treasury Constant Maturity Rate data
$apiUrlTreasury = "https://fred.stlouisfed.org/graph/fredgraph.csv?bgcolor=%23e1e9f0&chart_type=line&drp=0&fo=open%20sans&graph_bgcolor=%23ffffff&height=450&mode=fred&recession_bars=on&txtcolor=%23444444&ts=12&tts=12&width=1318&nt=0&thu=0&trc=0&show_legend=yes&show_axis_titles=yes&show_tooltip=yes&id=DGS10&scale=left&cosd=1962-01-02&coed=$todayDate&line_color=%234572a7&link_values=false&line_style=solid&mark_type=none&mw=3&lw=2&ost=-99999&oet=99999&mma=0&fml=a&fq=Daily&fam=avg&fgst=lin&fgsnd=2020-02-01&line_index=1&transformation=lin&vintage_date=$todayDate&revision_date=$todayDate&nd=1962-01-02";
$csvDataTreasury = file_get_contents($apiUrlTreasury);
$linesTreasury = explode("\n", $csvDataTreasury);
array_shift($linesTreasury); // Remove header
$treasuryData = [];
foreach ($linesTreasury as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $date = date('Y-m-d', strtotime($data[0]));
        $treasuryData[$date] = $data[1];
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
        'x1' => $todayDate, // Update to the latest available data point
        'y1' => 1,
        'fillcolor' => 'rgba(255, 0, 0, 0.2)',
        'line' => ['width' => 0]
    ];
}

// Generate the initial chart with JavaScript
echo '<style>
    body {
        background-color: #000000;
        color: #ffffff;
        font-family: Arial, sans-serif;
    }
    #chart {
        width: 100%;
        height: 80vh;
    }
    #controls {
        width: 100%;
        display: flex;
        justify-content: center;
        margin-top: 20px;
    }
    #sliderLabel {
        margin-right: 10px;
    }
    #convexitySlider {
        width: 200px;
    }
</style>

<div id="chart"></div>
<div id="controls">
    <span id="sliderLabel">Convexity Adjustment: <span id="convexityValue">0.00</span></span>
    <input type="range" id="convexitySlider" min="-2.00" max="2.00" step="0.01" value="0.00">
</div>

<script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
<script>
    var mortgageData = ' . json_encode($mortgageData) . ';
    var treasuryData = ' . json_encode($treasuryData) . ';
    var recessionShapes = ' . json_encode($recessionShapes) . ';

    function calculateAdjustedRates(convexityAdjustment) {
        var adjustedRates = {};
        var synthetic10YearYield = {};

        for (var date in mortgageData) {
            var rate = parseFloat(mortgageData[date]);
            adjustedRates[date] = rate + convexityAdjustment;
            synthetic10YearYield[date] = adjustedRates[date] - 0.5; // Example adjustment to create a synthetic 10-Year Yield
        }

        return {
            adjustedRates: adjustedRates,
            synthetic10YearYield: synthetic10YearYield
        };
    }

    function updateChart(convexityAdjustment) {
        var rates = calculateAdjustedRates(convexityAdjustment);

        var trace1 = {
            x: Object.keys(mortgageData),
            y: Object.values(mortgageData),
            type: "scatter",
            name: "30-Year Mortgage Rate",
            line: { shape: "spline" }
        };

        var trace2 = {
            x: Object.keys(treasuryData),
            y: Object.values(treasuryData),
            type: "scatter",
            name: "10-Year Treasury Rate",
            line: { shape: "spline" }
        };

        var trace3 = {
            x: Object.keys(rates.adjustedRates),
            y: Object.values(rates.adjustedRates),
            type: "scatter",
            name: "Adjusted 30-Year Mortgage Rate",
            line: { shape: "spline" }
        };

        var trace4 = {
            x: Object.keys(rates.synthetic10YearYield),
            y: Object.values(rates.synthetic10YearYield),
            type: "scatter",
            name: "Synthetic 10-Year Yield",
            line: { shape: "spline" }
        };

        var layout = {
            title: "30-Year Mortgage Rate, Adjusted Rate, and 10-Year Treasury Rate with Recessions",
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
            },
            shapes: recessionShapes,
        };

        var data = [trace1, trace2, trace3, trace4];
        Plotly.newPlot("chart", data, layout);
    }

    // Initial chart rendering
    updateChart(0.00);

    // Update chart when slider value changes
    document.getElementById("convexitySlider").addEventListener("input", function() {
        var convexityValue = parseFloat(this.value);
        document.getElementById("convexityValue").textContent = convexityValue.toFixed(2);
        updateChart(convexityValue);
    });
</script>';
?>
