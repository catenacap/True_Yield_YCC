<?php

// Get today's date for dynamic end date in the FRED API URL
$todayDate = date('Y-m-d');

// Read the CSV file
$csvFile = fopen('mortgage_treasury_data.csv', 'r');
if (!$csvFile) {
    die("Could not open the CSV file.");
}

// Extract headers
$headers = fgetcsv($csvFile);

// Initialize arrays to store the extracted data
$dateData = [];
$syntheticYieldData = [];

// Iterate through the CSV and extract Date and Synthetic 10-Year Yield
while (($row = fgetcsv($csvFile)) !== FALSE) {
    $dateData[] = $row[0]; // Assuming Date is the first column
    $syntheticYieldData[] = $row[3]; // Assuming Synthetic 10-Year Yield is the fourth column
}

fclose($csvFile);

// Fetch the T10Y2Y data from FRED
$apiUrlT10Y2Y = "https://fred.stlouisfed.org/graph/fredgraph.csv?id=T10Y2Y&cosd=1976-06-01&coed=$todayDate";
$csvDataT10Y2Y = file_get_contents($apiUrlT10Y2Y);
$linesT10Y2Y = explode("\n", $csvDataT10Y2Y);
array_shift($linesT10Y2Y); // Remove header

$fredDateData = [];
$fredT10Y2YData = [];

// Process the FRED data for T10Y2Y
foreach ($linesT10Y2Y as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $fredDateData[] = $data[0];
        $fredT10Y2YData[] = $data[1];
    }
}

// Fetch the 2-Year Treasury Yield (DGS2) data from FRED
$apiUrlDGS2 = "https://fred.stlouisfed.org/graph/fredgraph.csv?id=DGS2&cosd=1976-06-01&coed=$todayDate";
$csvDataDGS2 = file_get_contents($apiUrlDGS2);
$linesDGS2 = explode("\n", $csvDataDGS2);
array_shift($linesDGS2); // Remove header

$fredDGS2Data = [];
$syntheticMinus2YearData = [];

// Process the FRED data for DGS2 and calculate Synthetic 10-Year Yield - 2-Year Yield
foreach ($linesDGS2 as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 2 && is_numeric($data[1])) {
        $fredDGS2Data[] = $data[1];
    }
}

foreach ($syntheticYieldData as $index => $syntheticYield) {
    // Align dates with available data from FRED
    if (isset($fredDGS2Data[$index])) {
        $syntheticMinus2YearData[] = $syntheticYield - $fredDGS2Data[$index];
    } else {
        $syntheticMinus2YearData[] = null;
    }
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
    var trace1 = {
        x: ' . json_encode($dateData) . ',
        y: ' . json_encode($syntheticYieldData) . ',
        type: "scatter",
        name: "Synthetic 10-Year Yield",
        line: { shape: "spline" }
    };

    var trace2 = {
        x: ' . json_encode($fredDateData) . ',
        y: ' . json_encode($fredT10Y2YData) . ',
        type: "scatter",
        name: "T10Y2Y",
        line: { shape: "spline" }
    };

    var trace3 = {
        x: ' . json_encode($dateData) . ',
        y: ' . json_encode($syntheticMinus2YearData) . ',
        type: "scatter",
        name: "Synthetic 10-Year Yield - 2-Year Yield",
        line: { shape: "spline" }
    };

    var layout = {
        title: "Synthetic 10-Year Yield, T10Y2Y, and Synthetic 10-Year - 2-Year Yield",
        xaxis: {
            title: "Date",
            color: "#ffffff"
        },
        yaxis: {
            title: "Rate (%)",
            color: "#ffffff"
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
