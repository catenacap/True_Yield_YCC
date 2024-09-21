<?php
// URL of the CSV file
$csvUrl = 'https://crons.catenacap.xyz/experiments/Yield_Inversion_inLiquidityERA/Synthetic10minus2_convexity_changed.csv';

// Fetch the CSV content from the URL
$csvData = file_get_contents($csvUrl);

// Parse the CSV data into an array
$lines = explode(PHP_EOL, $csvData);
$headers = str_getcsv(array_shift($lines)); // Remove and parse the header line

// Initialize arrays to hold the date and YCC data
$dates = [];
$yieldSuppressionYCC = [];

// Loop through each line of the CSV to extract data
foreach ($lines as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 7) {
        $dates[] = $data[0]; // Date is in the first column
        $yieldSuppressionYCC[] = $data[7]; // "Yield Suppression (YCC)" is in the 8th column (index 7)
    }
}

// Generate the Plotly chart
echo '<style>
    body {
        background-color: #000000;
        color: #ffffff;
        font-family: Arial, sans-serif;
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
        x: ' . json_encode($dates) . ',
        y: ' . json_encode($yieldSuppressionYCC) . ',
        type: "scatter",
        name: "Yield Suppression (YCC)",
        line: { shape: "spline" }
    };

    var layout = {
        title: "Risk Free Real Yield Synthetic 10-2s - Yield Suppression (YCC) Over Time",
        xaxis: {
            title: "Date",
            color: "#ffffff"
        },
        yaxis: {
            title: "Yield Suppression (YCC)",
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

    var data = [trace1];
    Plotly.newPlot("chart", data, layout);
</script>';
?>
