<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$host = 'xxx';
$username = 'xxx';
$password = 'xxx';
$database = 'xxx';

$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch US-Treasury-Security-Issuance-Bills data
$sql = "SELECT date, value FROM `US-Treasury-Security-Issuance-Bills` WHERE date >= '2001-01-01' ORDER BY date ASC";
$result = $conn->query($sql);

$usTreasuryDates = [];
$usTreasuryValues = [];
$usTreasuryYoY = [];

if ($result->num_rows > 0) {
    $previousValue = null;
    $previousYearValue = [];
    while ($row = $result->fetch_assoc()) {
        $currentDate = $row['date'];
        $currentValue = $row['value'];

        // Calculate the YoY% change if we have data from a year ago
        $currentYear = substr($currentDate, 0, 4);
        if (isset($previousYearValue[$currentYear - 1])) {
            $previousValue = $previousYearValue[$currentYear - 1];
            $yoyChange = (($currentValue - $previousValue) / $previousValue) * 100;
            $usTreasuryYoY[] = $yoyChange;
        } else {
            $usTreasuryYoY[] = null; // No data available for YoY% calculation
        }

        $usTreasuryDates[] = $currentDate;
        $usTreasuryValues[] = $currentValue;
        $previousYearValue[$currentYear] = $currentValue; // Store value for YoY calculation in the next year
    }
} else {
    echo "0 results for US-Treasury-Security-Issuance-Bills";
}

$conn->close();

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

// Loop through each line of the CSV to extract data from 2001 onwards
foreach ($lines as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 7 && $data[0] >= '2001-01-01') {
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

    var trace2 = {
        x: ' . json_encode($usTreasuryDates) . ',
        y: ' . json_encode($usTreasuryYoY) . ',
        type: "scatter",
        name: "US Treasury Security Issuance (Bills) YoY%",
        line: { shape: "spline" },
        yaxis: "y2"
    };

    var layout = {
        title: "Risk Free Real Yield Synthetic 10-2s - Yield Suppression (YCC) and US Treasury Issuance YoY% Over Time (2001 Onwards)",
        xaxis: {
            title: "Date",
            color: "#ffffff"
        },
        yaxis: {
            title: "Yield Suppression (YCC)",
            titlefont: { color: "#1f77b4" },
            tickfont: { color: "#ffffff" }
        },
        yaxis2: {
            title: "US Treasury Issuance YoY%",
            titlefont: { color: "#ff7f0e" },
            tickfont: { color: "#ffffff" },
            overlaying: "y",
            side: "right"
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

    var data = [trace1, trace2];
    Plotly.newPlot("chart", data, layout);
</script>';
?>
