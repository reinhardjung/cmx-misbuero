<?php
namespace CLOUDMEISTER\CMX\Buero;

$context = $GLOBALS['cmx_anyboard_widget_context'] ?? false;
$view = isset($_GET['view']) ? (string) $_GET['view'] : '';

if (!$context && $view !== '1' && !defined('ABSPATH')) {
    die('Oxytocin!');
}

if (!$context || $view === '1') {
    if (function_exists('content_url')) {
        $chartjs_src = content_url('/plugins/cmx-misbuero/vendor/mikuspetr/chartjs/chart.umd.min.js');
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $chartjs_src = $scheme . '://' . $host . '/wp-content/plugins/cmx-misbuero/vendor/mikuspetr/chartjs/chart.umd.min.js';
    }
    ?>
    <!doctype html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Umsatz</title>
        <style>
            html, body {
                margin: 0;
                padding: 0;
                background: #1f2430;
                color: #e9eef7;
                height: 100%;
                font-family: Arial, sans-serif;
            }
            .wrap {
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 16px;
            }
            canvas {
                max-width: 420px;
                max-height: 420px;
            }
        </style>
        <script src="<?php echo esc_url($chartjs_src); ?>" defer></script>
    </head>
    <body>
        <div class="wrap">
            <canvas id="umsatzChart" width="420" height="420"></canvas>
        </div>
        <script>
            (function () {
                function getDataUrl() {
                    var url = new URL("/wp-json/cmx-misbuero/v1/anyboard-data", window.location.origin);
                    var params = new URLSearchParams(window.location.search);
                    var user = params.get("user");
                    var pw = params.get("pw");
                    if (user) url.searchParams.set("user", user);
                    if (pw) url.searchParams.set("pw", pw);
                    return url.toString();
                }

                function renderChart(items) {
                    var ctx = document.getElementById("umsatzChart");
                    if (!ctx || !window.Chart) return;
                    var labels = items.map(function (item) { return item.label; });
                    var data = items.map(function (item) { return item.value; });
                    new Chart(ctx, {
                        type: "pie",
                        data: {
                            labels: labels,
                            datasets: [{
                                data: data,
                                backgroundColor: ["#2ecc71", "#e74c3c"],
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: "bottom",
                                    labels: { color: "#e9eef7" }
                                }
                            }
                        }
                    });
                }

                fetch(getDataUrl())
                    .then(function (response) { return response.json(); })
                    .then(function (payload) {
                        var items = (payload && payload.data && payload.data.umsatz_breakdown) ? payload.data.umsatz_breakdown : [];
                        renderChart(items);
                    })
                    .catch(function () {
                        renderChart([
                            { label: "Rechnungen", value: 0 },
                            { label: "Ausgaben", value: 0 }
                        ]);
                    });
            })();
        </script>
    </body>
    </html>
    <?php
    exit;
}

return [
    'type' => 'image',
    'width' => 1,
    'height' => 2,
    'background' => '',
    'image' => [
        'url' => '',
    ],
    'source' => [
        'endpoint' => 'stats',
        'mapping' => [
            'image.url' => 'data.umsatz_pie_url',
        ],
    ],
];
