<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'config.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$is_licensee = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'licensee');
$licensee_gb_id = $_SESSION['gbpekema_id'] ?? 0;

$where_clauses = ["v.vehicle_model IS NOT NULL", "v.vehicle_model != ''"];
if ($is_licensee) {
    $where_clauses[] = "v.gbpekema_id = " . (int)$licensee_gb_id;
}
$where_sql = implode(" AND ", $where_clauses);

$sql = "SELECT g.nama AS company, v.vehicle_model AS model, COUNT(v.id) AS value, SUM(v.duty_rm) as total_tax
        FROM vehicle_inventory v
        LEFT JOIN gbpekema g ON v.gbpekema_id = g.id
        WHERE $where_sql
        GROUP BY g.nama, v.vehicle_model";

$result = $conn->query($sql);

$links = [];
$nodes = [];
$node_ids = [];
$summary = ['total_links' => 0, 'max_value' => 0, 'total_tax' => 0];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $comp = $row['company'] ?? 'Syarikat Tidak Diketahui';
        $model = $row['model'];
        $val = (int) $row['value'];
        $tax = (float) $row['total_tax'];

        // Add nodes
        if (!isset($node_ids[$comp])) {
            $nodes[] = ['id' => $comp, 'group' => 'company', 'size' => 0, 'tax' => 0];
            $node_ids[$comp] = count($nodes) - 1;
        }
        if (!isset($node_ids[$model])) {
            $nodes[] = ['id' => $model, 'group' => 'model', 'size' => 0, 'tax' => 0];
            $node_ids[$model] = count($nodes) - 1;
        }

        // Accumulate size/tax counts for visuals
        $nodes[$node_ids[$comp]]['size'] += $val;
        $nodes[$node_ids[$comp]]['tax'] += $tax;
        $nodes[$node_ids[$model]]['size'] += $val;

        // Add link
        $links[] = [
            'source' => $comp,
            'target' => $model,
            'value' => $val,
            'tax' => $tax
        ];

        $summary['total_links']++;
        $summary['total_tax'] += $tax;
        if ($val > $summary['max_value'])
            $summary['max_value'] = $val;
    }
}
$graph_data = ['nodes' => $nodes, 'links' => $links];
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisa Rangkaian AI - MyPEKEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #0284c7;
            --secondary: #2563eb;
            --bg-light: #f8fafc;
            --glass-border: rgba(226, 232, 240, 0.8);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: #1e293b;
            overflow: hidden;
        }

        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 10px 30px -10px rgba(100, 116, 139, 0.15);
        }

        #network-container {
            width: 100vw;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1;
        }

        .node circle {
            cursor: pointer;
            transition: all 0.3s ease;
            stroke: #fff;
            stroke-width: 2px;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .node:hover circle {
            stroke: #2563eb;
            stroke-width: 4px;
        }

        .link {
            stroke: #94a3b8;
            /* Slate-400 for visibility on light */
            stroke-opacity: 0.3;
            transition: all 0.3s ease;
        }

        .link.active {
            stroke: var(--secondary);
            stroke-opacity: 0.8;
            stroke-width: 2px;
        }

        .tooltip {
            position: absolute;
            padding: 16px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            pointer-events: none;
            font-size: 12px;
            z-index: 1000;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            display: none;
            color: #334155;
            min-width: 200px;
        }

        .glow-text {
            text-shadow: none;
            /* Removed for clean look */
        }

        .sidebar-right {
            position: fixed;
            right: 24px;
            top: 100px;
            width: 320px;
            z-index: 10;
        }

        .overlay-nav {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 50;
        }
    </style>
</head>

<body>

    <div class="overlay-nav">
        <?php include 'topmenu.php'; ?>
    </div>

    <div id="network-container"></div>
    <div id="tooltip" class="tooltip"></div>

    <!-- UI Overlay -->
    <div class="fixed top-28 left-8 z-10 pointer-events-none">
        <h1 class="text-5xl font-black tracking-tighter text-slate-800">Analisa <span
                class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">Rangkaian AI</span>
        </h1>
        <p class="text-slate-500 text-sm font-bold mt-2 uppercase tracking-[0.3em] pl-1">Interkoneksi Syarikat & Model
            Kenderaan</p>

        <div class="mt-8 flex gap-4 pointer-events-auto">
            <div class="glass p-5 rounded-2xl border-l-4 border-blue-600 hover:scale-105 transition-transform">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Hubungan</p>
                <p class="text-3xl font-black text-slate-800 mt-1"><?= number_format($summary['total_links']) ?></p>
            </div>
            <div class="glass p-5 rounded-2xl border-l-4 border-indigo-600 hover:scale-105 transition-transform">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Sumbangan Cukai</p>
                <p class="text-3xl font-black text-indigo-600 mt-1">RM
                    <?= number_format($summary['total_tax'] / 1000000, 1) ?>J</p>
            </div>
        </div>
    </div>

    <!-- Right Sidebar Info -->
    <div class="sidebar-right space-y-4">
        <div class="glass p-6 rounded-[2rem]">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                <i class="fas fa-layer-group text-blue-500"></i> Legend Visual
            </h3>
            <div class="space-y-5">
                <div class="flex items-center gap-4 group">
                    <div
                        class="w-12 h-12 rounded-xl bg-blue-100 border border-blue-200 flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors shadow-sm">
                        <i class="fas fa-building text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-black text-slate-700">Syarikat PEKEMA</p>
                        <p class="text-[10px] text-slate-500 font-medium">Saiz nod = Jumlah unit import</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 group">
                    <div
                        class="w-12 h-12 rounded-xl bg-indigo-100 border border-indigo-200 flex items-center justify-center text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-colors shadow-sm">
                        <i class="fas fa-car text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-black text-slate-700">Model Kenderaan</p>
                        <p class="text-[10px] text-slate-500 font-medium">Ikon mewakili kategori model</p>
                    </div>
                </div>
                <div class="pt-6 mt-2 border-t border-slate-200">
                    <p class="text-[10px] text-slate-400 font-black mb-3 uppercase tracking-widest">Panduan Interaksi
                    </p>
                    <ul class="text-[11px] text-slate-500 space-y-2 font-medium">
                        <li class="flex items-center gap-2"><i class="fas fa-mouse-pointer text-blue-400 w-4"></i> Klik
                            & seret untuk alih nod</li>
                        <li class="flex items-center gap-2"><i class="fas fa-search-plus text-blue-400 w-4"></i> Scroll
                            untuk zoom masuk/keluar</li>
                        <li class="flex items-center gap-2"><i class="fas fa-hand-pointer text-blue-400 w-4"></i> Hover
                            untuk info terperinci</li>
                    </ul>
                </div>
            </div>
        </div>

        <button onclick="window.location.reload()"
            class="w-full glass py-4 rounded-2xl text-xs font-black text-slate-500 hover:text-blue-600 transition-all flex items-center justify-center gap-3 active:scale-95 group uppercase tracking-wider">
            <i class="fas fa-sync-alt group-hover:rotate-180 transition-transform duration-500"></i> Reset Visualisasi
        </button>
    </div>

    <script>
        const data = <?= json_encode($graph_data) ?>;

        if (data.nodes.length === 0) {
            document.getElementById('network-container').innerHTML = `
                <div class="flex flex-col items-center justify-center h-full text-slate-500">
                    <i class="fas fa-project-diagram text-6xl mb-4 opacity-20"></i>
                    <p class="font-bold">Tiada data hubungan ditemui dalam pangkalan data.</p>
                </div>
            `;
        } else {
            initGraph();
        }

        function initGraph() {
            const width = window.innerWidth;
            const height = window.innerHeight;

            const svg = d3.select("#network-container")
                .append("svg")
                .attr("width", "100%")
                .attr("height", "100%")
                .attr("viewBox", [0, 0, width, height]);

            const g = svg.append("g");

            // Define gradients (Updated active colors)
            const defs = svg.append("defs");

            const companyGradient = defs.append("radialGradient")
                .attr("id", "company-grad");
            companyGradient.append("stop").attr("offset", "0%").attr("stop-color", "#3b82f6"); // Brighter Blue
            companyGradient.append("stop").attr("offset", "100%").attr("stop-color", "#1d4ed8"); // Deep Blue

            const modelGradient = defs.append("radialGradient")
                .attr("id", "model-grad");
            modelGradient.append("stop").attr("offset", "0%").attr("stop-color", "#818cf8"); // Indigo-400
            modelGradient.append("stop").attr("offset", "100%").attr("stop-color", "#4f46e5"); // Indigo-600

            svg.call(d3.zoom()
                .extent([[0, 0], [width, height]])
                .scaleExtent([0.1, 4])
                .on("zoom", (event) => g.attr("transform", event.transform)));

            const simulation = d3.forceSimulation(data.nodes)
                .force("link", d3.forceLink(data.links).id(d => d.id).distance(200))
                .force("charge", d3.forceManyBody().strength(-800))
                .force("center", d3.forceCenter(width / 2, height / 2))
                .force("collision", d3.forceCollide().radius(d => (d.group === 'company' ? 40 : 30)));

            const link = g.append("g")
                .selectAll("line")
                .data(data.links)
                .join("line")
                .attr("class", "link")
                .attr("stroke-width", d => Math.log(d.value + 1) * 3);

            const node = g.append("g")
                .selectAll(".node")
                .data(data.nodes)
                .join("g")
                .attr("class", "node")
                .call(d3.drag()
                    .on("start", dragstarted)
                    .on("drag", dragged)
                    .on("end", dragended));

            // Tooltip handler
            const tooltip = d3.select("#tooltip");

            // Company Nodes
            node.filter(d => d.group === 'company')
                .append("circle")
                .attr("r", d => 15 + Math.sqrt(d.size) * 3)
                .attr("fill", "url(#company-grad)")
                .on("mouseover", (event, d) => {
                    tooltip.style("display", "block")
                        .html(`
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 border border-blue-100 shadow-sm">
                                    <i class="fas fa-building text-lg"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-black text-slate-800 text-sm truncate leading-tight">${d.id}</p>
                                    <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider mt-0.5">Syarikat PEKEMA</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 pt-3 border-t border-slate-100">
                                <div>
                                    <p class="text-[9px] text-slate-400 uppercase font-black tracking-widest">Jumlah Unit</p>
                                    <p class="text-lg font-black text-slate-700">${d.size}</p>
                                </div>
                                <div>
                                    <p class="text-[9px] text-slate-400 uppercase font-black tracking-widest">Anggaran Cukai</p>
                                    <p class="text-lg font-black text-emerald-500">RM ${d.tax.toLocaleString()}</p>
                                </div>
                            </div>
                        `);

                    highlightLinks(d.id, true);
                })
                .on("mousemove", (event) => {
                    tooltip.style("left", (event.pageX + 20) + "px")
                        .style("top", (event.pageY - 10) + "px");
                })
                .on("mouseout", () => {
                    tooltip.style("display", "none");
                    highlightLinks(null, false);
                });

            // Model Nodes
            const modelNodes = node.filter(d => d.group === 'model');

            modelNodes.append("circle")
                .attr("r", 20)
                .attr("fill", "url(#model-grad)")
                .attr("opacity", 0.8);

            modelNodes.append("text")
                .attr("text-anchor", "middle")
                .attr("dy", ".35em")
                .attr("font-family", "FontAwesome")
                .attr("fill", "white")
                .attr("font-size", "14px")
                .text("\uf1b9") // Car icon
                .on("mouseover", (event, d) => {
                    tooltip.style("display", "block")
                        .html(`
                            <div class="flex items-center gap-3 text-left">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 border border-indigo-100 shadow-sm">
                                    <i class="fas fa-car text-lg"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-black text-slate-800 text-sm truncate leading-tight">${d.id}</p>
                                    <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider mt-0.5">Model Kenderaan</p>
                                </div>
                            </div>
                        `);
                    highlightLinks(d.id, true);
                })
                .on("mousemove", (event) => {
                    tooltip.style("left", (event.pageX + 20) + "px")
                        .style("top", (event.pageY - 10) + "px");
                })
                .on("mouseout", () => {
                    tooltip.style("display", "none");
                    highlightLinks(null, false);
                });

            // Labels
            node.append("text")
                .attr("dx", d => (d.group === 'company' ? (20 + Math.sqrt(d.size) * 3) : 25))
                .attr("dy", ".35em")
                .attr("fill", "#475569") // Slate-600
                .attr("font-size", "11px")
                .attr("font-weight", "bold")
                .text(d => d.id);

            simulation.on("tick", () => {
                link
                    .attr("x1", d => d.source.x)
                    .attr("y1", d => d.source.y)
                    .attr("x2", d => d.target.x)
                    .attr("y2", d => d.target.y);

                node
                    .attr("transform", d => `translate(${d.x},${d.y})`);
            });

            function highlightLinks(nodeId, active) {
                if (!nodeId) {
                    link.classed("active", false);
                    return;
                }
                link.classed("active", d => d.source.id === nodeId || d.target.id === nodeId);
            }

            function dragstarted(event, d) {
                if (!event.active) simulation.alphaTarget(0.3).restart();
                d.fx = d.x;
                d.fy = d.y;
            }
            function dragged(event, d) {
                d.fx = event.x;
                d.fy = event.y;
            }
            function dragended(event, d) {
                if (!event.active) simulation.alphaTarget(0);
                d.fx = null;
                d.fy = null;
            }
        }
    </script>
</body>

</html>