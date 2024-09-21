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

// Fetch and combine data from the three tables
$sql = "
    SELECT date, SUM(value) as combined_value FROM (
        SELECT date, value FROM `US-Treasury-Security-Issuance-Bills`
        UNION ALL
        SELECT date, value FROM `US-Treasury-Security-Issuance-Bonds`
        UNION ALL
        SELECT date, value FROM `US-Treasury-Security-Issuance-Notes`
    ) AS combined
    GROUP BY date
    ORDER BY date ASC
";
$result = $conn->query($sql);

$allDates = [];
$combinedValues = [];
$combinedYoY = [];

if ($result->num_rows > 0) {
    $previousYearValue = [];
    while ($row = $result->fetch_assoc()) {
        $currentDate = $row['date'];
        $currentValue = $row['combined_value'];

        // Calculate the YoY% change if we have data from a year ago
        $currentYear = substr($currentDate, 0, 4);
        if (isset($previousYearValue[$currentYear - 1])) {
            $previousValue = $previousYearValue[$currentYear - 1];
            $yoyChange = (($currentValue - $previousValue) / $previousValue) * 100;
            $combinedYoY[] = $yoyChange;
        } else {
            $combinedYoY[] = null; // No data available for YoY% calculation
        }

        $allDates[] = $currentDate;
        $combinedValues[] = $currentValue;
        $previousYearValue[$currentYear] = $currentValue; // Store value for YoY calculation in the next year
    }
} else {
    echo "0 results for combined US Treasury Security Issuance";
}

$conn->close();

// Filter to only include dates from 2001 onwards for plotting
$filteredDates = [];
$filteredYoY = [];

foreach ($allDates as $index => $date) {
    if ($date >= '2001-01-01') {
        $filteredDates[] = $date;
        $filteredYoY[] = $combinedYoY[$index];
    }
}

// URL of the CSV file
$csvUrl = 'https://crons.catenacap.xyz/experiments/Yield_Inversion_inLiquidityERA/Synthetic10minus2_convexity_changed.csv';

// Fetch the CSV content from the URL
$csvData = file_get_contents($csvUrl);

// Parse the CSV data into an array
$lines = explode(PHP_EOL, $csvData);
$headers = str_getcsv(array_shift($lines)); // Remove and parse the header line

// Initialize arrays to hold the date and YCC data
$yccDates = [];
$yieldSuppressionYCC = [];

// Loop through each line of the CSV to extract data from 2001 onwards
foreach ($lines as $line) {
    $data = str_getcsv($line);
    if (count($data) >= 7 && $data[0] >= '2001-01-01') {
        $yccDates[] = $data[0]; // Date is in the first column
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
        x: ' . json_encode($yccDates) . ',
        y: ' . json_encode($yieldSuppressionYCC) . ',
        type: "scatter",
        name: "Yield Suppression (YCC)",
        line: { shape: "spline" }
    };

    var trace2 = {
        x: ' . json_encode($filteredDates) . ',
        y: ' . json_encode($filteredYoY) . ',
        type: "scatter",
        name: "Combined US Treasury Issuance (Bills, Bonds, Notes) YoY%",
        line: { shape: "spline" },
        yaxis: "y2"
    };

    var layout = {
        title: "Risk Free Real Yield Synthetic 10-2s - Yield Suppression (YCC) and Combined US Treasury Issuance YoY% Over Time (2001 Onwards)",
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
            title: "Combined Issuance YoY%",
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


