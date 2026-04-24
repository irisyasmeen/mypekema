<?php
session_start();
include 'config.php';

// Auth check
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

// Get recent searches from session
$recent_searches = $_SESSION['recent_searches'] ?? [];
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carian Pintar AI - MyPEKEMA Management Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #002d62;
            --secondary: #1d4ed8;
            --bg-glass: rgba(255, 255, 255, 0.7);
            --border-glass: rgba(255, 255, 255, 0.2);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top right, #eef2ff, #f8fafc);
            min-height: 100vh;
        }

        .glass-panel {
            background: var(--bg-glass);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-glass);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }

        .ai-gradient-text {
            background: linear-gradient(135deg, #002d62 0%, #1d4ed8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .search-container {
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .search-focused {
            transform: translateY(-20px);
            box-shadow: 0 25px 50px -12px rgba(0, 45, 98, 0.25);
        }

        .suggestion-chip {
            transition: all 0.2s;
            cursor: pointer;
        }

        .suggestion-chip:hover {
            transform: translateY(-2px);
            background: white;
            border-color: #002d62;
            color: #002d62;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .result-item {
            animation: fadeIn 0.4s ease-out forwards;
        }

        @keyframes pulse-glow {

            0%,
            100% {
                box-shadow: 0 0 20px rgba(29, 78, 216, 0.3);
            }

            50% {
                box-shadow: 0 0 40px rgba(29, 78, 216, 0.5);
            }
        }

        .ai-thinking {
            animation: pulse-glow 2s ease-in-out infinite;
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-900">

    <?php include 'topmenu.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-12">

        <div class="max-w-5xl mx-auto mb-16 text-center">
            <div
                class="inline-flex items-center gap-2 px-4 py-1.5 bg-gradient-to-r from-blue-50 to-indigo-50 text-blue-900 rounded-full text-[10px] font-black uppercase tracking-[0.2em] mb-6 border border-blue-100">
                <i class="fas fa-sparkles"></i> AI Powered Search Engine
            </div>
            <h1 class="text-5xl md:text-6xl font-black text-slate-900 mb-6 tracking-tight">Carian <span
                    class="ai-gradient-text">Pintar</span></h1>
            <p class="text-lg text-slate-500 leading-relaxed max-w-2xl mx-auto">
                Tanya sistem apa sahaja menggunakan bahasa biasa. AI kami akan memproses pertanyaan anda dan membekalkan
                data yang tepat dalam masa nyata.
            </p>
        </div>

        <div class="max-w-4xl mx-auto search-container" id="searchWrapper">
            <!-- Search Bar -->
            <div
                class="glass-panel p-2 rounded-[2rem] flex items-center gap-2 mb-8 border-2 border-slate-200 focus-within:border-blue-800 transition-all duration-300">
                <div class="pl-6 text-slate-400">
                    <i class="fas fa-search text-xl"></i>
                </div>
                <input type="text" id="smartSearchInput"
                    class="flex-1 bg-transparent py-6 px-4 text-xl font-medium outline-none placeholder:text-slate-300"
                    placeholder="Contoh: Berapa jumlah cukai tahun 2024?">
                <button id="voiceBtn"
                    class="w-14 h-14 rounded-xl flex items-center justify-center text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-all"
                    title="Carian Suara">
                    <i class="fas fa-microphone text-lg"></i>
                </button>
                <button id="searchBtn"
                    class="bg-blue-900 text-white w-16 h-16 rounded-[1.5rem] flex items-center justify-center text-xl hover:bg-blue-800 transition-all shadow-lg shadow-blue-500/20">
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="stat-card glass-panel p-4 rounded-2xl cursor-pointer"
                    onclick="setQuery('Jumlah cukai keseluruhan')">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-blue-600">
                            <i class="fas fa-coins text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Jumlah Cukai</p>
                            <p class="text-sm font-black text-slate-800">Klik untuk lihat</p>
                        </div>
                    </div>
                </div>
                <div class="stat-card glass-panel p-4 rounded-2xl cursor-pointer"
                    onclick="setQuery('Berapa kenderaan dalam sistem')">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center text-emerald-600">
                            <i class="fas fa-car text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Kenderaan</p>
                            <p class="text-sm font-black text-slate-800">Klik untuk lihat</p>
                        </div>
                    </div>
                </div>
                <div class="stat-card glass-panel p-4 rounded-2xl cursor-pointer"
                    onclick="setQuery('Senarai syarikat')">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center text-purple-600">
                            <i class="fas fa-building text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Syarikat</p>
                            <p class="text-sm font-black text-slate-800">Klik untuk lihat</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suggestions -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Cadangan Carian:</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <div class="suggestion-chip px-4 py-2 bg-slate-100 border border-slate-200 rounded-full text-xs font-bold text-slate-600"
                        onclick="setQuery('Berapa Vellfire tahun 2023 hingga 2024')">
                        <i class="fas fa-calendar mr-1"></i> Vellfire 2023 hingga 2024
                    </div>
                    <div class="suggestion-chip px-4 py-2 bg-slate-100 border border-slate-200 rounded-full text-xs font-bold text-slate-600"
                        onclick="setQuery('Senarai Honda warna putih cc bawah 2000')">
                        <i class="fas fa-car mr-1"></i> Honda Putih CC < 2000 </div>
                            <div class="suggestion-chip px-4 py-2 bg-slate-100 border border-slate-200 rounded-full text-xs font-bold text-slate-600"
                                onclick="setQuery('Cukai tertinggi bawah 100000')">
                                <i class="fas fa-arrow-up mr-1"></i> Cukai tertinggi < RM100k </div>
                                    <div class="suggestion-chip px-4 py-2 bg-slate-100 border border-slate-200 rounded-full text-xs font-bold text-slate-600"
                                        onclick="setQuery('Senarai Toyota')">
                                        <i class="fas fa-list mr-1"></i> Senarai Toyota
                                    </div>
                                    <div class="suggestion-chip px-4 py-2 bg-slate-100 border border-slate-200 rounded-full text-xs font-bold text-slate-600"
                                        onclick="setQuery('Terkini warna hitam')">
                                        <i class="fas fa-clock mr-1"></i> Terkini Hitam
                                    </div>
                            </div>
                    </div>

                    <!-- Recent Searches -->
                    <?php if (!empty($recent_searches)): ?>
                        <div class="mb-8">
                            <div class="flex items-center gap-3 mb-4">
                                <i class="fas fa-history text-slate-400"></i>
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Carian
                                    Terkini:</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach (array_slice($recent_searches, 0, 5) as $search): ?>
                                    <div class="px-4 py-2 bg-white border border-slate-200 rounded-full text-xs font-medium text-slate-600 cursor-pointer hover:border-blue-500 hover:text-blue-600 transition-all"
                                        onclick="setQuery('<?= htmlspecialchars($search) ?>')">
                                        <?= htmlspecialchars($search) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Loader -->
                    <div id="loader" class="hidden text-center py-20">
                        <div
                            class="inline-block w-16 h-16 border-4 border-blue-100 border-t-blue-900 rounded-full animate-spin ai-thinking">
                        </div>
                        <p class="mt-6 font-bold text-slate-400 uppercase tracking-widest text-xs">AI sedang memproses
                            data...</p>
                        <p class="mt-2 text-xs text-slate-400">Menganalisis <span id="queryPreview"
                                class="font-bold text-blue-600"></span></p>
                    </div>

                    <!-- Results Section -->
                    <div id="resultsContainer" class="hidden space-y-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider">Keputusan Carian</h3>
                            <button onclick="clearResults()"
                                class="text-xs text-slate-400 hover:text-red-600 font-medium">
                                <i class="fas fa-times mr-1"></i> Kosongkan
                            </button>
                        </div>
                        <div id="searchResults">
                            <!-- Results injected here -->
                        </div>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="max-w-4xl mx-auto mt-16">
                    <div class="glass-panel p-8 rounded-3xl">
                        <div class="flex items-start gap-4">
                            <div
                                class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center text-amber-600 flex-shrink-0">
                                <i class="fas fa-lightbulb text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 mb-2">Tips Carian Pintar</h3>
                                <ul class="text-sm text-slate-600 space-y-1">
                                    <li><i class="fas fa-check text-emerald-500 mr-2"></i> Gunakan julat tahun untuk
                                        spesifik "tahun 2022 hingga 2024"</li>
                                    <li><i class="fas fa-check text-emerald-500 mr-2"></i> Nyatakan warna dan kapasiti
                                        enjin seperti "warna hitam cc bawah 2000"</li>
                                    <li><i class="fas fa-check text-emerald-500 mr-2"></i> Tetapkan had cukai seperti
                                        "jumlah kenderaan cukai bawah 50000"</li>
                                    <li><i class="fas fa-check text-emerald-500 mr-2"></i> Boleh gunakan gabungan:
                                        "senarai Honda Vellfire tahun 2023 cukai lebih 30000"</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

    </main>

    <script>
        const searchBtn = document.getElementById('searchBtn');
        const searchInput = document.getElementById('smartSearchInput');
        const resultsContainer = document.getElementById('resultsContainer');
        const searchResults = document.getElementById('searchResults');
        const loader = document.getElementById('loader');
        const wrapper = document.getElementById('searchWrapper');
        const queryPreview = document.getElementById('queryPreview');
        const voiceBtn = document.getElementById('voiceBtn');

        // Voice Search
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = new SpeechRecognition();
            recognition.lang = 'ms-MY';
            recognition.continuous = false;

            voiceBtn.addEventListener('click', () => {
                recognition.start();
                voiceBtn.innerHTML = '<i class="fas fa-circle text-red-500 animate-pulse"></i>';
            });

            recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                searchInput.value = transcript;
                voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
                performSearch();
            };

            recognition.onerror = () => {
                voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
            };
        } else {
            voiceBtn.style.display = 'none';
        }

        function setQuery(text) {
            searchInput.value = text;
            performSearch();
        }

        function clearResults() {
            resultsContainer.classList.add('hidden');
            searchResults.innerHTML = '';
            wrapper.classList.remove('search-focused');
        }

        let currentResultsData = [];
        let currentSearchQuery = '';

        const performSearch = async () => {
            const query = searchInput.value.trim();
            if (!query) return;

            currentSearchQuery = query;

            // UI Feedback
            wrapper.classList.add('search-focused');
            loader.classList.remove('hidden');
            resultsContainer.classList.add('hidden');
            searchResults.innerHTML = '';
            queryPreview.textContent = `"${query}"`;

            try {
                const formData = new FormData();
                formData.append('query', query);

                const response = await fetch('proses_carian.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                currentResultsData = result.data || [];

                setTimeout(() => {
                    loader.classList.add('hidden');
                    resultsContainer.classList.remove('hidden');
                    displayResults(result);
                }, 800);

            } catch (error) {
                loader.classList.add('hidden');
                searchResults.innerHTML = `<div class="p-8 bg-rose-50 border border-rose-100 rounded-3xl text-rose-600 text-center font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Ralat sistem. Sila cuba lagi sebentar.</div>`;
                resultsContainer.classList.remove('hidden');
            }
        };

        searchBtn.addEventListener('click', performSearch);
        searchInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') performSearch(); });

        function displayResults(result) {
            if (!result.success || !result.data || (Array.isArray(result.data) && result.data.length === 0)) {
                searchResults.innerHTML = `
                    <div class="p-12 glass-panel rounded-[2.5rem] text-center">
                        <div class="w-20 h-20 bg-slate-50 flex items-center justify-center rounded-full mx-auto mb-4 text-slate-300">
                            <i class="fas fa-search text-3xl"></i>
                        </div>
                        <p class="text-slate-400 font-bold text-lg mb-2">Tiada Keputusan Ditemui</p>
                        <p class="text-slate-400 text-sm">Cuba ubah pertanyaan anda atau gunakan cadangan carian di atas.</p>
                    </div>
                `;
                return;
            }

            if (result.type === 'summary') {
                const value = parseFloat(result.data.value) || 0;
                const count = parseInt(result.data.count) || 0;
                let formatted = new Intl.NumberFormat('ms-MY', { style: 'currency', currency: 'MYR' }).format(value);

                searchResults.innerHTML = `
                    <div class="p-10 bg-gradient-to-br from-blue-950 to-indigo-950 rounded-[2.5rem] text-white shadow-2xl shadow-blue-500/40 relative overflow-hidden result-item">
                        <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 blur-3xl rounded-full"></div>
                        <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-blue-500/20 blur-3xl rounded-full"></div>
                        <div class="relative">
                            <p class="text-xs font-black text-blue-200 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <i class="fas fa-brain"></i> Ringkasan AI
                            </p>
                            <h4 class="text-sm font-semibold text-blue-100 mb-8 opacity-70">Berdasarkan pertanyaan: "${searchInput.value}"</h4>
                            <div class="flex items-baseline gap-3 mb-6">
                                <span class="text-6xl font-black">${formatted.split('.')[0]}</span>
                                <span class="text-2xl font-bold opacity-50">.${formatted.split('.')[1] || '00'}</span>
                            </div>
                            ${count > 0 ? `<p class="text-sm text-blue-200"><i class="fas fa-info-circle mr-2"></i>Berdasarkan ${count} rekod kenderaan</p>` : ''}
                        </div>
                    </div>
                `;
            } else if (result.type === 'count') {
                const count = parseInt(result.data.count) || 0;
                searchResults.innerHTML = `
                    <div class="p-10 bg-gradient-to-br from-emerald-600 to-teal-600 rounded-[2.5rem] text-white shadow-2xl shadow-emerald-500/40 relative overflow-hidden result-item">
                        <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 blur-3xl rounded-full"></div>
                        <div class="relative">
                            <p class="text-xs font-black text-emerald-100 uppercase tracking-widest mb-2 flex items-center gap-2">
                                <i class="fas fa-chart-bar"></i> Statistik
                            </p>
                            <h4 class="text-sm font-semibold text-emerald-50 mb-8 opacity-70">Berdasarkan pertanyaan: "${searchInput.value}"</h4>
                            <div class="flex items-baseline gap-3">
                                <span class="text-7xl font-black">${count.toLocaleString()}</span>
                                <span class="text-2xl font-bold opacity-70">unit</span>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                const isCompanySearch = result.data.length > 0 && result.data[0].match_type === 'syarikat';

                if (isCompanySearch) {
                    let html = `
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="font-black text-slate-800 uppercase tracking-widest text-xs flex items-center gap-2">
                                <i class="fas fa-building text-purple-600"></i> SENARAI SYARIKAT (${result.data.length})
                            </h3>
                            <button onclick="exportResults()" class="text-xs text-purple-600 hover:text-purple-800 font-bold">
                                <i class="fas fa-download mr-1 text-sm"></i> Export
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 result-item">`;

                    result.data.forEach(item => {
                        html += `
                            <a href="gbpekema.php?search=${encodeURIComponent(item.vehicle_model)}" 
                               class="glass-panel p-6 rounded-[2rem] flex items-center gap-5 hover-lift transition-all border-2 border-transparent hover:border-purple-200 group">
                                <div class="w-16 h-16 bg-purple-50 rounded-2xl flex items-center justify-center text-purple-500 group-hover:scale-110 transition-transform shadow-inner">
                                    <i class="fas fa-building text-xl"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-black text-slate-900 truncate mb-2 text-sm uppercase">${item.vehicle_model}</h4>
                                    <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400 mb-1">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span class="truncate">${item.negeri}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400">
                                        <i class="fas fa-phone"></i>
                                        <span class="truncate">${item.no_tel}</span>
                                    </div>
                                </div>
                            </a>
                        `;
                    });

                    html += `</div>`;
                    searchResults.innerHTML = html;
                } else {
                    let html = `
                        <div class="glass-panel p-8 rounded-[2.5rem] result-item shadow-xl">
                            <div class="flex items-center justify-between mb-8">
                                <h3 class="font-black text-slate-800 uppercase tracking-widest text-xs flex items-center gap-2">
                                    <i class="fas fa-list-ul text-blue-600"></i> Senarai Padanan (${result.data.length})
                                </h3>
                                <button onclick="exportResults()" class="text-xs text-blue-600 hover:text-blue-800 font-bold">
                                    <i class="fas fa-download mr-1"></i> Export
                                </button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left">
                                    <thead>
                                        <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                            <th class="pb-4">Model Kenderaan</th>
                                            <th class="pb-4">No. Casis</th>
                                            <th class="pb-4">Syarikat</th>
                                            <th class="pb-4 text-right">Cukai Bayar</th>
                                            <th class="pb-4 text-center">Tindakan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">`;

                    result.data.forEach(item => {
                        html += `
                            <tr class="group hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 font-bold text-slate-800 text-sm">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-car text-slate-300 group-hover:text-blue-500"></i>
                                        ${item.vehicle_model}
                                    </div>
                                </td>
                                <td class="py-4 text-xs font-mono text-slate-500">${item.chassis_no || 'N/A'}</td>
                                <td class="py-4 text-xs font-bold text-slate-700">${item.company_name || 'N/A'}</td>
                                <td class="py-4 text-right font-black text-blue-900 text-sm">RM ${parseFloat(item.duty_rm || 0).toLocaleString()}</td>
                                <td class="py-4 text-center">
                                    <a href="vehicle_details.php?id=${item.id}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg text-xs font-bold transition-all">
                                        <i class="fas fa-eye"></i> Lihat
                                    </a>
                                </td>
                            </tr>
                        `;
                    });

                    html += `</tbody></table></div></div>`;
                    searchResults.innerHTML = html;
                }
            }
        }

        function exportResults() {
            if (!currentResultsData || (Array.isArray(currentResultsData) && currentResultsData.length === 0)) {
                alert('Tiada pertukaran data atau data kosong untuk dieksport.');
                return;
            }

            let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
            csvContent += "Model Kenderaan,No. Casis,Syarikat,Cukai Bayar (RM)\n";

            currentResultsData.forEach(item => {
                let model = `"${(item.vehicle_model || '').replace(/"/g, '""')}"`;
                let chassis = `"${(item.chassis_no || item.chassis_number || '').replace(/"/g, '""')}"`;
                let company = `"${(item.company_name || '').replace(/"/g, '""')}"`;
                let duty = item.duty_rm || 0;
                csvContent += `${model},${chassis},${company},${duty}\n`;
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);

            const safeQueryName = currentSearchQuery ? currentSearchQuery.replace(/[^a-z0-9]/gi, '_').toLowerCase() : 'carian';
            link.setAttribute("download", `export_${safeQueryName}.csv`);
            document.body.appendChild(link);
            link.click();
            link.remove();
        }
    </script>

</body>

</html>